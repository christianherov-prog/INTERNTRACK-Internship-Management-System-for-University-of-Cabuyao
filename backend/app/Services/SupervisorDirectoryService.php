<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\SupervisorInviteToken;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\DepartmentScope;
use App\Support\NameParts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Scoped HTE supervisor directory for faculty / coordinator / director portals.
 * Never includes password hashes or other credentials.
 */
class SupervisorDirectoryService
{
    public const SCOPE_FACULTY = 'faculty';

    public const SCOPE_COORDINATOR = 'coordinator';

    public const SCOPE_DIRECTOR = 'director';

    public function listFor(User $actor, string $scope): JsonResponse
    {
        $supervisors = $this->scopedSupervisorsQuery($actor, $scope)
            ->with(['supervisorProfile.company'])
            ->orderBy('faculty_number')
            ->orderBy('id')
            ->get();

        return ApiResponse::list($this->transformMany($supervisors, $actor, $scope));
    }

    public function showFor(User $actor, string $scope, int $supervisorId): JsonResponse
    {
        $supervisor = $this->scopedSupervisorsQuery($actor, $scope)
            ->with(['supervisorProfile.company'])
            ->whereKey($supervisorId)
            ->first();

        if (! $supervisor) {
            if (User::query()->where('role', 'supervisor')->whereKey($supervisorId)->exists()) {
                DepartmentScope::abortDifferentDepartment();
            }

            abort(404, 'Supervisor not found.');
        }

        return response()->json([
            'data' => $this->transformOne($supervisor, $actor, $scope),
        ]);
    }

    private function scopedSupervisorsQuery(User $actor, string $scope): Builder
    {
        $supervisorIds = (clone $this->scopedInternshipsQuery($actor, $scope))
            ->whereNotNull('supervisor_id')
            ->distinct()
            ->pluck('supervisor_id');

        return User::query()
            ->where('role', 'supervisor')
            ->whereIn('id', $supervisorIds);
    }

    private function scopedInternshipsQuery(User $actor, string $scope): Builder
    {
        $query = Internship::query();

        return match ($scope) {
            self::SCOPE_DIRECTOR => $query,
            self::SCOPE_FACULTY => $query->whereIn(
                'student_id',
                FacultySectionAssignmentService::assignedStudentsQuery($actor, false)->pluck('id')
            ),
            self::SCOPE_COORDINATOR => $this->constrainCoordinatorInternships($query, $actor),
            default => throw new InvalidArgumentException("Unknown supervisor directory scope: {$scope}"),
        };
    }

    private function constrainCoordinatorInternships(Builder $query, User $actor): Builder
    {
        $deptId = DepartmentScope::departmentIdFor($actor);
        if (! $deptId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('student.studentProfile', function ($q) use ($deptId) {
            DepartmentScope::constrainStudentProfiles($q, $deptId);
        });
    }

    /**
     * @param  Collection<int, User>  $supervisors
     * @return Collection<int, array<string, mixed>>
     */
    private function transformMany(Collection $supervisors, User $actor, string $scope): Collection
    {
        if ($supervisors->isEmpty()) {
            return collect();
        }

        $supervisorIds = $supervisors->pluck('id');
        $internships = $this->scopedInternshipsQuery($actor, $scope)
            ->whereIn('supervisor_id', $supervisorIds)
            ->with([
                'student.studentProfile.program',
                'company',
            ])
            ->get()
            ->groupBy('supervisor_id');

        $approvals = $this->latestApprovalsBySupervisor($supervisorIds);

        return $supervisors->map(function (User $supervisor) use ($internships, $approvals) {
            return $this->formatSupervisor(
                $supervisor,
                $internships->get($supervisor->id, collect()),
                $approvals->get($supervisor->id)
            );
        })->values();
    }

    private function transformOne(User $supervisor, User $actor, string $scope): array
    {
        $internships = $this->scopedInternshipsQuery($actor, $scope)
            ->where('supervisor_id', $supervisor->id)
            ->with([
                'student.studentProfile.program',
                'company',
            ])
            ->get();

        $approvals = $this->latestApprovalsBySupervisor(collect([$supervisor->id]));

        return $this->formatSupervisor(
            $supervisor,
            $internships,
            $approvals->get($supervisor->id)
        );
    }

    /**
     * @param  Collection<int, Internship>  $internships
     * @return array<string, mixed>
     */
    private function formatSupervisor(User $supervisor, Collection $internships, ?SupervisorInviteToken $latestInvite): array
    {
        $profile = $supervisor->supervisorProfile;
        $name = $profile?->full_name ?: NameParts::fromProfile($profile);
        if ($name === '') {
            $name = $supervisor->username ?? '—';
        }

        $company = $profile?->company;
        $companyName = $company?->company_name;
        if (! $companyName) {
            $companyName = $internships
                ->map(fn (Internship $i) => $i->company?->company_name)
                ->filter()
                ->unique()
                ->values()
                ->first();
        }

        $assignedStudents = $internships
            ->filter(fn (Internship $i) => $i->student)
            ->map(function (Internship $internship) {
                $student = $internship->student;
                $profile = $student->studentProfile;
                $profile?->loadMissing('program');
                $progress = InternshipProgressService::snapshot($internship);
                $programName = is_string($internship->program) && $internship->program !== ''
                    ? $internship->program
                    : ($profile?->getRelation('program')?->name ?? '—');
                $studentName = $profile
                    ? (NameParts::fromProfile($profile) ?: trim("{$profile->last_name}, {$profile->first_name}"))
                    : ($student->username ?? '—');

                return [
                    'id' => $student->id,
                    'name' => $studentName !== '' ? $studentName : '—',
                    'student_number' => $student->student_number ?? $profile?->student_number,
                    'program' => $programName,
                    'status' => $progress['status'] ?? $internship->status,
                    'company' => $internship->company?->company_name,
                    'hours_rendered' => $progress['hours_rendered'] ?? (float) ($internship->total_hours_rendered ?? 0),
                    'target_hours' => $progress['target_hours'] ?? (float) ($internship->target_hours ?? 0),
                    'internship_id' => $internship->id,
                ];
            })
            ->unique('id')
            ->values()
            ->all();

        $approvalStatus = null;
        if ($latestInvite) {
            $approvalStatus = $latestInvite->status;
        } elseif ($supervisor->is_active) {
            $approvalStatus = 'approved';
        }

        return [
            'id' => $supervisor->id,
            'name' => $name,
            'faculty_number' => $supervisor->faculty_number,
            'login_username' => $supervisor->attributes['login_username'] ?? null,
            'email' => $profile?->email ?? $supervisor->email,
            'contact_number' => $profile?->contact_number,
            'position' => $profile?->position,
            'company' => $companyName,
            'company_id' => $profile?->company_id ?? $company?->id,
            'is_active' => (bool) $supervisor->is_active,
            'approval_status' => $approvalStatus,
            'reviewed_at' => $latestInvite?->reviewed_at?->toDateTimeString(),
            'review_remarks' => $latestInvite?->review_remarks,
            'assigned_students_count' => count($assignedStudents),
            'assigned_students' => $assignedStudents,
        ];
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $supervisorIds
     * @return Collection<int, SupervisorInviteToken>
     */
    private function latestApprovalsBySupervisor($supervisorIds): Collection
    {
        $ids = collect($supervisorIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return SupervisorInviteToken::query()
            ->whereIn('supervisor_user_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->unique('supervisor_user_id')
            ->keyBy('supervisor_user_id');
    }
}
