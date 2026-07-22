<?php

namespace App\Services;

use App\Models\User;
use App\Models\StudentProfile;
use App\Models\FacultyProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * MisdIntegrationService
 *
 * Acts as the single integration layer between INTERNTRACK and the University
 * MISD (Management Information Systems Department) API.
 *
 * Architecture:
 *  - When MISD_USE_MOCK=true  → Queries the built-in mock endpoints
 *  - When MISD_USE_MOCK=false → Queries the official MISD_API_BASE_URL
 *
 * Switching from mock to official API only requires updating the .env file.
 * No frontend, business logic, or database changes are required.
 */
class MisdIntegrationService
{
    private string $baseUrl;
    private bool   $useMock;
    private int    $cacheTtl;

    public function __construct()
    {
        $this->useMock  = (bool) config('interntrack.misd_use_mock', true);
        $this->baseUrl  = rtrim(config('interntrack.misd_api_base_url'), '/');
        $this->cacheTtl = (int)  config('interntrack.misd_cache_ttl', 3600);
    }

    /**
     * Detect role from the username/ID format.
     * Matches the University ID conventions:
     *  20XX-XXXXX → student
     *  DIR-XXX    → director
     *  SUP-XXX    → supervisor
     *  FAC-XXX    → faculty
     *  EMP/COORD/ADMIN-XXX → coordinator
     */
    public static function detectRole(string $id): ?string
    {
        $id = strtoupper(trim($id));
        if (preg_match('/^20\d{2}-\d{5}$/', $id))          return 'student';
        if (preg_match('/^DIR-[A-Z0-9]+$/', $id))           return 'director';
        if (preg_match('/^SUP-[A-Z0-9]+$/', $id))           return 'supervisor';
        if (preg_match('/^FAC-[A-Z0-9]+$/', $id))           return 'faculty';
        if (preg_match('/^(EMP|COORD|ADMIN)-[A-Z0-9]+$/', $id)) return 'coordinator';
        return null;
    }

    /**
     * Attempt to provision a new user from MISD on first login.
     * Returns the local User on success, null on failure.
     */
    public function provision(string $username, string $password): ?User
    {
        $role = self::detectRole($username);
        if (!$role) return null;

        // Only provision with default password (new accounts start with interntrack123)
        $defaultPw = config('interntrack.default_password', 'interntrack123');
        if ($password !== $defaultPw) return null;

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

    /**
     * Fetch and provision a student from MISD.
     */
    private function provisionStudent(string $studentNumber, string $password): User
    {
        $data = $this->fetchStudent($studentNumber);

        $user = User::create([
            'username' => strtoupper($studentNumber),
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role'     => 'student',
        ]);

        StudentProfile::create([
            'user_id'           => $user->id,
            'student_number'    => strtoupper($studentNumber),
            'first_name'        => $data['first_name']        ?? 'Unknown',
            'middle_name'       => $data['middle_name']       ?? null,
            'last_name'         => $data['last_name']         ?? 'Student',
            'email'             => $data['email']             ?? null,
            'contact_number'    => $data['contact_number']    ?? null,
            'birthday'          => $data['birthday']          ?? null,
            'sex'               => $data['sex']               ?? null,
            'program'           => $data['program']           ?? null,
            'college'           => $data['college']           ?? null,
            'department'        => $data['department']        ?? null,
            'course_name'       => $data['course_name']       ?? null,
            'year_level'        => $data['year_level']        ?? null,
            'section'           => $data['section']           ?? null,
            'academic_year'     => $data['academic_year']     ?? null,
            'semester'          => $data['semester']          ?? null,
            'enrollment_status' => $data['enrollment_status'] ?? 'Enrolled',
            'synced_at'         => now(),
        ]);

        return $user;
    }

    /**
     * Fetch and provision a faculty member from MISD.
     */
    private function provisionFaculty(string $employeeNumber, string $password): User
    {
        $data = $this->fetchFaculty($employeeNumber);

        $user = User::create([
            'username' => strtoupper($employeeNumber),
            'email'    => $data['email'] ?? null,
            'password' => Hash::make($password),
            'role'     => 'faculty',
        ]);

        \App\Models\FacultyProfile::create([
            'user_id'           => $user->id,
            'employee_number'   => strtoupper($employeeNumber),
            'first_name'        => $data['first_name']       ?? 'Unknown',
            'middle_name'       => $data['middle_name']      ?? null,
            'last_name'         => $data['last_name']        ?? 'Faculty',
            'email'             => $data['email']            ?? null,
            'contact_number'    => $data['contact_number']   ?? null,
            'department'        => $data['department']       ?? null,
            'college'           => $data['college']          ?? null,
            'position'          => $data['position']         ?? null,
            'employment_status' => $data['employment_status']?? 'Regular',
            'synced_at'         => now(),
        ]);

        return $user;
    }

    /**
     * Provision non-iEnroll staff (supervisor, coordinator, director) with default profile.
     */
    private function provisionStaff(string $username, string $role, string $password): User
    {
        return User::create([
            'username' => strtoupper($username),
            'password' => Hash::make($password),
            'role'     => $role,
        ]);
    }

    /**
     * Fetch a student record from MISD (mock or live).
     */
    public function fetchStudent(string $studentNumber): array
    {
        $cacheKey = "misd_student_{$studentNumber}";
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($studentNumber) {
            $response = Http::timeout(10)
                ->retry(3, 500)
                ->get("{$this->baseUrl}/students/{$studentNumber}");

            if ($response->failed()) {
                Log::warning("MISD: student not found [{$studentNumber}]");
                return [];
            }
            return $response->json() ?? [];
        });
    }

    /**
     * Fetch a faculty record from MISD (mock or live).
     */
    public function fetchFaculty(string $employeeNumber): array
    {
        $cacheKey = "misd_faculty_{$employeeNumber}";
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($employeeNumber) {
            $response = Http::timeout(10)
                ->retry(3, 500)
                ->get("{$this->baseUrl}/faculty/{$employeeNumber}");

            if ($response->failed()) {
                Log::warning("MISD: faculty not found [{$employeeNumber}]");
                return [];
            }
            return $response->json() ?? [];
        });
    }
}
