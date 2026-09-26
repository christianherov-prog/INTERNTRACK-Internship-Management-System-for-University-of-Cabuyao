<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\InternshipStatusHistory;
use App\Models\JournalEntry;
use App\Models\Program;
use App\Models\StudentPortfolio;
use App\Models\StudentProfile;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\ControlledAttendanceWriter;
use App\Services\DocumentComplianceService;
use App\Services\JournalPeriodValidator;
use App\Support\InternshipProvisioning;
use App\Support\SupervisorIds;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent controlled demo dataset for CCS:
 * 10 companies, 12 students — BSIT 9 (3 finished / 3 ongoing / 3 fresh) and
 * BSCS 3 (1 finished / 1 ongoing / 1 fresh) — section-driven faculty
 * advisers, supervisors, attendance, journals, requirements, evaluations,
 * portfolio, and absorption tracking.
 *
 * Advisers come from the CCS section split (CcsSectionDirectory), never from
 * per-student lists; hour targets come from each program's requirement
 * (ProgramRequirementService: BSIT 500h, BSCS 300h).
 *
 * Re-running this command upserts by natural keys (student_number,
 * company_name, faculty_number) and never duplicates rows. It replicates
 * the same status-transition slot bookkeeping used by
 * InternshipStatusController::adjustSlotsForTransition() so company slot
 * counts stay correct across re-runs.
 */
class SeedCcsDemoDataset extends Command
{
    protected $signature = 'interntrack:seed-ccs-demo';

    protected $description = 'Seed the controlled CCS demo dataset (10 companies; 12 students: BSIT 3/3/3 and BSCS 1/1/1 finished/ongoing/fresh). Idempotent.';

    /** Mirrors InternshipStatusController::OCCUPYING */
    private const OCCUPYING = ['active', 'ongoing', 'placed', 'for_evaluation', 'suspended'];

    /** Mirrors InternshipStatusController::FREEING */
    private const FREEING = ['completed', 'expelled', 'deferred', 'terminated', 'failed'];

    private const PASSWORD = 'interntrack123';

    /** Weekly journal content for Computer Science interns (one distinct entry per week). */
    private const CS_JOURNAL_ACTIVITIES = [
        ['activities' => 'Onboarded to the QA team: set up the local build, read the test strategy for the claims-processing service, and ran the existing unit test suite.', 'learnings' => 'Learned how the team organizes unit, integration, and end-to-end tests and why each layer catches different defects.', 'challenges' => 'Several tests failed locally because of missing environment variables that were not in the setup guide.'],
        ['activities' => 'Wrote automated API tests for the customer-lookup endpoints using the team\'s REST testing framework, covering valid, invalid, and boundary inputs.', 'learnings' => 'Understood equivalence partitioning and boundary-value analysis applied to real request payloads.', 'challenges' => 'Test data had to be isolated so that runs did not interfere with other testers sharing the same environment.'],
        ['activities' => 'Supported a developer implementing a deduplication routine for imported records by writing test cases and benchmarking the hash-based approach against the old nested-loop version.', 'learnings' => 'Saw how moving from O(n^2) comparisons to hashing reduced processing time on large batches.', 'challenges' => 'Near-duplicate records with different letter casing needed a normalization step before hashing.'],
        ['activities' => 'Debugged a failing nightly data-processing job by reading logs, reproducing the input locally, and isolating a null-date record that crashed the parser.', 'learnings' => 'Practiced a systematic debugging approach: reproduce, isolate, fix, then add a regression test.', 'challenges' => 'The failure only occurred with production-sized files, so the reproduction dataset had to be trimmed carefully.'],
        ['activities' => 'Maintained the reporting database: archived stale staging tables, added a missing index on a frequently filtered column, and verified query plans before and after.', 'learnings' => 'Learned to read EXPLAIN output and to confirm an index is actually used before keeping it.', 'challenges' => 'Archiving had to be scheduled outside business hours to avoid locking tables used by analysts.'],
        ['activities' => 'Participated in code reviews for small pull requests, checking naming, error handling, and test coverage, and applied reviewer feedback to my own test scripts.', 'learnings' => 'Understood how review checklists keep a codebase consistent across many contributors.', 'challenges' => 'Giving concise, specific review comments without sounding vague took practice.'],
        ['activities' => 'Automated a repetitive regression checklist with a small Python script that calls the staging API and compares responses to saved baselines.', 'learnings' => 'Learned how snapshot comparisons speed up regression checks and where they can hide real changes.', 'challenges' => 'Timestamps and generated IDs in responses had to be masked before comparing baselines.'],
        ['activities' => 'Wrote technical documentation for the test harness and prepared the end-of-internship report on test coverage and defects found.', 'learnings' => 'Saw how clear documentation lets the next intern run and extend the harness without hand-holding.', 'challenges' => 'Summarizing eight weeks of defect data into a few actionable findings required careful grouping.'],
        ['activities' => 'Profiled a slow batch validation step and helped refactor it to stream records instead of loading the entire file into memory.', 'learnings' => 'Understood the memory trade-offs between batch loading and streaming for large inputs.', 'challenges' => 'Error reporting had to keep line numbers accurate after the switch to streaming.'],
        ['activities' => 'Tested the new role-based access rules for the internal admin portal, verifying each role could only reach its permitted pages and API routes.', 'learnings' => 'Learned to design negative test cases for authorization, not just positive ones.', 'challenges' => 'Some permissions were cached per session, so tests needed fresh sessions to observe changes.'],
    ];

    private JournalPeriodValidator $weekResolver;

    private DocumentComplianceService $compliance;

    public function __construct()
    {
        parent::__construct();
        $this->weekResolver = new JournalPeriodValidator();
        $this->compliance = new DocumentComplianceService();
    }

    public function handle(): int
    {
        DB::transaction(function () {
            $ccs = Department::where('code', 'CCS')->firstOrFail();
            $bsit = Program::where('code', 'BSIT')->where('department_id', $ccs->id)->firstOrFail();
            $bscs = Program::where('code', 'BSCS')->where('department_id', $ccs->id)->firstOrFail();

            $arcelito = User::where('faculty_number', 'COR-CCS-001')->firstOrFail();
            $director = User::where('role', 'director')->where('is_active', true)->orderBy('id')->firstOrFail();

            // Section → Faculty split first, so every student below resolves
            // its adviser from its section (the normal default source).
            \App\Support\CcsSectionDirectory::apply();

            $companies = $this->ensureCompanies();
            $supervisors = $this->ensureSupervisors($companies);

            // ── Students (section codes stored normalized, e.g. 4ITB / 4CSA) ──
            $clarence  = $this->ensureStudent('2300592', 'Clarence', null, 'Montealegre', 'Male', $ccs, $bsit, '4ITA');
            $angel     = $this->ensureStudent('2300590', 'Angel Luis', null, 'Taac-Taac', 'Male', $ccs, $bsit, '4ITB');
            $arthur    = $this->ensureStudent('2300595', 'Arthur', null, 'Morgan', 'Male', $ccs, $bsit, '4ITA');
            $christian = $this->ensureStudent('2300600', 'Christian Hero', 'Aboy', 'Valinado', 'Male', $ccs, $bsit, '4ITA');
            $lara      = $this->ensureStudent('2300609', 'Lara', null, 'Croft', 'Female', $ccs, $bsit, '4ITB');
            $max       = $this->ensureStudent('2300610', 'Max', null, 'Payne', 'Male', $ccs, $bsit, '4ITA');
            $mark      = $this->ensureStudent('2300500', 'Mark Joseph', 'V', 'Taduran', 'Male', $ccs, $bsit, '4ITA');
            $ellie     = $this->ensureStudent('2300501', 'Ellie', null, 'Williams', 'Female', $ccs, $bsit, '4ITB');
            $leon      = $this->ensureStudent('2300502', 'Leon', null, 'Kennedy', 'Male', $ccs, $bsit, '4ITB');

            // BSCS (2300610 is Max Payne's, so Terrence uses the next free number).
            $terrence  = $this->ensureStudent('2300613', 'Terrence John', null, 'Manlapaz', 'Male', $ccs, $bscs, '4CSA');
            $ada       = $this->ensureStudent('2300611', 'Ada', null, 'Wong', 'Female', $ccs, $bscs, '4CSB');
            $nathan    = $this->ensureStudent('2300612', 'Nathan', null, 'Drake', 'Male', $ccs, $bscs, '4CSA');

            // ── Faculty adviser from section; Arcelito coordinates all of CCS ──
            $adviser = [];
            foreach ([$clarence, $angel, $arthur, $christian, $lara, $max, $mark, $ellie, $leon, $terrence, $ada, $nathan] as $u) {
                $adviser[$u->id] = $this->assignAdviserFromSection($u, $arcelito->id);
            }

            // ── Finished students ──────────────────────────────────────
            $this->deployFinished($clarence, $companies['Infor'], $supervisors['Infor'], $adviser[$clarence->id], $arcelito->id, $director, '2026-06-03', '2026-08-28', 'absorbed', 'steady');
            $this->deployFinished($angel, $companies['Accenture Philippines'], $supervisors['Accenture Philippines'], $adviser[$angel->id], $arcelito->id, $director, '2026-05-27', '2026-08-21', 'pending', 'steady');
            $this->deployFinished($arthur, $companies['Microsoft Philippines'], $supervisors['Microsoft Philippines'], $adviser[$arthur->id], $arcelito->id, $director, '2026-06-04', '2026-08-31', 'not_hired', 'varied');
            // BSCS: program target from June 1; the finish date is the last generated working day.
            $this->deployFinished($terrence, $companies['Cognizant Philippines'], $supervisors['Cognizant Philippines'], $adviser[$terrence->id], $arcelito->id, $director, '2026-06-01', null, 'pending', 'strong', 'I received a verbal offer for an Associate QA Engineer role at Cognizant and am waiting for the written offer.');

            // ── Ongoing students ───────────────────────────────────────
            $this->deployOngoing($christian, $companies['Oracle Philippines'], $supervisors['Oracle Philippines'], $adviser[$christian->id], $arcelito->id, '2026-08-10', 240.0, 0.65);
            $this->deployOngoing($lara, $companies['IBM Philippines'], $supervisors['IBM Philippines'], $adviser[$lara->id], $arcelito->id, '2026-08-24', 184.0, 0.45);
            $this->deployOngoing($max, $companies['DXC Technology Philippines'], $supervisors['DXC Technology Philippines'], $adviser[$max->id], $arcelito->id, '2026-07-27', 296.0, 0.78);
            $this->deployOngoing($ada, $companies['NTT DATA Philippines'], $supervisors['NTT DATA Philippines'], $adviser[$ada->id], $arcelito->id, '2026-08-19', 216.0, 0.5);

            // ── Fresh students ─────────────────────────────────────────
            foreach ([$mark, $ellie, $leon, $nathan] as $u) {
                $this->ensureFreshInternship($u);
            }
        });

        $this->printSummary();

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    // Companies
    // ─────────────────────────────────────────────────────────────────

    /** @return array<string, Company> */
    private function ensureCompanies(): array
    {
        $rows = \App\Support\CcsCompanyDirectory::COMPANIES;

        $result = [];
        foreach ($rows as $row) {
            // Identity match (case/whitespace variants and the explicit alias
            // "Accenture PH"), so a legacy variant is adopted, never duplicated.
            $preexisting = \App\Support\CompanyNameNormalizer::findExisting($row['name']);
            // "New to the controlled CCS-10 list" covers both a brand new row
            // AND a pre-existing legacy-named row being onboarded into the list
            // for the first time via rename — either way this run establishes
            // its baseline capacity. Once its company_name already equals the
            // canonical name, later runs never touch slots_available again
            // (real placement logic owns it).
            $isNew = $preexisting === null || $preexisting->company_name !== $row['name'];

            $company = $preexisting ?: new Company();
            $company->fill([
                'company_name' => $row['name'],
                'address' => $company->address ?: 'Metro Manila, Philippines',
                'industry' => $row['industry'],
                'organization_type' => 'Private',
                'moa_status' => 'active',
                'is_active' => true,
                'moa_start_date' => $company->moa_start_date ?: '2026-01-05',
                'moa_expiry_date' => '2028-01-05',
                'contact_person' => $company->contact_person ?: 'HR Partnerships Office',
                'contact_email' => $company->contact_email ?: strtolower(preg_replace('/[^a-z0-9]/', '', strtolower($row['name']))).'@partners.interntrack.test',
                'contact_number' => $company->contact_number ?: '028-'.random_int(1000000, 9999999),
            ]);

            // Only set the baseline capacity the first time this controlled
            // demo company is created; never stomp on slots that have
            // already moved due to real placement logic on later runs.
            if ($isNew) {
                $company->slots_available = $row['slots'];
            }

            $company->save();
            $result[$row['name']] = $company->fresh();
        }

        return $result;
    }

    /** @param array<string, Company> $companies @return array<string, User> supervisor users keyed by company name */
    private function ensureSupervisors(array $companies): array
    {
        $defs = [
            'Infor' => ['first' => 'Miguel', 'last' => 'Santos', 'sex' => 'Male', 'code' => 'INFOR'],
            'Accenture Philippines' => ['first' => 'Patricia', 'last' => 'Gomez', 'sex' => 'Female', 'code' => 'ACN'],
            'Microsoft Philippines' => ['first' => 'Daniel', 'last' => 'Cruz', 'sex' => 'Male', 'code' => 'MSFT'],
            'Oracle Philippines' => ['first' => 'Andrea', 'last' => 'Lim', 'sex' => 'Female', 'code' => 'ORCL'],
            'IBM Philippines' => ['first' => 'Kevin', 'last' => 'Tan', 'sex' => 'Male', 'code' => 'IBM'],
            'DXC Technology Philippines' => ['first' => 'Melissa', 'last' => 'Ramos', 'sex' => 'Female', 'code' => 'DXC'],
            'Cognizant Philippines' => ['first' => 'Rafael', 'last' => 'Villanueva', 'sex' => 'Male', 'code' => 'CTS'],
            'NTT DATA Philippines' => ['first' => 'Katrina', 'last' => 'Mendoza', 'sex' => 'Female', 'code' => 'NTTD'],
        ];

        $password = Hash::make(self::PASSWORD);
        $result = [];

        foreach ($defs as $companyName => $def) {
            $company = $companies[$companyName];
            // Supervisor IDs follow the system's own SUP-#### sequence. Re-runs find
            // the account by its stable email (or its pre-rename legacy ID) and keep
            // the ID it already has; only a brand-new account draws the next number.
            $email = strtolower($def['first'].'.'.$def['last']).'@interntrack.test';
            $existing = User::withTrashed()
                ->where('role', 'supervisor')
                ->where(fn ($q) => $q->where('email', $email)
                    ->orWhere('faculty_number', 'SUP-CCSDEMO-'.$def['code']))
                ->first();
            $facultyNumber = $existing && ! str_starts_with((string) $existing->faculty_number, 'SUP-CCSDEMO-')
                ? $existing->faculty_number
                : SupervisorIds::nextFacultyNumber();

            $user = User::withTrashed()->updateOrCreate(
                ['id' => $existing?->id],
                [
                    'faculty_number' => $facultyNumber,
                    'email' => $email,
                    'password' => $password,
                    'role' => 'supervisor',
                    'sex' => $def['sex'],
                    'is_active' => true,
                    'deleted_at' => null,
                ]
            );
            if ($user->trashed()) {
                $user->restore();
            }

            SupervisorProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $def['first'],
                    'last_name' => $def['last'],
                    'email' => $email,
                    'contact_number' => '0917'.random_int(1000000, 9999999),
                    'sex' => $def['sex'],
                    'position' => 'Industry Supervisor',
                    'company_id' => $company->id,
                ]
            );

            $result[$companyName] = $user;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────
    // Students
    // ─────────────────────────────────────────────────────────────────

    private function ensureStudent(
        string $studentNumber,
        string $firstName,
        ?string $middleName,
        string $lastName,
        string $sex,
        Department $dept,
        Program $program,
        string $section
    ): User {
        $email = strtolower(preg_replace('/[^a-z]/', '', $firstName)).'.'.strtolower(preg_replace('/[^a-z]/', '', $lastName)).'@uc.edu.ph';
        $password = Hash::make(self::PASSWORD);
        $targetHours = $this->programTargetHours($program);

        $user = User::withTrashed()->updateOrCreate(
            ['student_number' => $studentNumber],
            [
                'email' => $email,
                'password' => $password,
                'role' => 'student',
                'sex' => $sex,
                'is_active' => true,
                'deleted_at' => null,
            ]
        );
        if ($user->trashed()) {
            $user->restore();
        }

        StudentProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'student_number' => $studentNumber,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'email' => $email,
                'contact_number' => '0917'.random_int(1000000, 9999999),
                'sex' => $sex,
                'course_description' => ($program->code === 'BSCS' ? 'CS' : 'IT').' Practicum ('.(int) $targetHours.' hours)',
                'year_level' => 4,
                'section' => $section,
                'school_year' => '2025-2026',
                'semester' => '2nd Semester',
                'enrollment_status' => 'Enrolled',
                'department_id' => $dept->id,
                'program_id' => $program->id,
                'synced_at' => now(),
            ]
        );

        // Only provision a pending internship when this student has NONE at
        // all. createPendingIfNone() itself only checks "open/current"
        // statuses (excludes 'completed'), so calling it unconditionally on
        // every run would spawn a stray new row for an already-finished
        // demo student before placeStudent() ever runs.
        if (! Internship::where('student_id', $user->id)->exists()) {
            InternshipProvisioning::createPendingIfNone($user->fresh(), [
                'school_year' => '2025-2026',
                'semester' => '2nd Semester',
                'program' => $program->name,
                'target_hours' => $targetHours,
            ]);
        }

        return $user->fresh();
    }

    private function programTargetHours(Program $program): float
    {
        $hours = \App\Services\ProgramRequirementService::targetHoursFor($program);

        return $hours > 0 ? $hours : 500.0;
    }

    private function targetHoursFor(User $student): float
    {
        $program = $student->studentProfile?->program;

        return $program ? $this->programTargetHours($program) : 500.0;
    }

    /**
     * The adviser is whoever the Student's section is assigned to — the
     * same rule used for every real Student — recorded on the internship.
     */
    private function assignAdviserFromSection(User $student, int $coordinatorId): int
    {
        $profile = $student->studentProfile()->with('program')->firstOrFail();
        $faculty = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile);
        if (! $faculty) {
            throw new \RuntimeException("No Faculty is assigned to section {$profile->section} ({$profile->student_number}).");
        }

        $internship = Internship::where('student_id', $student->id)->orderByDesc('id')->first();
        if ($internship
            && ((int) $internship->faculty_id !== (int) $faculty->id || (int) $internship->coordinator_id !== $coordinatorId)) {
            $internship->forceFill(['faculty_id' => $faculty->id, 'coordinator_id' => $coordinatorId])->saveQuietly();
        }

        return $faculty->id;
    }

    /** Fresh students: keep pending_placement, no company/supervisor/hours. */
    private function ensureFreshInternship(User $student): void
    {
        $internship = InternshipProvisioning::openForStudent($student->id);
        if (! $internship) {
            return;
        }
        // Idempotent no-op unless a previous partial run left placement data.
        if ($internship->status !== 'pending_placement' || $internship->company_id) {
            $internship->forceFill([
                'status' => 'pending_placement',
                'company_id' => null,
                'supervisor_id' => null,
                'start_date' => null,
                'end_date' => null,
                'total_hours_rendered' => 0,
            ])->saveQuietly();
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Placement + slot bookkeeping (mirrors InternshipStatusController)
    // ─────────────────────────────────────────────────────────────────

    private function applySlotTransition(Company $company, string $from, string $to): void
    {
        $wasOccupying = in_array($from, self::OCCUPYING, true);
        $willOccupy = in_array($to, self::OCCUPYING, true);
        $wasFreeing = in_array($from, self::FREEING, true);
        $willFree = in_array($to, self::FREEING, true);

        if ($from === $to) {
            return;
        }

        if ($wasOccupying && $willFree) {
            $company->releaseSlot();

            return;
        }

        if ((! $wasOccupying || $wasFreeing) && $willOccupy) {
            $company->refresh();
            if (! $company->isEligibleForPlacement()) {
                $this->warn("  ! {$company->company_name} not eligible for placement ({$company->ineligibilityReason()}) — skipping slot consumption.");

                return;
            }
            $company->consumeSlot();
        }
    }

    private function transitionStatus(Internship $internship, Company $company, string $toStatus, array $extra = []): Internship
    {
        $from = $internship->status;
        $internship->forceFill(array_merge(['status' => $toStatus], $extra))->save();
        $this->applySlotTransition($company, $from, $toStatus);

        if ($from !== $toStatus) {
            InternshipStatusHistory::create([
                'internship_id' => $internship->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'reason' => 'Status recorded during internship record setup.',
                'changed_by' => $internship->coordinator_id ?? $internship->faculty_id,
            ]);
        }

        return $internship->fresh();
    }

    private function placeStudent(User $student, Company $company, User $supervisor, int $facultyId, int $coordinatorId, string $startDate): Internship
    {
        // Each of these controlled demo students owns exactly ONE
        // internship row. openForStudent() only returns "open/current"
        // statuses and excludes 'completed' — using it here would make a
        // re-run on an already-finished student create a brand new row
        // every time instead of reusing the one this command already
        // produced, breaking idempotency. Look up by student regardless of
        // status instead.
        $internship = Internship::where('student_id', $student->id)->orderByDesc('id')->first();
        if (! $internship) {
            $internship = InternshipProvisioning::createPendingIfNone($student, ['target_hours' => $this->targetHoursFor($student)]);
        }

        // A student may arrive here already "occupying" a slot at a DIFFERENT
        // company from earlier/unrelated demo state (e.g. StudentAccountsSeeder
        // leaves Clarence 'ongoing' at another company), or occupying with NO
        // company at all. Status-bucket transition logic alone treats
        // occupying→occupying as a no-op, which would never release a stale
        // company's slot nor consume this one's. Release any stale slot
        // explicitly and reset to a neutral status first so the normal
        // transition below correctly consumes the intended company.
        if (
            in_array($internship->status, self::OCCUPYING, true)
            && (int) $internship->company_id !== (int) $company->id
        ) {
            if ($internship->company_id) {
                Company::find($internship->company_id)?->releaseSlot();
            }
            $internship->forceFill(['status' => 'pending_placement', 'company_id' => null])->save();
        }

        // The controlled workflow is apply → accepted → placed at ONE company:
        // drop leftover pending/approved demo applications to other companies so
        // they cannot make a second company look current for this student.
        InternshipApplication::where('student_id', $student->id)
            ->where('company_id', '!=', $company->id)
            ->whereIn('status', ['pending', 'approved'])
            ->delete();

        InternshipApplication::updateOrCreate(
            ['student_id' => $student->id, 'company_id' => $company->id],
            ['status' => 'approved', 'coordinator_remarks' => 'Approved for placement.']
        );

        $internship = $this->transitionStatus($internship, $company, 'active', [
            'company_id' => $company->id,
            'supervisor_id' => $supervisor->id,
            'faculty_id' => $facultyId,
            'coordinator_id' => $coordinatorId,
            'start_date' => $startDate,
            'target_hours' => $this->targetHoursFor($student),
        ]);

        return $internship;
    }

    // ─────────────────────────────────────────────────────────────────
    // Finished students
    // ─────────────────────────────────────────────────────────────────

    private function deployFinished(
        User $student,
        Company $company,
        User $supervisor,
        int $facultyId,
        int $coordinatorId,
        User $director,
        string $startDate,
        ?string $endDate,
        string $absorptionStatus,
        string $ratingProfile,
        ?string $studentHireDeclaration = null
    ): void {
        $this->line("Deploying finished student {$student->studentProfile->student_number} ({$student->studentProfile->first_name} {$student->studentProfile->last_name}) at {$company->company_name}...");

        $internship = $this->placeStudent($student, $company, $supervisor, $facultyId, $coordinatorId, $startDate);

        $targetHours = $this->targetHoursFor($student);
        $lastWorkday = $this->seedAttendance($internship, $supervisor, $startDate, $endDate, $targetHours);
        // No fixed finish date: the internship ends on its last working day.
        $endDate ??= $lastWorkday;
        $internship = $internship->fresh();

        $internship = $this->transitionStatus($internship, $company, 'completed', [
            'end_date' => $endDate,
            'expected_end_date' => $endDate,
            'evaluation_period_status' => 'approved',
            'evaluation_period_approved_by' => $facultyId,
            'evaluation_period_approved_at' => Carbon::parse($endDate)->subDays(3),
        ]);

        $this->seedJournals($internship, $facultyId, $endDate, 'all_approved');
        $this->seedRequirements($internship, $student, 'all_approved', $facultyId);
        $this->seedEvaluations($internship, $supervisor, $student, $facultyId, $director, $endDate, $ratingProfile);
        $this->seedPortfolio($internship, $student, $company, true);

        $internship->forceFill([
            'absorption_status' => $absorptionStatus,
            'absorbed_at' => $absorptionStatus === 'absorbed' ? Carbon::parse($endDate)->addDays(14)->toDateString() : null,
            'job_title' => $absorptionStatus === 'absorbed' ? 'Junior Software Developer' : null,
            'absorption_notes' => match ($absorptionStatus) {
                'absorbed' => 'Offered a full-time Junior Software Developer role after strong final evaluation ratings.',
                'not_hired' => 'No open headcount at end of internship; HTE cited hiring freeze, not performance.',
                default => 'Awaiting PALD Director confirmation of hire outcome.',
            },
            'absorption_recorded_by' => $absorptionStatus === 'pending' ? null : $director->id,
            'absorption_recorded_at' => $absorptionStatus === 'pending' ? null : Carbon::parse($endDate)->addDays(14),
            'absorption_recorded_by_role' => $absorptionStatus === 'pending' ? null : 'director',
            'student_declared_hired' => $studentHireDeclaration !== null,
            'student_declared_at' => $studentHireDeclaration !== null ? Carbon::parse($endDate)->addDays(10) : null,
            'student_declaration_notes' => $studentHireDeclaration,
        ])->save();
    }

    // ─────────────────────────────────────────────────────────────────
    // Ongoing students
    // ─────────────────────────────────────────────────────────────────

    private function deployOngoing(
        User $student,
        Company $company,
        User $supervisor,
        int $facultyId,
        int $coordinatorId,
        string $startDate,
        float $hours,
        float $requirementApprovalRatio
    ): void {
        $this->line("Deploying ongoing student {$student->studentProfile->student_number} ({$student->studentProfile->first_name} {$student->studentProfile->last_name}) at {$company->company_name}...");

        $internship = $this->placeStudent($student, $company, $supervisor, $facultyId, $coordinatorId, $startDate);

        $lastAttendanceDate = $this->seedAttendance($internship, $supervisor, $startDate, null, $hours);
        $internship = $internship->fresh();

        $remainingHours = max(0.0, $this->targetHoursFor($student) - $hours);
        $remainingDays = (int) ceil($remainingHours / 8);
        $expectedEnd = $this->addWorkdays(Carbon::parse($lastAttendanceDate), $remainingDays);

        $internship->forceFill(['expected_end_date' => $expectedEnd->toDateString()])->save();

        $this->seedJournals($internship, $facultyId, null, 'mixed');
        $this->seedRequirements($internship, $student, 'mixed', $facultyId, $requirementApprovalRatio);
        $this->seedPortfolio($internship, $student, $company, false);
    }

    // ─────────────────────────────────────────────────────────────────
    // Attendance
    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns the date string of the last attendance day written.
     *
     * Every day is written through ControlledAttendanceWriter: an approved
     * Asia/Manila 08:00–17:00 schedule, realistic clock times stored in the
     * same representation as live clock-in, a recorded lunch break, and
     * credited hours computed by the DTR service (8.00 per full day; the
     * final partial morning makes up any remainder of the program target).
     */
    private function seedAttendance(Internship $internship, User $supervisor, string $startDate, ?string $capEndDate, float $targetHours): string
    {
        $writer = app(ControlledAttendanceWriter::class);
        $writer->ensureStandardSchedule($internship, $supervisor->id, Carbon::parse($startDate)->toDateString());

        $existing = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
        $fullDays = (int) floor($targetHours / 8);
        $leftover = round($targetHours - ($fullDays * 8), 2);

        $today = Carbon::now('Asia/Manila')->startOfDay();
        $cap = $capEndDate ? Carbon::parse($capEndDate) : $today;

        $date = Carbon::parse($startDate)->startOfDay();
        $written = 0;
        $lastDate = $date->copy();

        // Only (re)generate if not already fully seeded — keeps re-runs idempotent.
        // Rows left by the earlier generator (Manila times stored as UTC, no
        // break) are rewritten in place so totals and dates stay the same.
        if ($existing >= $targetHours - 0.01) {
            $writer->repairLegacyRows($internship, $supervisor->id);
            $internship->refreshTotalHours();
            $lastLog = $internship->attendance()->where('status', 'validated')->orderByDesc('date')->first();

            return $lastLog?->date ?? $startDate;
        }

        // Clear ALL prior attendance for this internship (any status,
        // including previously soft-deleted rows) so the unique
        // (internship_id, date) constraint never collides with leftover
        // rows from an earlier demo state, then regenerate cleanly.
        $internship->attendance()->withTrashed()->forceDelete();

        while ($written < $fullDays) {
            if ($date->isWeekend() || $date->gt($cap) || $date->gt($today)) {
                $date->addDay();

                continue;
            }

            $day = $date->toDateString();
            $writer->writeDay($internship, $day, $writer->dayTimes($internship->id, $day, false), $supervisor->id);
            $lastDate = $date->copy();
            $written++;
            $date->addDay();
        }

        if ($leftover > 0) {
            while ($date->isWeekend() || $date->gt($cap) || $date->gt($today)) {
                $date->addDay();
            }
            $day = $date->toDateString();
            // Controlled leftovers are 4 h (500 = 62 × 8 + 4; 300 = 37 × 8 + 4): one morning session.
            $writer->writeDay($internship, $day, $writer->dayTimes($internship->id, $day, true), $supervisor->id);
            $lastDate = $date->copy();
        }

        $internship->refreshTotalHours();

        return $lastDate->toDateString();
    }

    private function addWorkdays(Carbon $from, int $workdays): Carbon
    {
        $date = $from->copy();
        $count = 0;
        while ($count < $workdays) {
            $date->addDay();
            if (! $date->isWeekend()) {
                $count++;
            }
        }

        return $date;
    }

    // ─────────────────────────────────────────────────────────────────
    // Journals
    // ─────────────────────────────────────────────────────────────────

    /** @param 'all_approved'|'mixed' $mode */
    private function seedJournals(Internship $internship, int $facultyId, ?string $completedEndDate, string $mode): void
    {
        $windows = $this->weekResolver->weekWindows($internship);
        $today = Carbon::now('Asia/Manila')->toDateString();

        $activities = [
            ['activities' => 'Assisted in reviewing and updating internal system documentation for the client-facing web portal.', 'learnings' => 'Learned how to structure technical documentation so both developers and non-technical staff can follow it.', 'challenges' => 'Some legacy modules had no prior documentation, requiring code tracing before writing anything down.'],
            ['activities' => 'Performed data validation checks on migrated customer records against the source database.', 'learnings' => 'Gained hands-on experience with SQL queries used to spot mismatched or duplicate records.', 'challenges' => 'Encountered inconsistent date formats across regions that required a custom normalization script.'],
            ['activities' => 'Executed manual and scripted software testing on a new feature release before staging deployment.', 'learnings' => 'Understood the difference between smoke testing and full regression testing in a real release cycle.', 'challenges' => 'Reproducing an intermittent UI bug took several attempts across different browsers.'],
            ['activities' => 'Checked database integrity constraints and indexed slow-running queries flagged by the monitoring dashboard.', 'learnings' => 'Learned to read query execution plans to identify missing indexes.', 'challenges' => 'Balancing index additions against write-performance tradeoffs was new territory.'],
            ['activities' => 'Handled Tier-1 helpdesk tickets, troubleshooting login and VPN connectivity issues for end users.', 'learnings' => 'Improved communication skills explaining technical fixes to non-technical staff over chat and calls.', 'challenges' => 'A recurring VPN drop issue needed escalation after initial troubleshooting steps failed.'],
            ['activities' => 'Prepared a weekly status report summarizing ticket volume, resolution time, and open issues for the team lead.', 'learnings' => 'Learned how operational metrics are compiled and presented to stakeholders.', 'challenges' => 'Reconciling numbers from two different ticketing exports took longer than expected.'],
            ['activities' => 'Ran application testing on a mobile companion app ahead of its internal beta release.', 'learnings' => 'Practiced writing clear, reproducible bug reports with steps, expected result, and actual result.', 'challenges' => 'Device-specific rendering issues only appeared on older Android versions.'],
            ['activities' => 'Documented the onboarding process for new hires accessing internal systems, based on shadowing IT staff.', 'learnings' => 'Learned to write process documentation from an outside, first-time-user perspective.', 'challenges' => 'Some access-provisioning steps required approvals from teams not documented anywhere.'],
            ['activities' => 'Assisted with basic network troubleshooting for a branch office reporting slow intranet access.', 'learnings' => 'Learned to use ping and traceroute to isolate whether a slowdown was local or upstream.', 'challenges' => 'Diagnosing the issue remotely without physical access to the switch took extra coordination.'],
            ['activities' => 'Performed quality assurance checks on a batch reporting job, comparing output totals against expected control sums.', 'learnings' => 'Understood how control totals catch silent data-processing errors before they reach end users.', 'challenges' => 'One discrepancy traced back to a rounding difference between two currency fields.'],
            ['activities' => 'Provided user support during a system upgrade rollout, answering questions on the new interface.', 'learnings' => 'Learned to anticipate common user confusion points before a rollout, not just react to tickets.', 'challenges' => 'Managing the volume of simultaneous questions on rollout day required quick triage.'],
            ['activities' => 'Reconciled exported transaction data between the old and new systems following a database migration.', 'learnings' => 'Practiced writing reconciliation scripts that flag mismatches instead of manually eyeballing spreadsheets.', 'challenges' => 'A timezone offset in the legacy system caused false mismatches until identified.'],
            ['activities' => 'Compiled the final internship completion report and organized supporting attendance and evaluation records.', 'learnings' => 'Reflected on how the semester of small tasks combined into a full IT support workflow.', 'challenges' => 'Consolidating records from multiple weeks into one coherent report took careful cross-checking.'],
        ];

        $internship->loadMissing('student.studentProfile.program');
        if ($internship->student?->studentProfile?->program?->code === 'BSCS') {
            $activities = self::CS_JOURNAL_ACTIVITIES;
        }

        foreach ($windows as $window) {
            if ($window['start_date'] > $today) {
                continue;
            }

            // The current week has not ended: the real journal validator rejects
            // it (JournalPeriodValidator::MSG_FUTURE), so it stays unsubmitted.
            // Drop any unreviewed entry an earlier seeding produced for it.
            if ($window['end_date'] > $today) {
                // Hard delete: the (internship_id, week_number) unique index also
                // covers soft-deleted rows and would block next week's entry.
                JournalEntry::withTrashed()
                    ->where('internship_id', $internship->id)
                    ->where('week_number', $window['week_number'])
                    ->whereIn('status', ['draft', 'submitted'])
                    ->whereNull('faculty_reviewed_at')
                    ->forceDelete();

                continue;
            }

            $idx = ($window['week_number'] - 1) % count($activities);
            $content = $activities[$idx];

            $status = 'approved';
            $submittedAt = Carbon::parse($window['end_date'])->addDay();
            $reviewedAt = $submittedAt->copy()->addDay();

            if ($mode === 'mixed') {
                $isLatestOpenWeek = $window['end_date'] >= Carbon::now('Asia/Manila')->subDays(7)->toDateString();
                $status = $isLatestOpenWeek ? 'submitted' : 'approved';
            }

            $journal = JournalEntry::withTrashed()->updateOrCreate(
                ['internship_id' => $internship->id, 'week_number' => $window['week_number']],
                [
                    'entry_number' => $window['week_number'],
                    'date' => $window['start_date'],
                    'end_date' => $window['end_date'],
                    'activities_summary' => $content['activities'],
                    'learnings' => $content['learnings'],
                    'challenges' => $content['challenges'],
                    'status' => $status,
                    'submitted_at' => $submittedAt,
                    'submitted_late' => false,
                ]
            );
            if ($journal->trashed()) {
                $journal->restore();
            }

            if ($status === 'approved') {
                $journal->forceFill([
                    'faculty_feedback' => 'Good progress this week — keep documenting specific tools and outcomes.',
                    'faculty_reviewed_by' => $facultyId,
                    'faculty_reviewed_at' => $reviewedAt,
                ])->save();
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Requirements / documents
    // ─────────────────────────────────────────────────────────────────

    /** @param 'all_approved'|'mixed' $mode */
    private function seedRequirements(Internship $internship, User $student, string $mode, int $facultyId, float $approvalRatio = 1.0): void
    {
        $templates = $this->compliance->applicableTemplatesForStudent($student);
        $autoSatisfiedCodes = ['daily_time_record', 'performance_evaluation', 'host_evaluation', 'weekly_journal'];

        $manual = $templates->filter(function ($t) use ($autoSatisfiedCodes) {
            $code = $t->system_code ?: DocumentComplianceService::normalizeLabel($t->name);

            return ! in_array($code, $autoSatisfiedCodes, true);
        })->values();

        $count = $manual->count();
        $approvedCount = $mode === 'all_approved' ? $count : (int) floor($count * $approvalRatio);

        foreach ($manual as $i => $template) {
            $baseStatus = $i < $approvedCount ? 'approved' : (($i - $approvedCount) % 3 === 0 ? 'rejected' : 'pending_review');
            if ($mode === 'all_approved') {
                $baseStatus = 'approved';
            }

            // "Missing" requirements are represented by simply not creating a document.
            if ($mode === 'mixed' && $baseStatus === 'pending_review' && ($i % 4 === 3)) {
                Document::where('internship_id', $internship->id)->where('document_type', $template->name)->delete();

                continue;
            }

            Document::updateOrCreate(
                ['internship_id' => $internship->id, 'document_type' => $template->name],
                [
                    'status' => $baseStatus,
                    'current_stage' => $baseStatus === 'approved' ? 'approved' : 'coordinator',
                    'submitted_at' => now()->subDays(random_int(2, 20)),
                    'reviewed_by' => $baseStatus === 'approved' ? $facultyId : null,
                    'reviewed_at' => $baseStatus === 'approved' ? now()->subDays(random_int(1, 10)) : null,
                    'remarks' => $baseStatus === 'rejected' ? 'Please resubmit with the correct signatory.' : null,
                    'drive_link' => null,
                ]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Evaluations
    // ─────────────────────────────────────────────────────────────────

    /** @param 'steady'|'varied'|'strong' $profile FO-24 criteria on the 65–100 scale (x/10 → x·10) */
    private function seedEvaluations(Internship $internship, User $supervisor, User $student, int $facultyId, User $director, string $endDate, string $profile): void
    {
        $fo24Responses = match ($profile) {
            'varied' => ['c1' => 90, 'c2' => 100, 'c3' => 80, 'c4' => 90, 'c5' => 100, 'c6' => 80, 'c7' => 90, 'c8' => 100, 'c9' => 80, 'c10' => 90],
            // Mix of 8/10, 9/10 and 10/10, leaning high.
            'strong' => ['c1' => 100, 'c2' => 90, 'c3' => 100, 'c4' => 80, 'c5' => 90, 'c6' => 100, 'c7' => 90, 'c8' => 100, 'c9' => 90, 'c10' => 100],
            default => array_fill_keys(['c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8', 'c9', 'c10'], 90),
        };
        $varied = $profile !== 'steady';

        $this->upsertEvaluation($internship, 'FO-24', 'supervisor', $supervisor->id, $fo24Responses, $endDate, 'Consistently reliable and technically capable throughout the internship.', $facultyId);

        $fo03Responses = $varied
            ? ['q1' => 4, 'q2' => 5, 'q3' => 4, 'q4' => 5, 'q5' => 4]
            : array_fill_keys(['q1', 'q2', 'q3', 'q4', 'q5'], 5);
        $this->upsertEvaluation($internship, 'FO-03', 'supervisor', $supervisor->id, $fo03Responses, $endDate, 'Recommended for future hires from this program.', $director->id);

        $fo22Responses = $varied
            ? ['q1' => 4, 'q2' => 4, 'q3' => 5, 'q4' => 4]
            : array_fill_keys(['q1', 'q2', 'q3', 'q4'], 5);
        $this->upsertEvaluation($internship, 'FO-22', 'student', $student->id, $fo22Responses, $endDate, 'The host company provided real project exposure and mentorship.', null);

        $fo23Responses = $varied
            ? ['q1' => 4, 'q2' => 5, 'q3' => 4]
            : array_fill_keys(['q1', 'q2', 'q3'], 5);
        $this->upsertEvaluation($internship, 'FO-23', 'student', $student->id, $fo23Responses, $endDate, 'The practicum program adequately prepared me for this placement.', null);
    }

    private function upsertEvaluation(Internship $internship, string $formType, string $evaluatorType, int $evaluatedBy, array $responses, string $endDate, string $comments, ?int $releasedBy): void
    {
        $evaluation = Evaluation::updateOrCreate(
            [
                'internship_id' => $internship->id,
                'form_type' => $formType,
                'evaluator_type' => $evaluatorType,
                'evaluation_period' => 'Final',
            ],
            [
                'evaluated_by' => $evaluatedBy,
                'responses' => $responses,
                'general_comments' => $comments,
                'submitted_at' => Carbon::parse($endDate)->addDays(2),
                'signer_name' => null,
            ]
        );

        $evaluation->computeScores();

        if ($releasedBy) {
            $evaluation->released_to_student_at = Carbon::parse($endDate)->addDays(5);
            $evaluation->released_to_student_by = $releasedBy;
        }

        $evaluation->save();
    }

    // ─────────────────────────────────────────────────────────────────
    // Portfolio
    // ─────────────────────────────────────────────────────────────────

    private function seedPortfolio(Internship $internship, User $student, Company $company, bool $complete): void
    {
        $first = $student->studentProfile->first_name;
        $isCs = $student->studentProfile->program?->code === 'BSCS';
        $skills = $isCs
            ? 'software testing, API test automation, debugging, database maintenance, and code review'
            : 'documentation, testing, data validation, and helpdesk support';

        if ($complete) {
            StudentPortfolio::updateOrCreate(
                ['internship_id' => $internship->id],
                [
                    'user_id' => $student->id,
                    'company_name' => $company->company_name,
                    'company_address' => $company->address,
                    'company_vision' => "To be the technology partner of choice for organizations transforming their digital operations.",
                    'company_mission' => "To deliver reliable, innovative software and IT services that help clients and communities thrive.",
                    'company_history' => "{$company->company_name} has operated in the Philippines for over a decade, growing a local delivery team that supports global clients across software development, quality assurance, and IT support.",
                    'assessment_ethical' => "The organization maintained clear data-privacy and client-confidentiality practices, which {$first} was required to acknowledge and follow from day one.",
                    'assessment_learnings' => "{$first} developed practical skills in {$skills}, alongside stronger habits around time management and professional communication.",
                    'assessment_experience' => "The internship offered exposure to real production systems and cross-functional teams, giving {$first} a realistic view of day-to-day IT operations work.",
                    'assessment_standards' => "Work was reviewed against the company's existing QA and documentation standards, requiring {$first} to adapt to formal review cycles.",
                    'assessment_recommendations' => 'Future interns would benefit from an earlier walkthrough of the ticketing and version-control tools used by the team.',
                    'assessment_advice' => 'Ask questions early, keep a personal log of daily tasks, and treat every ticket as a chance to learn the underlying system.',
                ]
            );

            return;
        }

        StudentPortfolio::updateOrCreate(
            ['internship_id' => $internship->id],
            [
                'user_id' => $student->id,
                'company_name' => $company->company_name,
                'company_address' => $company->address,
                'company_vision' => "To be the technology partner of choice for organizations transforming their digital operations.",
                'company_mission' => "To deliver reliable, innovative software and IT services that help clients and communities thrive.",
                'company_history' => "{$company->company_name} has operated in the Philippines for over a decade, supporting global clients from a local delivery team.",
                'assessment_ethical' => null,
                'assessment_learnings' => "So far, {$first} has been building skills in {$skills}; a full reflection will be completed once the internship concludes.",
                'assessment_experience' => null,
                'assessment_standards' => null,
                'assessment_recommendations' => null,
                'assessment_advice' => null,
            ]
        );
    }

    private function printSummary(): void
    {
        $this->info('');
        $this->info('✅ CCS controlled demo dataset seeded (12 students).');
        $this->info('   BSIT finished: 2300592 Clarence Montealegre (Infor), 2300590 Angel Luis Taac-Taac (Accenture Philippines), 2300595 Arthur Morgan (Microsoft Philippines)');
        $this->info('   BSIT ongoing:  2300600 Christian Hero Aboy Valinado (Oracle, 240h), 2300609 Lara Croft (IBM, 184h), 2300610 Max Payne (DXC, 296h)');
        $this->info('   BSIT fresh:    2300500 Mark Joseph V. Taduran, 2300501 Ellie Williams, 2300502 Leon Kennedy');
        $this->info('   BSCS:          2300613 Terrence John Manlapaz (finished, Cognizant), 2300611 Ada Wong (ongoing, NTT DATA, 216h), 2300612 Nathan Drake (fresh)');
        $this->info('   Sections:      4IT-A/4IT-D/4CS-A → FAC-1001 Marvin Bicua; 4IT-B/4CS-B → COR-CCS-001 Arcelito Quiatchon');
    }
}
