<?php

namespace App\Console\Commands;

use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\Notification;
use App\Models\SupervisorInviteToken;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipProgressService;
use App\Services\ProgramRequirementService;
use App\Support\InternshipProvisioning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset one student to a clean pending-placement (fresh enrollee) internship state.
 * Preserves account, profile, program/section, and faculty mapping.
 */
class ResetFreshEnrolleeCommand extends Command
{
    protected $signature = 'interntrack:reset-fresh-enrollee
                            {student_number : Campus student number}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Reset a student to fresh-enrollee internship state (no company/supervisor/hours)';

    public function handle(FacultySectionAssignmentService $facultySections): int
    {
        $number = strtoupper(trim((string) $this->argument('student_number')));
        $dry = (bool) $this->option('dry-run');

        $user = User::query()
            ->with(['studentProfile.program.department'])
            ->where('role', 'student')
            ->where(function ($q) use ($number) {
                $q->where('student_number', $number)
                    ->orWhereHas('studentProfile', fn ($p) => $p->where('student_number', $number));
            })
            ->first();

        if (! $user || ! $user->studentProfile) {
            $this->error("Student {$number} not found.");

            return self::FAILURE;
        }

        $profile = $user->studentProfile;
        $profile->loadMissing('program.department');

        $faculty = $facultySections->resolveFacultyForProfile($profile)
            ?? ($user->internshipsAsStudent()->whereNotNull('faculty_id')->latest('id')->first()?->faculty);

        $coordId = $profile->coordinator_id
            ?? $user->internshipsAsStudent()->whereNotNull('coordinator_id')->latest('id')->value('coordinator_id');

        $programHours = (int) round(ProgramRequirementService::targetHoursForProfile($profile));
        if ($programHours <= 0) {
            $programHours = (int) config('interntrack.target_hours', 500);
        }

        $ay = $profile->school_year ?: '2025-2026';
        $sem = $profile->semester ?: '2nd Semester';

        $this->line('Student: '.trim(($profile->first_name ?? '').' '.($profile->middle_name ?? '').' '.($profile->last_name ?? '')));
        $this->line("Number: {$user->student_number}");
        $this->line('Program: '.($profile->program?->name ?? '-'));
        $this->line('Section: '.($profile->section ?? '-'));
        $this->line('Department: '.($profile->program?->department?->name ?? '-'));
        $this->line('Faculty: '.($faculty?->faculty_number ?? 'unresolved').' (id='.($faculty?->id ?? 'null').')');
        $this->line("Required hours: {$programHours}");
        $this->line($dry ? 'Mode: DRY-RUN' : 'Mode: WRITE');

        if ($dry) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($user, $profile, $faculty, $coordId, $programHours, $ay, $sem) {
            $internships = Internship::withTrashed()->where('student_id', $user->id)->orderByDesc('id')->get();

            foreach ($internships as $internship) {
                $internship->journals()->withTrashed()->forceDelete();
                $internship->attendance()->withTrashed()->forceDelete();
                $internship->documents()->withTrashed()->forceDelete();
                $internship->evaluations()->withTrashed()->forceDelete();
                $internship->overtimeEntries()->delete();
                $internship->correctionRequests()->delete();
                $internship->workSchedules()->delete();
                $internship->portfolio()->delete();
                $internship->forceFill(['current_placement_id' => null])->saveQuietly();
                if (Schema::hasTable('internship_placements')) {
                    $internship->placements()->delete();
                }
                if (Schema::hasTable('dtr_request_audits')) {
                    DB::table('dtr_request_audits')->where('internship_id', $internship->id)->delete();
                }
            }

            InternshipApplication::where('student_id', $user->id)->delete();
            HteRequest::where('student_id', $user->id)->delete();

            // Invalidate all invites for this student (do not leave approved/pending tokens usable).
            SupervisorInviteToken::where('student_id', $user->id)->each(function (SupervisorInviteToken $invite) {
                $invite->forceFill([
                    'status' => in_array($invite->status, ['approved', 'pending', 'pending_faculty', 'accepted'], true)
                        ? 'expired'
                        : $invite->status,
                    'supervisor_user_id' => null,
                ])->save();
            });
            SupervisorInviteToken::where('student_id', $user->id)
                ->whereIn('status', ['pending', 'pending_faculty', 'approved', 'accepted'])
                ->update(['status' => 'expired', 'supervisor_user_id' => null]);

            // Drop disposable internship workflow notifications only.
            if (Schema::hasTable('notifications')) {
                Notification::query()
                    ->where('user_id', $user->id)
                    ->where(function ($q) {
                        $q->where('type', 'like', '%attendance%')
                            ->orWhere('type', 'like', '%journal%')
                            ->orWhere('type', 'like', '%document%')
                            ->orWhere('type', 'like', '%supervisor%')
                            ->orWhere('type', 'like', '%placement%')
                            ->orWhere('type', 'like', '%hte%')
                            ->orWhere('type', 'like', '%evaluation%')
                            ->orWhere('type', 'like', '%company%')
                            ->orWhere('type', 'like', '%invite%')
                            ->orWhere('type', 'like', '%dtr%')
                            ->orWhere('type', 'like', '%schedule%');
                    })
                    ->delete();
            }

            $keep = $internships->first(fn ($row) => ! $row->trashed())
                ?? $internships->first();

            $pending = [
                'status' => 'pending_placement',
                'company_id' => null,
                'supervisor_id' => null,
                'current_placement_id' => null,
                'faculty_id' => $faculty?->id,
                'coordinator_id' => $coordId,
                'school_year' => $ay,
                'semester' => $sem,
                'term' => "AY {$ay}, {$sem}",
                'program' => $profile->program?->name,
                'target_hours' => $programHours,
                'total_hours_rendered' => 0,
                'start_date' => null,
                'end_date' => null,
                'expected_end_date' => null,
                'termination_reason' => null,
                'final_grade' => null,
                'final_remarks' => null,
                'status_reason' => null,
                'evaluation_period_status' => 'pending',
                'evaluation_period_approved_by' => null,
                'evaluation_period_approved_at' => null,
                'absorption_status' => null,
                'absorbed_at' => null,
                'job_title' => null,
                'absorption_notes' => null,
                'absorption_recorded_by' => null,
                'absorption_recorded_at' => null,
                'absorption_recorded_by_role' => null,
                'student_declared_hired' => false,
                'student_declared_at' => null,
                'student_declaration_notes' => null,
            ];

            if (Schema::hasColumn('internships', 'student_declaration_proofs')) {
                $pending['student_declaration_proofs'] = null;
            }

            if ($keep) {
                if ($keep->trashed()) {
                    $keep->restore();
                }
                foreach ($internships as $internship) {
                    if ((int) $internship->id === (int) $keep->id) {
                        continue;
                    }
                    if (! $internship->trashed()) {
                        $internship->forceFill([
                            'status' => 'cancelled',
                            'company_id' => null,
                            'supervisor_id' => null,
                            'current_placement_id' => null,
                            'total_hours_rendered' => 0,
                        ])->saveQuietly();
                    }
                }
                $keep->forceFill($pending)->save();
                InternshipProgressService::synchronize($keep->fresh());
                // Deliberate reset: lets interntrack:restore-evaluation-approvals tell
                // it apart from an approval lost outside the app.
                audit_log(null, 'evaluation_period_reset', ['internship_id' => $keep->id, 'source' => 'reset-fresh-enrollee']);
            } else {
                InternshipProvisioning::createPendingIfNone($user, $pending);
            }
        });

        $fresh = $user->fresh()->load(['studentProfile.program', 'activeInternship.company', 'activeInternship.supervisor']);
        $internship = $fresh->activeInternship;
        $snap = $internship ? InternshipProgressService::snapshot($internship) : null;

        $this->newLine();
        $this->info('Reset complete.');
        $this->line('Active status: '.($internship?->status ?? 'NONE'));
        $this->line('Company: '.($internship?->company_id ? 'SET' : 'NONE'));
        $this->line('Supervisor: '.($internship?->supervisor_id ? 'SET' : 'NONE'));
        $this->line('Hours: '.($snap['hours_rendered'] ?? 0).' / '.($snap['target_hours'] ?? $programHours));
        $this->line('Progress: '.($snap['progress_pct'] ?? 0).'%');
        $this->line('Faculty id: '.($internship?->faculty_id ?? 'null'));

        return self::SUCCESS;
    }
}
