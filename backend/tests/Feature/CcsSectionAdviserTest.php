<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FacultySectionAssignment;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;
use App\Support\PlacementEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SECTION-01..07, ADV-01..05, PLACE-ADV-01..05 and ROLE-ARC-01..07 against
 * the seeded controlled CCS dataset (interntrack_testing, never the dev DB).
 *
 * Rule under test: section assignment is the DEFAULT adviser; the actual
 * adviser on the Student's current internship is authoritative for rosters
 * and Faculty-only actions; a Student needs a valid adviser to apply.
 */
class CcsSectionAdviserTest extends TestCase
{
    use RefreshDatabase;

    private User $marvin;

    private User $arcelito;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');

        $this->marvin = User::where('faculty_number', 'FAC-1001')->firstOrFail();
        $this->arcelito = User::where('faculty_number', 'COR-CCS-001')->firstOrFail();
    }

    private function student(string $studentNumber): User
    {
        return User::findOrFail(StudentProfile::where('student_number', $studentNumber)->value('user_id'));
    }

    private function internship(string $studentNumber): Internship
    {
        return Internship::where('student_id', $this->student($studentNumber)->id)->orderByDesc('id')->firstOrFail();
    }

    /** @return list<string> student numbers on the logged-in Faculty workspace roster */
    private function facultyRoster(User $faculty): array
    {
        Sanctum::actingAs($faculty);
        $rows = $this->getJson('/api/v1/faculty/assigned-students?per_page=100')->assertOk()->json('data');

        return StudentProfile::whereIn('user_id', collect($rows)->pluck('user_id'))
            ->orderBy('student_number')->pluck('student_number')->all();
    }

    private function resolveSection(string $section, string $program): ?User
    {
        return app(FacultySectionAssignmentService::class)->suggestFacultyForSection($section, $program, '2025-2026', '2nd Semester');
    }

    // ── SECTION-01..05 ────────────────────────────────────────────────

    public function test_section_split_resolves_to_the_expected_faculty(): void
    {
        $it = 'Bachelor of Science in Information Technology';
        $cs = 'Bachelor of Science in Computer Science';

        $this->assertSame($this->marvin->id, $this->resolveSection('4IT-A', $it)?->id, 'SECTION-01');
        $this->assertSame($this->arcelito->id, $this->resolveSection('4IT-B', $it)?->id, 'SECTION-02');
        $this->assertSame($this->marvin->id, $this->resolveSection('4IT-D', $it)?->id, 'SECTION-03');
        $this->assertSame($this->marvin->id, $this->resolveSection('4CS-A', $cs)?->id, 'SECTION-04');
        $this->assertSame($this->arcelito->id, $this->resolveSection('4CS-B', $cs)?->id, 'SECTION-05');

        // Formatting variants resolve to the same row; no duplicate sections exist.
        $this->assertSame($this->arcelito->id, $this->resolveSection('4ITB', $it)?->id);
        foreach (['4ITA', '4ITB', '4ITD', '4CSA', '4CSB'] as $normalized) {
            $rows = FacultySectionAssignment::where('is_active', true)->get()
                ->filter(fn ($a) => FacultySectionAssignmentService::normalizeSection($a->section) === $normalized);
            $this->assertCount(1, $rows->pluck('faculty_user_id')->unique(), "{$normalized} must map to exactly one faculty");
        }
    }

    // ── SECTION-06 ────────────────────────────────────────────────────

    public function test_leon_kennedy_is_in_4itb_and_advised_by_arcelito(): void
    {
        $leon = $this->student('2300502');
        $this->assertSame('4ITB', $leon->studentProfile->section);
        $this->assertSame($this->arcelito->id, (int) $this->internship('2300502')->faculty_id);
        $this->assertSame(1, StudentProfile::where('student_number', '2300502')->count());

        $this->assertContains('2300502', $this->facultyRoster($this->arcelito));
        $this->assertNotContains('2300502', $this->facultyRoster($this->marvin));

        Sanctum::actingAs($leon);
        $this->getJson('/api/v1/student/applications')->assertOk()
            ->assertJsonPath('adviser.assigned', true)
            ->assertJsonPath('adviser.faculty_id', $this->arcelito->id)
            ->assertJsonPath('placement_lock.locked', false);
    }

    // ── SECTION-07 / ADV-05 ───────────────────────────────────────────

    public function test_new_student_gets_the_section_default_adviser(): void
    {
        $ccs = \App\Models\Department::where('code', 'CCS')->firstOrFail();
        $bscs = \App\Models\Program::where('code', 'BSCS')->firstOrFail();

        $newcomer = User::factory()->role('student')->create(['student_number' => '2399901']);
        StudentProfile::create([
            'user_id' => $newcomer->id,
            'student_number' => '2399901',
            'first_name' => 'Future',
            'last_name' => 'Enrollee',
            'section' => '4CSB',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'department_id' => $ccs->id,
            'program_id' => $bscs->id,
        ]);

        $internship = Internship::where('student_id', $newcomer->id)->firstOrFail();
        $this->assertSame($this->arcelito->id, (int) $internship->faculty_id, 'A 4CS-B enrollee defaults to Arcelito');
        $this->assertContains('2399901', $this->facultyRoster($this->arcelito));
    }

    // ── ADV-01..04 ────────────────────────────────────────────────────

    public function test_explicit_reassignment_persists_through_profile_saves_and_moves_faculty_scope(): void
    {
        $nathan = $this->student('2300612');
        $nathanInternship = $this->internship('2300612');
        $this->assertSame($this->marvin->id, (int) $nathanInternship->faculty_id, 'Section default before reassignment');

        // ADV-02: authorized reassignment — the Coordinator places Nathan and
        // explicitly chooses Arcelito as Faculty adviser (a section exception).
        $supervisor = User::where('faculty_number', 'SUP-CCSDEMO-NTTD')->firstOrFail();
        $company = Company::where('company_name', 'NTT DATA Philippines')->firstOrFail();
        Sanctum::actingAs($this->arcelito);
        $this->postJson("/api/v1/coordinator/internships/{$nathanInternship->id}/place", [
            'company_id' => $company->id,
            'supervisor_id' => $supervisor->id,
            'faculty_id' => $this->arcelito->id,
        ])->assertOk();
        $this->assertSame($this->arcelito->id, (int) $nathanInternship->fresh()->faculty_id);

        // ADV-01: the Student editing their profile does not revert it.
        Sanctum::actingAs($nathan);
        $this->putJson('/api/v1/auth/profile', ['contact_number' => '09175550123'])->assertOk();
        $nathan->studentProfile()->first()->update(['contact_number' => '09175550124']);
        $this->assertSame($this->arcelito->id, (int) $nathanInternship->fresh()->faculty_id, 'Profile save must not overwrite the adviser');

        // ADV-03: rosters follow the actual adviser.
        $this->assertContains('2300612', $this->facultyRoster($this->arcelito));
        $this->assertNotContains('2300612', $this->facultyRoster($this->marvin));

        // ADV-04: the previous Faculty loses Faculty-only access.
        Sanctum::actingAs($this->marvin);
        $this->getJson("/api/v1/faculty/students/{$nathan->id}/progress")->assertForbidden();
        Sanctum::actingAs($this->arcelito);
        $this->getJson("/api/v1/faculty/students/{$nathan->id}/progress")->assertOk();
    }

    public function test_section_transfer_moves_the_adviser_with_the_section(): void
    {
        // A registry (MISD) section change is an institutional transfer.
        $leon = $this->student('2300502');
        $leon->studentProfile()->first()->update(['section' => '4ITA']);

        $this->assertSame($this->marvin->id, (int) $this->internship('2300502')->faculty_id);
        $this->assertContains('2300502', $this->facultyRoster($this->marvin));
        $this->assertNotContains('2300502', $this->facultyRoster($this->arcelito));
    }

    public function test_remapping_a_section_moves_default_followers_but_not_deliberate_exceptions(): void
    {
        // Ellie (4ITB) is a deliberate exception advised by Marvin.
        $ellieInternship = $this->internship('2300501');
        $ellieInternship->forceFill(['faculty_id' => $this->marvin->id])->save();

        $assignment = FacultySectionAssignment::where('section', '4IT-B')->firstOrFail();
        $ana = User::where('faculty_number', 'FAC-1002')->firstOrFail();
        $assignment->update(['faculty_user_id' => $ana->id]);

        // Leon followed the old default (Arcelito) and moves; Ellie keeps Marvin.
        $this->assertSame($ana->id, (int) $this->internship('2300502')->faculty_id);
        $this->assertSame($this->marvin->id, (int) $ellieInternship->fresh()->faculty_id);
    }

    // ── PLACE-ADV-01..05 ──────────────────────────────────────────────

    public function test_student_without_adviser_cannot_apply_or_request_hte_until_one_is_assigned(): void
    {
        $nathan = $this->student('2300612');
        $company = Company::where('company_name', 'Infor')->firstOrFail();

        // A section with no Faculty mapping, and no adviser on the internship.
        $nathan->studentProfile()->first()->update(['section' => '4CSZ']);
        Internship::whereKey($this->internship('2300612')->id)->update(['faculty_id' => null]);

        Sanctum::actingAs($nathan);
        $this->getJson('/api/v1/student/applications')->assertOk()
            ->assertJsonPath('adviser.assigned', false)
            ->assertJsonPath('adviser.message', PlacementEligibility::ADVISER_MESSAGE);

        // PLACE-ADV-02 / PLACE-ADV-04: direct backend POST is rejected.
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])
            ->assertStatus(422)->assertJsonPath('message', PlacementEligibility::ADVISER_MESSAGE);
        // PLACE-ADV-03
        $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Brand New HTE Corp',
            'address' => 'Cabuyao, Laguna',
            'contact_person' => 'HR',
            'contact_email' => 'hr@brandnew.example',
            'contact_number' => '09171234567',
        ])->assertStatus(422)->assertJsonPath('message', PlacementEligibility::ADVISER_MESSAGE);
        $this->assertSame(0, InternshipApplication::where('student_id', $nathan->id)->count());
        $this->assertSame(0, HteRequest::where('student_id', $nathan->id)->count());

        // PLACE-ADV-05: MISD maps the section — no code change, Nathan becomes eligible.
        FacultySectionAssignment::create([
            'section' => '4CS-Z',
            'program' => 'Bachelor of Science in Computer Science',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'faculty_user_id' => $this->marvin->id,
            'is_active' => true,
        ]);
        $this->assertSame($this->marvin->id, (int) $this->internship('2300612')->faculty_id);

        // PLACE-ADV-01: a Student with an adviser applies normally.
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->assertDatabaseHas('internship_applications', ['student_id' => $nathan->id, 'company_id' => $company->id, 'status' => 'pending']);
    }

    // ── ROLE-ARC-01..07 ───────────────────────────────────────────────

    public function test_arcelito_uses_one_account_with_separate_coordinator_and_faculty_scopes(): void
    {
        // ROLE-ARC-01: one login.
        $this->assertSame(1, User::where('faculty_number', 'COR-CCS-001')->count());
        $this->assertSame('coordinator', $this->arcelito->role);
        $this->assertSame(1, \App\Models\FacultyProfile::where('last_name', 'Quiatchon')->count());

        // ROLE-ARC-03 / 04: Faculty workspace = only his actual advisees.
        $this->assertSame(['2300501', '2300502', '2300590', '2300609', '2300611'], $this->facultyRoster($this->arcelito));
        $this->assertSame(['2300500', '2300592', '2300595', '2300600', '2300610', '2300612', '2300613'], $this->facultyRoster($this->marvin));

        // ROLE-ARC-02 / 05: Coordinator workspace = all CCS Students.
        Sanctum::actingAs($this->arcelito);
        $records = $this->getJson('/api/v1/coordinator/records?per_page=100')->assertOk()->json('data');
        $ccsNumbers = collect($records)->map(fn ($r) => $r['student_profile']['student_number'] ?? null)->filter()->all();
        foreach (['2300500', '2300501', '2300502', '2300590', '2300592', '2300595', '2300600', '2300609', '2300610', '2300611', '2300612', '2300613'] as $num) {
            $this->assertContains($num, $ccsNumbers, "Coordinator workspace must include {$num}");
        }
    }

    public function test_coordinator_role_alone_does_not_grant_faculty_journal_review(): void
    {
        // ROLE-ARC-07: Ada is his advisee → allowed.
        $adaJournal = JournalEntry::where('internship_id', $this->internship('2300611')->id)
            ->where('status', 'submitted')->orderByDesc('week_number')->firstOrFail();
        Sanctum::actingAs($this->arcelito);
        $this->patchJson("/api/v1/faculty/journals/{$adaJournal->id}/review", [
            'action' => 'approved',
            'feedback' => 'Clear write-up of the database maintenance work.',
        ])->assertOk();
        $this->assertSame('approved', $adaJournal->fresh()->status);

        // ROLE-ARC-06: Terrence is Marvin's advisee → denied despite CCS-wide coordinator scope.
        $terrenceJournal = JournalEntry::where('internship_id', $this->internship('2300613')->id)->orderBy('week_number')->firstOrFail();
        $this->patchJson("/api/v1/faculty/journals/{$terrenceJournal->id}/review", [
            'action' => 'approved',
            'feedback' => 'Should not be allowed.',
        ])->assertNotFound();
        $this->getJson('/api/v1/faculty/students/'.$this->student('2300613')->id.'/progress')->assertForbidden();
    }
}
