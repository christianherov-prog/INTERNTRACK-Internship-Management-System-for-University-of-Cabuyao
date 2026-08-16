<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Contracts\MisdRepositoryInterface;
use App\Models\StudentProfile;
use App\Models\FacultyProfile;

/**
 * In-process mock of the University of Cabuyao MISD (iEnroll) API.
 *
 * Used when MISD_USE_MOCK=true so Laravel never HTTP-calls its own
 * /mock-misd routes (that deadlocks php artisan serve / single-worker servers).
 *
 * Section/faculty assignment writes persist in cache overrides so coordinator
 * push-backs survive within the demo session.
 */
class MockMisdRepository implements MisdRepositoryInterface
{
    private const OVERRIDE_CACHE_KEY = 'mock_misd.student_assignment_overrides';

    /** @return array<string, array<string, mixed>> */
    public function students(): array
    {
        return [
            // Capstone demo accounts (same IDs as StudentAccountsSeeder)
            '2300600' => [
                'student_number'    => '2300600',
                'first_name'        => 'Christian Hero',
                'middle_name'       => 'Aboy',
                'last_name'         => 'Valinado',
                'email'             => 'christian.valinado@uc.edu.ph',
                'contact_number'    => '09123456789',
                'birthday'          => '2000-01-01',
                'sex'               => 'Male',
                'program'           => 'Bachelor of Science in Information Technology',
                'department'        => 'College of Computing Studies',
                'course_description'=> 'IT Practicum (500 hours)',
                'year_level'        => 4,
                'section'           => '4IT-D',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300590' => [
                'student_number'    => '2300590',
                'first_name'        => 'John',
                'middle_name'       => null,
                'last_name'         => 'Taac-Taac',
                'email'             => 'john.taactaac@uc.edu.ph',
                'contact_number'    => '09175550590',
                'birthday'          => '2001-05-15',
                'sex'               => 'Male',
                'program'           => 'Bachelor of Science in Information Technology',
                'department'        => 'College of Computing Studies',
                'course_description'=> 'IT Practicum (500 hours)',
                'year_level'        => 4,
                'section'           => '4IT-D',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300592' => [
                'student_number'    => '2300592',
                'first_name'        => 'Clarence',
                'middle_name'       => null,
                'last_name'         => 'Montealegre',
                'email'             => 'clarence.montealegre@uc.edu.ph',
                'contact_number'    => '09175550592',
                'birthday'          => '2001-08-20',
                'sex'               => 'Male',
                'program'           => 'Bachelor of Science in Information Technology',
                'department'        => 'College of Computing Studies',
                'course_description'=> 'IT Practicum (500 hours)',
                'year_level'        => 4,
                'section'           => '4IT-D',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function faculty(): array
    {
        return [
            'ADMIN-MISD-001' => [
                'faculty_number'    => 'ADMIN-MISD-001',
                'first_name'        => 'MISD',
                'middle_name'       => null,
                'last_name'         => 'Administrator',
                'email'             => 'misd.admin@uc.edu.ph',
                'contact_number'    => '09175557800',
                'sex'               => 'Male',
                'department'        => 'Management Information Systems Department',
                'position'          => 'MISD Administrator',
                'employment_status' => 'Regular',
            ],
            'ADMIN-1001' => [
                'faculty_number'    => 'ADMIN-1001',
                'first_name'        => 'MISD',
                'middle_name'       => null,
                'last_name'         => 'Administrator',
                'email'             => 'misd.admin@uc.edu.ph',
                'contact_number'    => '09175557800',
                'sex'               => 'Male',
                'department'        => 'Management Information Systems Department',
                'position'          => 'MISD Administrator',
                'employment_status' => 'Regular',
            ],
            'FAC-1001' => [
                'faculty_number'    => 'FAC-1001',
                'first_name'        => 'Marvin',
                'middle_name'       => 'M',
                'last_name'         => 'Bicuña',
                'email'             => 'm.bicuna@uc.edu.ph',
                'contact_number'    => '09175557890',
                'sex'               => 'Male',
                'department'        => 'College of Computing Studies',
                'position'          => 'CCS Faculty',
                'employment_status' => 'Regular',
            ],
            'COR-1001' => [
                'faculty_number'    => 'COR-1001',
                'first_name'        => 'Arcelito',
                'middle_name'       => 'C.',
                'last_name'         => 'Quiatchon',
                'email'             => 'a.quiatchon@uc.edu.ph',
                'contact_number'    => '09175557891',
                'sex'               => 'Male',
                'department'        => 'College of Computing Studies',
                'position'          => 'CCS Coordinator',
                'employment_status' => 'Regular',
            ],
            'DIR-1001' => [
                'faculty_number'    => 'DIR-1001',
                'first_name'        => 'Gina',
                'middle_name'       => 'M.',
                'last_name'         => 'Oloresisimo',
                'email'             => 'g.oloresisimo@uc.edu.ph',
                'contact_number'    => '09175557892',
                'sex'               => 'Female',
                'department'        => 'Placement, Alumni, & Linkages Department',
                'position'          => 'PALD Director',
                'employment_status' => 'Regular',
            ],
        ];
    }

    public function findStudent(string $studentNumber): ?array
    {
        $key = strtoupper(trim($studentNumber));
        // Catalog keys are mixed case for demo IDs; normalize lookup
        $students = $this->students();
        $row = null;
        $catalogKey = null;

        if (isset($students[$key])) {
            $row = $students[$key];
            $catalogKey = $key;
        } elseif (isset($students[$studentNumber])) {
            $row = $students[$studentNumber];
            $catalogKey = $studentNumber;
        } else {
            foreach ($students as $id => $candidate) {
                if (strcasecmp((string) $id, $key) === 0 || strcasecmp((string) $id, $studentNumber) === 0) {
                    $row = $candidate;
                    $catalogKey = (string) $id;
                    break;
                }
            }
        }

        if (!$row) {
            // Check if profile already exists in DB before generating generic placeholder
            $existingProfile = StudentProfile::where('student_number', $key)->first();
            if ($existingProfile && $existingProfile->first_name && $existingProfile->first_name !== 'UC') {
                $row = [
                    'student_id'        => $existingProfile->id,
                    'student_number'    => $existingProfile->student_number,
                    'first_name'        => $existingProfile->first_name,
                    'middle_name'       => $existingProfile->middle_name,
                    'last_name'         => $existingProfile->last_name,
                    'suffix'            => $existingProfile->suffix,
                    'email'             => $existingProfile->email,
                    'contact_number'    => $existingProfile->contact_number,
                    'birthday'          => $existingProfile->birthday,
                    'sex'               => $existingProfile->sex,
                    'program'           => $existingProfile->program?->name ?? 'Bachelor of Science in Information Technology',
                    'department'        => $existingProfile->department?->name ?? 'College of Computing Studies',
                    'course_description'=> $existingProfile->course_description ?? 'IT Practicum (500 hours)',
                    'year_level'        => $existingProfile->year_level ?? 4,
                    'section'           => $existingProfile->section ?? '4IT-D',
                    'academic_year'     => $existingProfile->school_year ?? '2025-2026',
                    'semester'          => $existingProfile->semester ?? '2nd Semester',
                    'enrollment_status' => $existingProfile->enrollment_status ?? 'Enrolled',
                ];
                $catalogKey = $key;
            } elseif (preg_match('/^20\d{2}-\d{5}$/', $key) || preg_match('/^\d{7}$/', $key)) {
                $row = $this->generateGenericStudent($key);
                $catalogKey = $key;
            }
        }

        if (!$row) {
            return null;
        }

        return $this->applyAssignmentOverride($row, $catalogKey ?? $key);
    }

    /**
     * Persist section + faculty adviser for a mock student (write-back simulation).
     *
     * @return array<string, mixed>|null Updated student record, or null if not found
     */
    public function updateStudentSectionFaculty(
        string $studentNumber,
        string $section,
        string $facultyEmployeeNumber,
        ?string $academicYear = null,
        ?int $semester = null,
        ?string $updatedBy = null,
        ?string $reason = null
    ): ?array {
        $existing = $this->findStudent($studentNumber);
        if (!$existing) {
            return null;
        }

        $faculty = $this->findFaculty($facultyEmployeeNumber);
        if (!$faculty) {
            return null;
        }

        $key = strtoupper(trim((string) ($existing['student_number'] ?? $studentNumber)));
        $section = FacultySectionAssignmentService::normalizeSection($section) ?? $section;
        $facultyEmp = strtoupper(trim($facultyEmployeeNumber));

        $overrides = $this->assignmentOverrides();
        $overrides[$key] = [
            'section'            => $section,
            'faculty_adviser_id' => $facultyEmp,
            'updated_at'         => now()->toIso8601String(),
            'updated_by'         => $updatedBy,
            'reason'             => $reason,
        ];
        Cache::forever(self::OVERRIDE_CACHE_KEY, $overrides);

        return $this->findStudent($studentNumber);
    }

    /**
     * Clear mock assignment overrides (tests / reset demo).
     */
    public function clearAssignmentOverrides(): void
    {
        Cache::forget(self::OVERRIDE_CACHE_KEY);
    }

    /** @return array<string, array<string, mixed>> */
    private function assignmentOverrides(): array
    {
        return Cache::get(self::OVERRIDE_CACHE_KEY, []);
    }

    private function applyAssignmentOverride(array $row, string $catalogKey): array
    {
        $overrides = $this->assignmentOverrides();
        $normKey = strtoupper(trim($catalogKey));
        $override = $overrides[$normKey]
            ?? $overrides[$row['student_number'] ?? '']
            ?? null;

        if (!$override) {
            return $row;
        }

        if (!empty($override['section'])) {
            $row['section'] = $override['section'];
        }
        if (!empty($override['faculty_adviser_id'])) {
            $row['faculty_adviser_id'] = $override['faculty_adviser_id'];
        }

        return $row;
    }

    public function findFaculty(string $employeeNumber): ?array
    {
        $key = strtoupper(trim($employeeNumber));
        $faculty = $this->faculty();
        if (isset($faculty[$key])) {
            return $faculty[$key];
        }

        // Check if profile exists in DB
        $existingProfile = FacultyProfile::where('faculty_number', $key)->first();
        if ($existingProfile && $existingProfile->first_name && $existingProfile->first_name !== 'UC') {
            return [
                'faculty_id'        => $existingProfile->id,
                'faculty_number'    => $existingProfile->faculty_number,
                'first_name'        => $existingProfile->first_name,
                'middle_name'       => $existingProfile->middle_name,
                'last_name'         => $existingProfile->last_name,
                'suffix'            => $existingProfile->suffix,
                'email'             => $existingProfile->email,
                'contact_number'    => $existingProfile->contact_number,
                'department'        => $existingProfile->department?->name ?? 'College of Computing Studies',
                'position'          => $existingProfile->position ?? 'Faculty',
                'employment_status' => $existingProfile->employment_status ?? 'Regular',
                'sex'               => $existingProfile->sex,
            ];
        }

        // Allow any standard faculty format
        if (preg_match('/^(FAC|COR|COORD|DIR|MISD|ADMIN|EMP)-[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $key)) {
            return $this->generateGenericFaculty($key);
        }

        return null;
    }

    public function allStudents(array $filters = []): array
    {
        $rows = array_values(array_map(
            fn ($k) => $this->findStudent((string) $k),
            array_keys($this->students())
        ));
        return array_filter($rows);
    }

    public function allFaculty(array $filters = []): array
    {
        return array_values($this->faculty());
    }

    private function generateGenericStudent(string $studentNumber): array
    {
        return [
            'student_id'        => rand(9000, 9999),
            'student_number'    => $studentNumber,
            'first_name'        => 'UC',
            'middle_name'       => null,
            'last_name'         => 'Student',
            'suffix'            => null,
            'email'             => strtolower(str_replace('-', '.', $studentNumber)) . '@uc.edu.ph',
            'contact_number'    => null,
            'birthday'          => null,
            'sex'               => null,
            'program'           => 'Bachelor of Science in Information Technology',
            'department'        => 'College of Computing Studies',
            'course_description'=> 'IT Practicum (500 hours)',
            'year_level'        => 4,
            'section'           => '4IT-D',
            'school_year'       => '2025-2026',
            'semester'          => '2nd Semester',
            'enrollment_status' => 'Enrolled',
        ];
    }

    private function generateGenericFaculty(string $employeeNumber): array
    {
        $prefix = explode('-', $employeeNumber)[0] ?? 'FAC';
        $position = match ($prefix) {
            'DIR'               => 'PALD Director',
            'COORD', 'COR', 'EMP' => 'Practicum Coordinator',
            'MISD', 'ADMIN'     => 'MISD Administrator',
            default             => 'Faculty',
        };
        $department = match ($prefix) {
            'DIR'           => 'Director',
            'MISD', 'ADMIN' => 'MISD',
            default         => 'Information Technology',
        };

        return [
            'faculty_id'        => rand(9000, 9999),
            'faculty_number'    => $employeeNumber,
            'first_name'        => 'UC',
            'middle_name'       => null,
            'last_name'         => ucfirst(strtolower($prefix)),
            'suffix'            => null,
            'email'             => strtolower(str_replace('-', '.', $employeeNumber)) . '@uc.edu.ph',
            'contact_number'    => null,
            'department'        => 'University of Cabuyao',
            'position'          => $position,
            'employment_status' => 'Regular',
            'sex'               => null,
        ];
    }
}
