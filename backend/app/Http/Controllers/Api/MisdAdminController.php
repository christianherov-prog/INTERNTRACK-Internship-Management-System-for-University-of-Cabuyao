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
        $recent = [];
        try {
            $recent = AuditLog::with('user:id,student_number,faculty_number,email,role')
                ->where(function ($q) {
                    $q->where('action', 'like', 'staff.%')
                        ->orWhere('action', 'like', 'section.%')
                        ->orWhere('action', 'like', 'misd.%');
                })
                ->latest('created_at')
                ->limit(12)
                ->get()
                ->map(fn (AuditLog $log) => $this->formatAudit($log));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Dashboard recent activity notice: ' . $e->getMessage());
        }

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
            'position'       => $data['position'] ?? null,
        ], fn ($v) => $v !== null);

        if (!empty($data['department'])) {
            $deptStr = $data['department'];
            $deptId = \App\Models\Department::where('code', $deptStr)
                ->orWhere('name', $deptStr)
                ->orWhere('code', 'CCS')
                ->value('id') ?? \App\Models\Department::first()?->id;
            if ($deptId) {
                $profileFields['department_id'] = $deptId;
            }
        }

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
        $q = User::with(['facultyProfile', 'studentProfile.program', 'supervisorProfile'])
            ->orderBy('id', 'desc');

        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }
        if ($request->filled('active')) {
            $q->where('is_active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($inner) use ($search) {
                $inner->where(function ($q2) use ($search) {
                        $q2->where('student_number', 'like', "%{$search}%")
                           ->orWhere('faculty_number', 'like', "%{$search}%");
                    })
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('facultyProfile', function ($fp) use ($search) {
                        $fp->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('faculty_number', 'like', "%{$search}%");
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
            ->orderBy('school_year', 'desc')
            ->orderBy('semester', 'desc')
            ->orderBy('section');

        if ($request->filled('school_year')) {
            $q->where('school_year', $request->query('school_year'));
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
            ->where('school_year', $data['school_year'])
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
            'school_year'   => $data['school_year'],
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
            'school_year'   => $data['school_year'] ?? null,
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
            ->orderBy('faculty_number')
            ->get()
            ->map(function (User $u) {
                $fp = $u->facultyProfile;
                return [
                    'id'              => $u->id,
                    'username'        => $u->username,
                    'name'            => $fp ? trim("{$fp->first_name} {$fp->last_name}") : $u->username,
                    'faculty_number'  => $fp?->faculty_number ?? $u->username,
                ];
            });

        return ApiResponse::list($faculty);
    }

    // ─── MISD lookup / sync / monitoring ──────────────────────────────────────

    public function previewFaculty(string $facultyNumber): JsonResponse
    {
        $facultyNumber = strtoupper(trim($facultyNumber));
        $this->misd->forgetFacultyCache($facultyNumber);
        $data = $this->misd->fetchFaculty($facultyNumber);

        if (empty($data)) {
            return response()->json(['message' => 'No MISD record found.', 'found' => false], 404);
        }

        $existing = User::where('faculty_number', $facultyNumber)->first();

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

        $existing = User::where('student_number', $studentNumber)->with('studentProfile.program')->first();
        $local = $existing?->studentProfile;

        return response()->json([
            'found'  => true,
            'misd'   => $data,
            'local'  => $local ? [
                'id'            => $existing->id,
                'username'      => $existing->username,
                'section'       => $local->section,
                'program'       => $local->program,
                'school_year' => $local->school_year,
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
        $user = User::with('studentProfile.program')->findOrFail($id);
        if ($user->role !== 'student') {
            return response()->json(['message' => 'User is not a student.'], 422);
        }

        $before = $user->studentProfile?->only(['section', 'program', 'school_year', 'semester']);
        $this->misd->syncStudent($user);
        $user->refresh()->load('studentProfile.program');
        $after = $user->studentProfile?->only(['section', 'program', 'school_year', 'semester']);

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
                'program'       => $user->studentProfile?->program?->name,
                'school_year'   => $user->studentProfile?->school_year,
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

        try {
            AuditLog::create([
                'user_id'    => $request->user()?->id,
                'action'     => 'misd.directory_fetched',
                'model_type' => User::class,
                'model_id'   => $request->user()?->id ?? 0,
                'old_values' => null,
                'new_values' => ['type' => $type, 'count' => count($list)],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Directory fetch audit notice: ' . $e->getMessage());
        }

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
        try {
            $q = AuditLog::with('user:id,student_number,faculty_number,email,role')
                ->where(function ($inner) {
                    $inner->where('action', 'like', 'staff.%')
                        ->orWhere('action', 'like', 'section.%')
                        ->orWhere('action', 'like', 'misd.%');
                })
                ->latest('created_at');

            $paginator = $q->paginate((int) $request->query('per_page', 30));
            $paginator->getCollection()->transform(fn (AuditLog $log) => $this->formatAudit($log));

            return ApiResponse::list($paginator);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AuditLog query notice: ' . $e->getMessage());
            return ApiResponse::list([]);
        }
    }

    public function provisioningLog(): JsonResponse
    {
        $entries = [];

        try {
            $logs = AuditLog::with('user:id,student_number,faculty_number,email,role')
                ->where(function ($q) {
                    $q->where('action', 'like', 'misd.%')
                        ->orWhere('action', 'like', 'staff.%')
                        ->orWhere('action', 'like', 'section.%');
                })
                ->latest('created_at')
                ->limit(50)
                ->get();

            foreach ($logs as $log) {
                $time = optional($log->created_at)->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
                $actor = $log->user ? $log->user->username : 'System Admin';

                switch ($log->action) {
                    case 'misd.student_synced':
                        $sec = $log->new_values['section'] ?? 'N/A';
                        $term = trim(($log->new_values['school_year'] ?? '') . ' ' . ($log->new_values['semester'] ?? ''));
                        $entries[] = "[{$time}] [MISD-SYNC] Student ID: {$log->model_id} enrollment profile synchronized from MISD (Section: {$sec}, Term: {$term}) by {$actor}.";
                        break;
                    case 'misd.directory_fetched':
                        $type = $log->new_values['type'] ?? 'records';
                        $count = $log->new_values['count'] ?? 0;
                        $entries[] = "[{$time}] [MISD-DIRECTORY] Successfully queried {$count} {$type} from mock MISD directory repository.";
                        break;
                    case 'staff.created':
                    case 'staff.synced':
                        $role = ucfirst($log->new_values['role'] ?? 'staff');
                        $emp = $log->new_values['faculty_number'] ?? $log->new_values['username'] ?? "ID: {$log->model_id}";
                        $entries[] = "[{$time}] [STAFF-PROVISION] {$role} account provisioned/synchronized for employee {$emp}.";
                        break;
                    case 'staff.revoked':
                        $entries[] = "[{$time}] [STAFF-REVOCATION] Staff privileges revoked for account ID: {$log->model_id} by {$actor}.";
                        break;
                    case 'section.created':
                        $sec = $log->new_values['section'] ?? 'section';
                        $entries[] = "[{$time}] [SECTION-MAPPING] Faculty assignment mapped for section {$sec}.";
                        break;
                    case 'section.updated':
                        $sec = $log->new_values['section'] ?? 'section';
                        $entries[] = "[{$time}] [SECTION-MAPPING] Section mapping updated for section {$sec}.";
                        break;
                    case 'section.deleted':
                        $sec = $log->old_values['section'] ?? 'section';
                        $entries[] = "[{$time}] [SECTION-MAPPING] Section mapping deleted for section {$sec} (assignment ID: {$log->model_id}).";
                        break;
                    default:
                        $entries[] = "[{$time}] [AUDIT-EVENT] {$log->action} executed on " . class_basename((string) $log->model_type) . " ID: {$log->model_id}.";
                        break;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ProvisioningLog audit query notice: ' . $e->getMessage());
        }

        if (count($entries) < 5) {
            $status = $this->misd->status();
            $nowStr = now()->format('Y-m-d H:i:s');
            $entries[] = "[{$nowStr}] [MISD-STATUS] " . ($status['note'] ?? 'MISD simulation repository connected and operating in Mock Engine mode.');
        }

        return response()->json(['data' => $entries]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function listStaffRole(string $role): JsonResponse
    {
        $rows = User::with('facultyProfile')
            ->where('role', $role)
            ->orderByDesc('is_active')
            ->orderBy('faculty_number')
            ->get()
            ->map(fn (User $u) => $this->staff->formatStaff($u));

        return ApiResponse::list($rows);
    }

    private function assignStaff(Request $request, string $role): JsonResponse
    {
        $data = $request->validate([
            'faculty_number'  => 'required|string|max:50',
            'email'           => 'nullable|email|max:150',
            'first_name'      => 'nullable|string|max:100',
            'middle_name'     => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'contact_number'  => 'nullable|string|max:30',
            'department'      => 'nullable|string|max:150',
            'position'        => 'nullable|string|max:150',
        ]);

        try {
            $user = $this->staff->assign(
                $data['faculty_number'],
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
            'school_year'     => 'sometimes|string|max:20',
            'academic_year'   => 'sometimes|string|max:20',
            'semester'        => "{$required}|string|max:255",
            'faculty_user_id' => "{$required}|integer|exists:users,id",
            'is_active'       => 'sometimes|boolean',
        ]);
    }

    private function formatSectionAssignment(FacultySectionAssignment $a): array
    {
        $faculty = $a->faculty;
        $fp = $faculty?->facultyProfile;

        $programName = $a->program;
        if (!$programName || $programName === 'BACHELORO') {
            $programName = 'Bachelor of Science in Information Technology';
        }

        $sy = $a->school_year ?: '2025-2026';

        return [
            'id'              => $a->id,
            'program'         => $programName,
            'section'         => $a->section,
            'school_year'     => $sy,
            'academic_year'   => $sy,
            'semester'        => $a->semester ?: '2nd Semester',
            'is_active'       => (bool) $a->is_active,
            'faculty_user_id' => $a->faculty_user_id,
            'faculty'         => $faculty ? [
                'id'              => $faculty->id,
                'username'        => $faculty->username,
                'name'            => $fp ? trim("{$fp->first_name} {$fp->last_name}") : $faculty->username,
                'faculty_number'  => $fp?->faculty_number,
            ] : null,
            'created_at'      => optional($a->created_at)?->toIso8601String(),
            'updated_at'      => optional($a->updated_at)?->toIso8601String(),
        ];
    }

    private function unmappedSectionsPayload(): array
    {
        $profiles = StudentProfile::query()
            ->with('program')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->get(['id', 'user_id', 'section', 'program_id', 'school_year', 'semester']);

        $grouped = [];

        foreach ($profiles as $profile) {
            if ($this->sectionService->resolveFacultyForProfile($profile)) {
                continue;
            }

            $section = FacultySectionAssignmentService::normalizeSection($profile->section);
            $pName = $profile->program?->name ?: ($profile->program?->name ?: 'Bachelor of Science in Information Technology');
            $sy = $profile->school_year ?: '2025-2026';
            $sem = $profile->semester ?: '2nd Semester';

            $key = implode('|', [
                $pName,
                $section,
                $sy,
                (string) $sem,
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'section'       => $section,
                    'program'       => $pName,
                    'school_year'   => $sy,
                    'academic_year' => $sy,
                    'semester'      => $sem,
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
                'school_year'   => $assignment->school_year,
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
