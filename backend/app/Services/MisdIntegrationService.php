<?php

namespace App\Services;

use App\Models\User;
use App\Models\StudentProfile;
use App\Models\Department;
use App\Models\Program;
use App\Support\SexOptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * MisdIntegrationService
 *
 * Single integration layer between INTERNTRACK and University MISD (iEnroll).
 *
 *  - MISD_USE_MOCK=true  → reads MockMisdRepository in-process (no self-HTTP)
 *  - MISD_USE_MOCK=false → HTTP to MISD_API_BASE_URL
 */
class MisdIntegrationService
{
    private string $baseUrl;
    private bool   $useMock;
    private int    $cacheTtl;

    public function __construct(private MockMisdRepository $mock)
    {
        $this->useMock  = (bool) config('interntrack.misd_use_mock', true);
        $this->baseUrl  = rtrim((string) config('interntrack.misd_api_base_url'), '/');
        $this->cacheTtl = (int)  config('interntrack.misd_cache_ttl', 3600);
    }

    /**
     * Detect role from the username/ID format.
     */
    public static function detectRole(string $id): ?string
    {
        $id = strtoupper(trim($id));
        if (preg_match('/^20\d{2}-\d{5}$/', $id))                 return 'student';
        // Allow ADMIN-1001 and ADMIN-MISD-001 (hyphenated suffixes).
        if (preg_match('/^(MISD|ADMIN)-[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $id)) return 'admin';
        if (preg_match('/^DIR-[A-Z0-9]+$/', $id))                  return 'director';
        if (preg_match('/^SUP-?[A-Z0-9]+$/', $id))                 return 'supervisor';
        if (preg_match('/^FAC-[A-Z0-9]+$/', $id))                  return 'faculty';
        if (preg_match('/^(EMP|COORD|COR)-[A-Z0-9]+$/', $id))      return 'coordinator';
        return null;
    }

    /**
     * Attempt to provision a new user from MISD on first login.
     */
    public function provision(string $username, string $password): ?User
    {
        $role = self::detectRole($username);
        if (!$role) return null;

        $defaultPw = config('interntrack.default_password', 'interntrack123');
        if ($password !== $defaultPw) return null;

        if (!config('interntrack.allow_default_password_provision', false)) {
            Log::warning("MISD default-password provision blocked for [{$username}] (non-local / flag off).");
            return null;
        }

        try {
            if ($role === 'student') {
                return $this->provisionStudent($username, $password);
            } elseif ($role === 'faculty') {
                return $this->provisionFaculty($username, $password);
            } else {
                return $this->provisionStaff($username, $role, $password);
            }
        } catch (\Throwable $e) {
            Log::error("MISD provisioning failed for [{$username}]: " . $e->getMessage());
            return null;
        }
    }

    private function provisionStudent(string $studentNumber, string $password): User
    {
        $data = $this->fetchStudent($studentNumber);

        $user = User::create([
            'username' => strtoupper($studentNumber),
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role'     => 'student',
            'must_change_password' => true,
        ]);

        $academic = $this->resolveAcademicIds($data);

        StudentProfile::create([
            'user_id'           => $user->id,
            'student_number'    => strtoupper($studentNumber),
            'first_name'        => $data['first_name']        ?? 'Unknown',
            'middle_name'       => $data['middle_name']       ?? null,
            'last_name'         => $data['last_name']         ?? 'Student',
            'suffix'            => $data['suffix']            ?? null,
            'email'             => $data['email']             ?? null,
            'contact_number'    => $data['contact_number']    ?? null,
            'birthday'          => $data['birthday']          ?? null,
            'sex'               => SexOptions::sanitize($data['sex'] ?? null),
            'department_id'     => $academic['department_id'],
            'program_id'        => $academic['program_id'],
            'course_description'=> $data['course_name']       ?? null,
            'year_level'        => $data['year_level']        ?? null,
            'section'           => $data['section']           ?? null,
            'school_year'       => $data['academic_year']     ?? $data['school_year'] ?? null,
            'semester'          => $data['semester']          ?? null,
            'enrollment_status' => $data['enrollment_status'] ?? 'Enrolled',
            'synced_at'         => now(),
        ]);

        return $user;
    }

    private function provisionFaculty(string $employeeNumber, string $password): User
    {
        $data = $this->fetchFaculty($employeeNumber);

        $user = User::create([
            'username' => strtoupper($employeeNumber),
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role'     => 'faculty',
            'must_change_password' => true,
        ]);

        $departmentId = $this->resolveDepartmentId(
            $data['department'] ?? $data['college'] ?? 'CCS'
        ) ?? \App\Models\Department::first()?->id;

        \App\Models\FacultyProfile::create([
            'user_id'           => $user->id,
            'faculty_number'    => strtoupper($employeeNumber),
            'first_name'        => $data['first_name']       ?? 'Unknown',
            'middle_name'       => $data['middle_name']      ?? null,
            'last_name'         => $data['last_name']        ?? 'Faculty',
            'suffix'            => $data['suffix']           ?? null,
            'email'             => $data['email']            ?? null,
            'contact_number'    => $data['contact_number']   ?? null,
            'sex'               => SexOptions::sanitize($data['sex'] ?? null),
            'department_id'     => $departmentId,
            'position'          => $data['position']         ?? null,
            'employment_status' => $data['employment_status']?? 'Regular',
            'synced_at'         => now(),
        ]);

        return $user;
    }

    private function provisionStaff(string $username, string $role, string $password): User
    {
        $data = $this->fetchFaculty($username);

        $user = User::create([
            'username' => strtoupper($username),
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role'     => $role,
            'sex'      => SexOptions::sanitize($data['sex'] ?? null),
            'must_change_password' => true,
        ]);

        if (!empty($data) && in_array($role, ['faculty', 'coordinator', 'director', 'admin'], true)) {
            $departmentId = $this->resolveDepartmentId(
                $data['department'] ?? $data['college'] ?? ($role === 'director' ? 'PALD' : 'CCS')
            ) ?? \App\Models\Department::first()?->id;

            \App\Models\FacultyProfile::create([
                'user_id'           => $user->id,
                'faculty_number'    => strtoupper($username),
                'first_name'        => $data['first_name']       ?? 'Unknown',
                'middle_name'       => $data['middle_name']      ?? null,
                'last_name'         => $data['last_name']        ?? ucfirst($role),
                'suffix'            => $data['suffix']           ?? null,
                'email'             => $data['email']            ?? null,
                'contact_number'    => $data['contact_number']   ?? null,
                'sex'               => SexOptions::sanitize($data['sex'] ?? null),
                'department_id'     => $departmentId,
                'position'          => $data['position']         ?? null,
                'employment_status' => $data['employment_status']?? 'Regular',
                'synced_at'         => now(),
            ]);
        }

        return $user;
    }

    /**
     * Fetch a student record from MISD (mock in-process or live HTTP).
     */
    public function fetchStudent(string $studentNumber): array
    {
        $cacheKey = "misd_student_{$studentNumber}";
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($studentNumber) {
            if ($this->useMock) {
                return $this->mock->findStudent($studentNumber) ?? [];
            }

            try {
                $response = Http::timeout(5)
                    ->get("{$this->baseUrl}/students/{$studentNumber}");

                if ($response->successful()) {
                    return (array) $response->json();
                }

                Log::warning("MISD fetchStudent failed for {$studentNumber}", [
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::error("MISD fetchStudent exception for {$studentNumber}: " . $e->getMessage());
            }
            return [];
        });
    }

    /**
     * Fetch a faculty record from MISD (mock in-process or live HTTP).
     */
    public function fetchFaculty(string $employeeNumber): array
    {
        $cacheKey = "misd_faculty_{$employeeNumber}";
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($employeeNumber) {
            if ($this->useMock) {
                return $this->mock->findFaculty($employeeNumber) ?? [];
            }

            try {
                $response = Http::timeout(5)
                    ->get("{$this->baseUrl}/faculty/{$employeeNumber}");

                if ($response->successful()) {
                    return (array) $response->json();
                }

                Log::warning("MISD fetchFaculty failed for {$employeeNumber}", [
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::error("MISD fetchFaculty exception for {$employeeNumber}: " . $e->getMessage());
            }
            return [];
        });
    }

    public function syncStudent(User $user): void
    {
        $this->forgetStudentCache($user->username);
        $data = $this->fetchStudent($user->username);
        if (empty($data) || !$user->studentProfile) {
            return;
        }

        $allowed = [
            'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'contact_number',
            'birthday', 'sex', 'course_description',
            'year_level', 'section', 'school_year', 'semester', 'enrollment_status',
        ];

        $payload = array_intersect_key($data, array_flip($allowed));
        if (array_key_exists('course_name', $data) && empty($payload['course_description'])) {
            $payload['course_description'] = $data['course_name'];
        }
        if (array_key_exists('academic_year', $data) && empty($payload['school_year'])) {
            $payload['school_year'] = $data['academic_year'];
        }
        $academic = $this->resolveAcademicIds($data);
        if ($academic['department_id']) {
            $payload['department_id'] = $academic['department_id'];
        }
        if ($academic['program_id']) {
            $payload['program_id'] = $academic['program_id'];
        }
        if (array_key_exists('sex', $payload)) {
            $payload['sex'] = SexOptions::sanitize($payload['sex']);
        }
        $payload['synced_at'] = now();
        $user->studentProfile->update($payload);

        if (!empty($data['email'])) {
            $user->update(['email' => $data['email']]);
        }
    }

    /**
     * Refresh faculty/coordinator/director/admin identity from MISD (iEnroll wins).
     */
    public function syncFaculty(User $user): void
    {
        if (!in_array($user->role, ['faculty', 'coordinator', 'director', 'admin'], true)) {
            return;
        }

        $employeeNumber = $user->facultyProfile?->faculty_number ?: $user->username;
        $this->forgetFacultyCache($employeeNumber);
        $data = $this->fetchFaculty($employeeNumber);
        if (empty($data)) {
            return;
        }

        $sex = SexOptions::sanitize($data['sex'] ?? null);

        if ($user->facultyProfile) {
            $allowed = [
                'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'contact_number',
                'position', 'employment_status',
            ];
            $payload = array_intersect_key($data, array_flip($allowed));
            $payload['sex'] = $sex;
            $payload['synced_at'] = now();

            if (!empty($data['department']) || !empty($data['college'])) {
                $deptId = $this->resolveDepartmentId($data['department'] ?? $data['college']);
                if ($deptId) {
                    $payload['department_id'] = $deptId;
                }
            }
            $user->facultyProfile->update($payload);
        }

        $userUpdates = [];
        if (!empty($data['email'])) {
            $userUpdates['email'] = $data['email'];
        }
        if ($sex !== null) {
            $userUpdates['sex'] = $sex;
        }
        if ($userUpdates !== []) {
            $user->update($userUpdates);
        }
    }

    public function forgetStudentCache(string $studentNumber): void
    {
        Cache::forget("misd_student_{$studentNumber}");
    }

    public function forgetFacultyCache(string $employeeNumber): void
    {
        Cache::forget("misd_faculty_{$employeeNumber}");
    }

    public function listStudents(): array
    {
        if ($this->useMock) {
            return $this->mock->allStudents();
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 400)
                ->get("{$this->baseUrl}/students");

            if ($response->failed()) {
                return [];
            }

            $json = $response->json();
            return is_array($json) ? array_values($json) : [];
        } catch (\Throwable $e) {
            Log::warning('MISD listStudents failed: ' . $e->getMessage());
            return [];
        }
    }

    public function listFaculty(): array
    {
        if ($this->useMock) {
            return $this->mock->allFaculty();
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 400)
                ->get("{$this->baseUrl}/faculty");

            if ($response->failed()) {
                return [];
            }

            $json = $response->json();
            return is_array($json) ? array_values($json) : [];
        } catch (\Throwable $e) {
            Log::warning('MISD listFaculty failed: ' . $e->getMessage());
            return [];
        }
    }

    public function status(): array
    {
        if ($this->useMock) {
            $started = microtime(true);
            $count = count($this->mock->allFaculty());
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'use_mock'   => true,
                'base_url'   => 'in-process://MockMisdRepository',
                'cache_ttl'  => $this->cacheTtl,
                'reachable'  => true,
                'latency_ms' => $latencyMs,
                'error'      => null,
                'checked_at' => now()->toIso8601String(),
                'note'       => "Mock mode ({$count} faculty samples). No HTTP self-call.",
            ];
        }

        $reachable = false;
        $latencyMs = null;
        $error = null;

        try {
            $started = microtime(true);
            $response = Http::timeout(5)->get("{$this->baseUrl}/faculty");
            $latencyMs = (int) round((microtime(true) - $started) * 1000);
            $reachable = $response->successful();
            if (!$reachable) {
                $error = 'HTTP ' . $response->status();
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'use_mock'   => false,
            'base_url'   => $this->baseUrl,
            'cache_ttl'  => $this->cacheTtl,
            'reachable'  => $reachable,
            'latency_ms' => $latencyMs,
            'error'      => $error,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Map MISD college/program strings onto departments.id / programs.id.
     */
    private function resolveAcademicIds(array $data): array
    {
        $deptStr = trim((string) ($data['department'] ?? $data['college'] ?? ''));
        $progStr = trim((string) ($data['program'] ?? $data['course_name'] ?? ''));

        $departmentId = null;
        $programId = null;

        if ($deptStr !== '') {
            $departmentId = $this->resolveDepartmentId($deptStr);
        }

        if ($progStr !== '') {
            $program = Program::query()
                ->where(function ($query) use ($progStr) {
                    $query->where('code', $progStr)->orWhere('name', $progStr);
                })
                ->first();
            $programId = $program?->id;
            if (! $departmentId && $program) {
                $departmentId = $program->department_id;
            }
        }

        return [
            'department_id' => $departmentId,
            'program_id' => $programId,
        ];
    }

    private function resolveDepartmentId(?string $deptStr): ?int
    {
        $deptStr = trim((string) $deptStr);
        if ($deptStr === '') {
            return null;
        }

        return Department::query()
            ->where(function ($query) use ($deptStr) {
                $query->where('code', $deptStr)->orWhere('name', $deptStr);
            })
            ->value('id');
    }
}
