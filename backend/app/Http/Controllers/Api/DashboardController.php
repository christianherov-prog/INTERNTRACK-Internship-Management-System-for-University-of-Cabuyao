<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Role-aware dashboard summary — reuses existing Internship / Company /
 * Document / Evaluation relationships (no parallel tables).
 */
class DashboardController extends Controller
{
    /** GET /api/v1/dashboard/summary */
    public function summary(Request $request)
    {
        $user = $request->user();

        $base = [
            'role'            => $user->role,
            'name'            => $user->profile_name,
            'username'        => $user->username,
            'role_label'      => $this->roleLabel($user->role),
            'current_term'    => config('interntrack.current_term', 'AY 2025-2026, Sem 2'),
            'security_status' => 'Standard',
            'last_login_at'   => optional($user->last_login_at)?->toIso8601String(),
        ];

        $payload = match ($user->role) {
            'director'    => $this->directorSummary($base),
            'coordinator' => $this->coordinatorSummary($user, $base),
            'faculty'     => $this->facultySummary($user, $base),
            'supervisor'  => $this->supervisorSummary($user, $base),
            'student'     => $this->studentSummary($user, $base),
            default       => null,
        };

        if ($payload === null) {
            return response()->json(['message' => 'Dashboard summary is not available for this role.'], 403);
        }

        return response()->json($payload);
    }

    private function directorSummary(array $base): array
    {
        // MOA items that need director attention (closest existing "approval" workflow).
        $pendingApprovals = Company::query()
            ->whereIn('moa_status', ['pending', 'for_renewal', 'on-process'])
            ->count();

        return array_merge($base, [
            'label'                => 'DIRECTOR DASHBOARD',
            'total_coordinators'   => User::where('role', 'coordinator')->where('is_active', true)->count(),
            'total_companies'      => Company::where('is_active', true)->count(),
            'total_active_interns' => Internship::whereIn('status', ['ongoing', 'active'])->count(),
            'pending_approvals'    => $pendingApprovals,
        ]);
    }

    private function coordinatorSummary(User $user, array $base): array
    {
        $assignedIds = $user->internshipsCoordinated()->pluck('id');

        $assignedStudents = $user->internshipsCoordinated()
            ->whereIn('status', ['pending_placement', 'placed', 'ongoing', 'active', 'for_evaluation'])
            ->count();

        $assignedCompanies = $user->internshipsCoordinated()
            ->whereNotNull('company_id')
            ->distinct()
            ->count('company_id');

        // Coordinators review submitted journals (not formal Evaluation rows).
        $pendingEvaluations = JournalEntry::query()
            ->whereIn('internship_id', $assignedIds)
            ->where('status', 'submitted')
            ->count();

        $pendingDocuments = Document::query()
            ->whereIn('internship_id', $assignedIds)
            ->where('status', 'pending_review')
            ->count();

        return array_merge($base, [
            'label'                     => 'COORDINATOR DASHBOARD',
            'assigned_students_count'   => $assignedStudents,
            'assigned_companies_count'  => $assignedCompanies,
            'pending_evaluations_count' => $pendingEvaluations,
            'pending_documents_count'   => $pendingDocuments,
        ]);
    }

    private function facultySummary(User $user, array $base): array
    {
        $internshipIds = $user->internshipsAdvised()
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation'])
            ->pluck('id');

        // Advisees still missing a faculty evaluation submission.
        $evaluatedIds = Evaluation::query()
            ->where('evaluator_type', 'faculty')
            ->whereIn('internship_id', $internshipIds)
            ->pluck('internship_id');

        $pendingJournals = JournalEntry::query()
            ->whereIn('internship_id', $internshipIds)
            ->where('status', 'submitted')
            ->count();

        return array_merge($base, [
            'label'                     => 'FACULTY DASHBOARD',
            'assigned_students_count'   => $internshipIds->count(),
            'pending_evaluations_count' => $internshipIds->diff($evaluatedIds)->count(),
            'pending_journals_count'    => $pendingJournals,
        ]);
    }

    private function supervisorSummary(User $user, array $base): array
    {
        $internships = Internship::where('supervisor_id', $user->id)
            ->whereIn('status', ['ongoing', 'active', 'for_evaluation', 'placed', 'completed', 'suspended', 'deferred'])
            ->with('company')
            ->get();

        $internshipIds = $internships->pluck('id');

        $pendingAttendance = \App\Models\AttendanceLog::whereIn('internship_id', $internshipIds)
            ->where('status', 'pending')
            ->count();

        $evaluatedIds = Evaluation::query()
            ->where('evaluator_type', 'supervisor')
            ->whereIn('internship_id', $internshipIds)
            ->pluck('internship_id');

        $companyName = $internships->pluck('company.company_name')->filter()->unique()->first();

        return array_merge($base, [
            'label'                     => 'SUPERVISOR DASHBOARD',
            'assigned_students_count'   => $internships->count(),
            'company_name'              => $companyName ?: '—',
            'pending_validations_count' => $pendingAttendance,
            'pending_evaluations_count' => $internshipIds->diff($evaluatedIds)->count(),
        ]);
    }

    private function studentSummary(User $user, array $base): array
    {
        $internship = $user->activeInternship()->with('company')->first();
        $profile = $user->studentProfile;

        return array_merge($base, [
            'label'           => 'INTERNSHIP DASHBOARD',
            'section'         => $profile?->section,
            'student_number'  => $user->username,
            'company_name'    => $internship?->company?->company_name,
            'internship_status' => $internship?->status,
        ]);
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'student'     => 'Student Account',
            'supervisor'  => 'Supervisor Account',
            'faculty'     => 'Faculty Account',
            'coordinator' => 'Coordinator Account',
            'director'    => 'Director Account',
            default       => ucfirst($role).' Account',
        };
    }
}
