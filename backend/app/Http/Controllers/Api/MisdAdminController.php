<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FacultySectionAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\FacultySectionAssignmentService;
use App\Services\MisdIntegrationService;
use App\Services\StaffAssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MisdAdminController extends Controller
{
    public function __construct(
        private StaffAssignmentService $staff,
        private MisdIntegrationService $misd,
        private FacultySectionAssignmentService $sectionService,
    ) {
    }

    // ─── Dashboard ────────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $counts = User::query()
            ->selectRaw('role, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $byRole = [];
        foreach (['student', 'faculty', 'coordinator', 'director', 'supervisor', 'admin'] as $role) {
            $row = $counts->get($role);
            $byRole[$role] = [
                'total'  => (int) ($row->total ?? 0),
                'active' => (int) ($row->active ?? 0),
            ];
        }

        $unmapped = $this->unmappedSectionsPayload();
        $recent = AuditLog::with('user:id,username,role')
            ->where(function ($q) {
                $q->where('action', 'like', 'staff.%')
                    ->orWhere('action', 'like', 'section.%')
                    ->orWhere('action', 'like', 'misd.%');
            })
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(fn (AuditLog $log) => $this->formatAudit($log));

        return response()->json([
            'users_by_role'     => $byRole,
            'unmapped_sections' => $unmapped,
            'unmapped_count'    => count($unmapped),
            'misd_status'       => $this->misd->status(),
            'recent_activity'   => $recent,
            'term'              => config('interntrack.current_term'),
        ]);
    }

    // ─── Directors / Coordinators ─────────────────────────────────────────────

    public function directors(): JsonResponse
    {
        return $this->listStaffRole('director');
    }

    public function coordinators(): JsonResponse
    {
        return $this->listStaffRole('coordinator');
    }

    public function assignDirector(Request $request): JsonResponse
    {
        return $this->assignStaff($request, 'director');
    }

    public function assignCoordinator(Request $request): JsonResponse
    {
        return $this->assignStaff($request, 'coordinator');
    }

    public function updateStaff(Request $request, int $id): JsonResponse
    {
        $user = User::with('facultyProfile')->findOrFail($id);

        if (!in_array($user->role, ['director', 'coordinator', 'faculty', 'admin'], true)) {
            return response()->json(['message' => 'Only staff accounts can be updated here.'], 422);
        }

        $data = $request->validate([
            'is_active'      => 'sometimes|boolean',
            'email'          => 'nullable|email|max:150',
            'first_name'     => 'nullable|string|max:100',
            'middle_name'    => 'nullable|string|max:100',
            'last_name'      => 'nullable|string|max:100',
            'contact_number' => 'nullable|string|max:30',
            'department'     => 'nullable|string|max:150',
            'college'        => 'nullable|string|max:150',
            'position'       => 'nullable|string|max:150',
        ]);

        if (array_key_exists('is_active', $data)) {
            try {
                $user = $this->staff->setActive($user, (bool) $data['is_active'], $request->user());
            } catch (ValidationException $e) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        }

        $profileFields = array_filter([
            'first_name'     => $data['first_name'] ?? null,
            'middle_name'    => $data['middle_name'] ?? null,
            'last_name'      => $data['last_name'] ?? null,
            'email'          => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
            'department'     => $data['department'] ?? null,
            'college'        => $data['college'] ?? null,
            'position'       => $data['position'] ?? null,
        ], fn ($v) => $v !== null);

        if ($profileFields) {
            if ($user->facultyProfile) {
                $user->facultyProfile->update($profileFields);
            }
            if (!empty($profileFields['email'])) {
                $user->update(['email' => $profileFields['email']]);
            }
        }

        return response()->json([
            'message' => 'Staff updated.',
            'staff'   => $this->staff->formatStaff($user->fresh('facultyProfile')),
        ]);
    }

    public function revokeStaff(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $mode = $request->input('mode', 'deactivate');

        try {
            $user = $this->staff->revoke($user, $request->user(), $mode);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => 'Staff revoked.',
            'staff'   => $this->staff->formatStaff($user),
        ]);
    }

    public function syncStaff(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user = $this->staff->syncFromMisd($user);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'misd.staff_synced',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old_values' => null,
            'new_values' => ['username' => $user->username],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Profile synced from MISD.',
            'staff'   => $this->staff->formatStaff($user),
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $this->staff->resetPassword($user, $request->user());

        return response()->json([
            'message' => 'Password reset to the system default.',
        ]);
    }

    // ─── Users ────────────────────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $q = User::with(['facultyProfile', 'studentProfile', 'supervisorProfile'])
            ->orderBy('role')
            ->orderBy('username');

        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }
        if ($request->filled('active')) {
            $q->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($inner) use ($search) {
                $inner->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facultyProfile', function ($fp) use ($search) {
                        $fp->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('studentProfile', function ($sp) use ($search) {
                        $sp->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_number', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $q->paginate((int) $request->query('per_page', 25));

        $paginator->getCollection()->transform(function (User $user) {
            $profile = $user->facultyProfile ?? $user->studentProfile ?? $user->supervisorProfile;
            $name = $profile
                ? trim("{$profile->first_name} {$profile->last_name}")
                : $user->username;

            return [
                'id'            => $user->id,
                'username'      => $user->username,
                'email'         => $user->email ?? $profile?->email,
                'role'          => $user->role,
                'is_active'     => (bool) $user->is_active,
                'name'          => $name,
                'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
                'created_at'    => optional($user->created_at)?->toIso8601String(),
            ];
        });

        return ApiResponse::list($paginator);
    }

    public function setUserActive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        $user = User::findOrFail($id);

        try {
            $user = $this->staff->setActive($user, (bool) $data['is_active'], $request->user());
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => $user->is_active ? 'User activated.' : 'User deactivated.',
            'user'    => [
                'id'        => $user->id,
                'username'  => $user->username,
                'role'      => $user->role,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }

    // ─── Section assignments ──────────────────────────────────────────────────

    public function sectionAssignments(Request $request): JsonResponse
    {
        $q = FacultySectionAssignment::with('faculty.facultyProfile')
            ->orderBy('academic_year', 'desc')
            ->orderBy('semester', 'desc')
            ->orderBy('section');

        if ($request->filled('academic_year')) {
            $q->where('academic_year', $request->query('academic_year'));
        }
        if ($request->filled('semester')) {
            $q->where('semester', (int) $request->query('semester'));
        }
        if ($request->filled('section')) {
            $q->where('section', FacultySectionAssignmentService::normalizeSection($request->query('section')));
        }
        if ($request->filled('active')) {
            $q->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $rows = $q->get()->map(fn (FacultySectionAssignment $a) => $this->formatSectionAssignment($a));

        return ApiResponse::list($rows);
    }

    public function storeSectionAssignment(Request $request): JsonResponse
    {
        $data = $this->validateSectionPayload($request);
        $section = FacultySectionAssignmentService::normalizeSection($data['section']);

        $exists = FacultySectionAssignment::where('program', $data['program'] ?? null)
            ->where('section', $section)
            ->where('academic_year', $data['academic_year'])
            ->where('semester', $data['semester'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'section' => 'A mapping already exists for this program/section/term.',
            ]);
        }

        $faculty = User::where('id', $data['faculty_user_id'])->where('role', 'faculty')->first();
        if (!$faculty) {
            throw ValidationException::withMessages(['faculty_user_id' => 'Faculty user not found.']);
        }

        $assignment = FacultySectionAssignment::create([
            'program'         => $data['program'] ?? null,
            'section'         => $section,
            'academic_year'   => $data['academic_year'],
            'semester'        => $data['semester'],
            'faculty_user_id' => $faculty->id,
            'is_active'       => $data['is_active'] ?? true,
        ]);

        $this->auditSection($request->user(), 'section.created', $assignment);

        return response()->json([
            'message'    => 'Section mapping created.',
            'assignment' => $this->formatSectionAssignment($assignment->load('faculty.facultyProfile')),
        ], 201);
    }

    public function updateSectionAssignment(Request $request, int $id): JsonResponse
    {
        $assignment = FacultySectionAssignment::findOrFail($id);
        $data = $this->validateSectionPayload($request, true);

        if (isset($data['section'])) {
            $data['section'] = FacultySectionAssignmentService::normalizeSection($data['section']);
        }
        if (isset($data['faculty_user_id'])) {
            $faculty = User::where('id', $data['faculty_user_id'])->where('role', 'faculty')->first();
            if (!$faculty) {
                throw ValidationException::withMessages(['faculty_user_id' => 'Faculty user not found.']);
            }
        }

        $assignment->update(array_filter([
            'program'         => $data['program'] ?? null,
            'section'         => $data['section'] ?? null,
            'academic_year'   => $data['academic_year'] ?? null,
            'semester'        => $data['semester'] ?? null,
            'faculty_user_id' => $data['faculty_user_id'] ?? null,
            'is_active'       => array_key_exists('is_active', $data) ? $data['is_active'] : null,
        ], fn ($v) => $v !== null));

        $this->auditSection($request->user(), 'section.updated', $assignment);

        return response()->json([
            'message'    => 'Section mapping updated.',
            'assignment' => $this->formatSectionAssignment($assignment->fresh('faculty.facultyProfile')),
        ]);
    }

    public function destroySectionAssignment(Request $request, int $id): JsonResponse
    {
        $assignment = FacultySectionAssignment::findOrFail($id);
        $this->auditSection($request->user(), 'section.deleted', $assignment, [
            'section' => $assignment->section,
            'program' => $assignment->program,
        ]);
        $assignment->delete();

        return response()->json(['message' => 'Section mapping deleted.']);
    }

    public function facultyOptions(): JsonResponse
    {
        $faculty = User::with('facultyProfile')
            ->where('role', 'faculty')
            ->where('is_active', true)
            ->orderBy('username')
            ->get()
            ->map(function (User $u) {
                $fp = $u->facultyProfile;
                return [
                    'id'              => $u->id,
                    'username'        => $u->username,
                    'name'            => $fp ? trim("{$fp->first_name} {$fp->last_name}") : $u->username,
                    'employee_number' => $fp?->employee_number ?? $u->username,
                ];
            });

        return ApiResponse::list($faculty);
    }

    // ─── MISD lookup / sync / monitoring ──────────────────────────────────────

    public function previewFaculty(string $employeeNumber): JsonResponse
    {
        $employeeNumber = strtoupper(trim($employeeNumber));
        $this->misd->forgetFacultyCache($employeeNumber);
        $data = $this->misd->fetchFaculty($employeeNumber);

        if (empty($data)) {
            return response()->json(['message' => 'No MISD record found.', 'found' => false], 404);
        }

        $existing = User::where('username', $employeeNumber)->first();

        return response()->json([
            'found'    => true,
            'misd'     => $data,
            'existing' => $existing ? $this->staff->formatStaff($existing->load('facultyProfile')) : null,
        ]);
    }

    public function previewStudent(string $studentNumber): JsonResponse
    {
        $studentNumber = strtoupper(trim($studentNumber));
        $this->misd->forgetStudentCache($studentNumber);
        $data = $this->misd->fetchStudent($studentNumber);

        if (empty($data)) {
            return response()->json(['message' => 'No MISD record found.', 'found' => false], 404);
        }

        $existing = User::where('username', $studentNumber)->with('studentProfile')->first();
        $local = $existing?->studentProfile;

        return response()->json([
            'found'  => true,
            'misd'   => $data,
            'local'  => $local ? [
                'id'            => $existing->id,
                'username'      => $existing->username,
                'section'       => $local->section,
                'program'       => $local->program,
                'academic_year' => $local->academic_year,
                'semester'      => $local->semester,
                'synced_at'     => optional($local->synced_at)?->toIso8601String(),
            ] : null,
            'drift'  => $local ? [
                'section_changed' => ($local->section !== ($data['section'] ?? null)),
                'program_changed' => ($local->program !== ($data['program'] ?? null)),
            ] : null,
        ]);
    }

    public function misdStatus(): JsonResponse
    {
        return response()->json($this->misd->status());
    }

    public function syncStudent(Request $request, int $id): JsonResponse
    {
        $user = User::with('studentProfile')->findOrFail($id);
        if ($user->role !== 'student') {
            return response()->json(['message' => 'User is not a student.'], 422);
        }

        $before = $user->studentProfile?->only(['section', 'program', 'academic_year', 'semester']);
        $this->misd->syncStudent($user);
        $user->refresh()->load('studentProfile');
        $after = $user->studentProfile?->only(['section', 'program', 'academic_year', 'semester']);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'misd.student_synced',
            'model_type' => User::class,
            'model_id'   => $user->id,
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Student synced from MISD.',
            'student' => [
                'id'            => $user->id,
                'username'      => $user->username,
                'section'       => $user->studentProfile?->section,
                'program'       => $user->studentProfile?->program,
                'academic_year' => $user->studentProfile?->academic_year,
                'semester'      => $user->studentProfile?->semester,
                'synced_at'     => optional($user->studentProfile?->synced_at)?->toIso8601String(),
            ],
            'changed' => $before !== $after,
        ]);
    }

    public function syncDirectory(Request $request): JsonResponse
    {
        $type = $request->validate(['type' => 'required|in:students,faculty'])['type'];
        $list = $type === 'students' ? $this->misd->listStudents() : $this->misd->listFaculty();

        return response()->json([
            'type'  => $type,
            'count' => count($list),
            'data'  => $list,
        ]);
    }

    public function unmappedSections(): JsonResponse
    {
        return response()->json(['data' => $this->unmappedSectionsPayload()]);
    }

    public function auditLog(Request $request): JsonResponse
    {
        $q = AuditLog::with('user:id,username,role')
            ->where(function ($inner) {
                $inner->where('action', 'like', 'staff.%')
                    ->orWhere('action', 'like', 'section.%')
                    ->orWhere('action', 'like', 'misd.%');
            })
            ->latest('created_at');

        $paginator = $q->paginate((int) $request->query('per_page', 30));
        $paginator->getCollection()->transform(fn (AuditLog $log) => $this->formatAudit($log));

        return ApiResponse::list($paginator);
    }

    public function provisioningLog(): JsonResponse
    {
        $path = storage_path('logs/laravel.log');
        $entries = [];

        if (File::exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            $lines = array_slice($lines, -400);
            foreach (array_reverse($lines) as $line) {
                if (stripos($line, 'MISD') === false && stripos($line, 'provision') === false) {
                    continue;
                }
                $entries[] = $line;
                if (count($entries) >= 40) {
                    break;
                }
            }
        }

        return response()->json(['data' => $entries]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function listStaffRole(string $role): JsonResponse
    {
        $rows = User::with('facultyProfile')
            ->where('role', $role)
            ->orderByDesc('is_active')
            ->orderBy('username')
            ->get()
            ->map(fn (User $u) => $this->staff->formatStaff($u));

        return ApiResponse::list($rows);
    }

    private function assignStaff(Request $request, string $role): JsonResponse
    {
        $data = $request->validate([
            'employee_number' => 'required|string|max:50',
            'email'           => 'nullable|email|max:150',
            'first_name'      => 'nullable|string|max:100',
            'middle_name'     => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'contact_number'  => 'nullable|string|max:30',
            'department'      => 'nullable|string|max:150',
            'college'         => 'nullable|string|max:150',
            'position'        => 'nullable|string|max:150',
        ]);

        try {
            $user = $this->staff->assign(
                $data['employee_number'],
                $role,
                $request->user(),
                $data
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'message' => ucfirst($role) . ' assigned successfully.',
            'staff'   => $this->staff->formatStaff($user),
        ], 201);
    }

    private function validateSectionPayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'program'         => 'nullable|string|max:150',
            'section'         => "{$required}|string|max:20",
            'academic_year'   => "{$required}|string|max:20",
            'semester'        => ["{$required}", 'integer', Rule::in([1, 2])],
            'faculty_user_id' => "{$required}|integer|exists:users,id",
            'is_active'       => 'sometimes|boolean',
        ]);
    }

    private function formatSectionAssignment(FacultySectionAssignment $a): array
    {
        $faculty = $a->faculty;
        $fp = $faculty?->facultyProfile;

        return [
            'id'              => $a->id,
            'program'         => $a->program,
            'section'         => $a->section,
            'academic_year'   => $a->academic_year,
            'semester'        => $a->semester,
            'is_active'       => (bool) $a->is_active,
            'faculty_user_id' => $a->faculty_user_id,
            'faculty'         => $faculty ? [
                'id'              => $faculty->id,
                'username'        => $faculty->username,
                'name'            => $fp ? trim("{$fp->first_name} {$fp->last_name}") : $faculty->username,
                'employee_number' => $fp?->employee_number,
            ] : null,
            'created_at'      => optional($a->created_at)?->toIso8601String(),
            'updated_at'      => optional($a->updated_at)?->toIso8601String(),
        ];
    }

    private function unmappedSectionsPayload(): array
    {
        $profiles = StudentProfile::query()
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->get(['id', 'user_id', 'section', 'program', 'course_name', 'academic_year', 'semester']);

        $grouped = [];

        foreach ($profiles as $profile) {
            if ($this->sectionService->resolveFacultyForProfile($profile)) {
                continue;
            }

            $section = FacultySectionAssignmentService::normalizeSection($profile->section);
            $key = implode('|', [
                $profile->program ?: $profile->course_name ?: '',
                $section,
                $profile->academic_year ?: '',
                (string) ($profile->semester ?: ''),
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'section'       => $section,
                    'program'       => $profile->program ?: $profile->course_name,
                    'academic_year' => $profile->academic_year,
                    'semester'      => $profile->semester,
                    'student_count' => 0,
                ];
            }

            $grouped[$key]['student_count']++;
        }

        return array_values($grouped);
    }

    private function auditSection(User $actor, string $action, FacultySectionAssignment $assignment, ?array $extra = null): void
    {
        AuditLog::create([
            'user_id'    => $actor->id,
            'action'     => $action,
            'model_type' => FacultySectionAssignment::class,
            'model_id'   => $assignment->id,
            'old_values' => $extra,
            'new_values' => [
                'section'         => $assignment->section,
                'program'         => $assignment->program,
                'academic_year'   => $assignment->academic_year,
                'semester'        => $assignment->semester,
                'faculty_user_id' => $assignment->faculty_user_id,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    private function formatAudit(AuditLog $log): array
    {
        return [
            'id'         => $log->id,
            'action'     => $log->action,
            'model_type' => class_basename((string) $log->model_type),
            'model_id'   => $log->model_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'actor'      => $log->user ? [
                'id'       => $log->user->id,
                'username' => $log->user->username,
                'role'     => $log->user->role,
            ] : null,
            'created_at' => optional($log->created_at)?->toIso8601String(),
        ];
    }
}
