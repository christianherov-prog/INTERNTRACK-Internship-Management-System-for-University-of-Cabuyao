<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * In-process mock of the University of Cabuyao MISD (iEnroll) API.
 *
 * Used when MISD_USE_MOCK=true so Laravel never HTTP-calls its own
 * /mock-misd routes (that deadlocks php artisan serve / single-worker servers).
 *
 * Section/faculty assignment writes persist in cache overrides so coordinator
 * push-backs survive within the demo session.
 */
class MockMisdRepository
{
    private const OVERRIDE_CACHE_KEY = 'mock_misd.student_assignment_overrides';

    /** @return array<string, array<string, mixed>> */
    public function students(): array
    {
        return [
            '2021-00123' => [
                'student_id'        => 1001,
                'student_number'    => '2021-00123',
                'first_name'        => 'Juan',
                'middle_name'       => 'Santos',
                'last_name'         => 'dela Cruz',
                'suffix'            => 'Jr.',
                'email'             => 'juan.delacruz@uc.edu.ph',
                'contact_number'    => '09171234567',
                'birthday'          => '2003-05-14',
                'sex'               => 'Male',
                'program'           => 'BS Information Technology',
                'college'           => 'College of Computing Studies',
                'department'        => 'Information Technology',
                'course_name'       => 'BS Information Technology',
                'year_level'        => 4,
                'section'           => '4ITA',
                'academic_year'     => '2025-2026',
                'semester'          => 2,
                'enrollment_status' => 'Enrolled',
            ],
            '2021-00456' => [
                'student_id'        => 1002,
                'student_number'    => '2021-00456',
                'first_name'        => 'Maria',
                'middle_name'       => 'Reyes',
                'last_name'         => 'Santos',
                'email'             => 'maria.santos@uc.edu.ph',
                'contact_number'    => '09189876543',
                'birthday'          => '2003-02-28',
                'sex'               => 'Female',
                'program'           => 'BS Computer Science',
                'college'           => 'College of Computing Studies',
                'department'        => 'Computer Science',
                'course_name'       => 'BS Computer Science',
                'year_level'        => 4,
                'section'           => '4ITB',
                'academic_year'     => '2025-2026',
                'semester'          => 2,
                'enrollment_status' => 'Enrolled',
            ],
            '2021-00789' => [
                'student_id'        => 1003,
                'student_number'    => '2021-00789',
                'first_name'        => 'Carlo',
                'middle_name'       => 'Bautista',
                'last_name'         => 'Mendoza',
                'email'             => 'carlo.mendoza@uc.edu.ph',
                'contact_number'    => '09201112233',
                'birthday'          => '2002-11-10',
                'sex'               => 'Male',
                'program'           => 'BS Information Technology',
                'college'           => 'College of Computing Studies',
                'department'        => 'Information Technology',
                'course_name'       => 'BS Information Technology',
                'year_level'        => 4,
                'section'           => '4ITC',
                'academic_year'     => '2025-2026',
                'semester'          => 2,
                'enrollment_status' => 'Enrolled',
            ],
            // Capstone demo accounts (same IDs as StudentAccountsSeeder)
            '2300600' => [
                'student_id'        => 2300600,
                'student_number'    => '2300600',
                'first_name'        => 'Christian Hero',
                'middle_name'       => 'Aboy',
                'last_name'         => 'Valinado',
                'email'             => 'christian.valinado@uc.edu.ph',
                'contact_number'    => '09123456789',
                'birthday'          => '2000-01-01',
                'sex'               => 'Male',
                'program'           => 'BS Information Technology',
                'college'           => 'College of Computing Studies',
                'department'        => 'Information Technology',
                'course_name'       => 'BS Information Technology',
                'year_level'        => 4,
                'section'           => '4ITD',
                'academic_year'     => '2025-2026',
                'semester'          => 1,
                'enrollment_status' => 'Enrolled',
            ],
            '2300592' => [
                'student_id'        => 2300592,
                'student_number'    => '2300592',
                'first_name'        => 'Clarence',
                'middle_name'       => null,
                'last_name'         => 'Montealegre',
                'email'             => 'clarence.montealegre@uc.edu.ph',
                'contact_number'    => null,
                'birthday'          => null,
                'sex'               => 'Male',
                'program'           => 'BS Information Technology',
                'college'           => 'College of Computing Studies',
                'department'        => 'Information Technology',
                'course_name'       => 'BS Information Technology',
                'year_level'        => 4,
                'section'           => '4ITD',
                'academic_year'     => '2025-2026',
                'semester'          => 1,
                'enrollment_status' => 'Enrolled',
            ],
            '2300590' => [
                'student_id'        => 2300590,
                'student_number'    => '2300590',
                'first_name'        => 'Angel Luis',
                'middle_name'       => 'Rafols',
                'last_name'         => 'Taac-Taac',
                'email'             => 'angel.taactaac@uc.edu.ph',
                'contact_number'    => null,
                'birthday'          => null,
                'sex'               => 'Male',
                'program'           => 'BS Information Technology',
                'college'           => 'College of Computing Studies',
                'department'        => 'Information Technology',
                'course_name'       => 'BS Information Technology',
                'year_level'        => 4,
                'section'           => '4ITD',
                'academic_year'     => '2025-2026',
                'semester'          => 2,
                'enrollment_status' => 'Enrolled',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function faculty(): array
    {
        return [
            'FAC-001' => [
                'faculty_id'        => 2001,
                'employee_number'   => 'FAC-001',
                'first_name'        => 'Andrea',
                'middle_name'       => 'Cruz',
                'last_name'         => 'Reyes',
                'email'             => 'a.reyes@uc.edu.ph',
                'contact_number'    => '09175557890',
                'sex'               => 'Female',
                'department'        => 'Information Technology',
                'college'           => 'College of Computing Studies',
                'position'          => 'Assistant Professor',
                'employment_status' => 'Regular',
            ],
            'FAC-002' => [
                'faculty_id'        => 2002,
                'employee_number'   => 'FAC-002',
                'first_name'        => 'Roberto',
                'middle_name'       => 'Garcia',
                'last_name'         => 'Lim',
                'email'             => 'r.lim@uc.edu.ph',
                'contact_number'    => '09189993456',
                'sex'               => 'Male',
                'department'        => 'Computer Science',
                'college'           => 'College of Computing Studies',
                'position'          => 'Associate Professor',
                'employment_status' => 'Regular',
            ],
            'FAC-1001' => [
                'faculty_id'        => 2101,
                'employee_number'   => 'FAC-1001',
                'first_name'        => 'Marvin',
                'middle_name'       => 'M.',
                'last_name'         => 'Bicua',
                'suffix'            => 'Sr.',
                'email'             => 'm.bicua@uc.edu.ph',
                'contact_number'    => '09175557890',
                'sex'               => 'Male',
                'department'        => 'Information Technology',
                'college'           => 'College of Computing Studies',
                'position'          => 'OJT Teacher',
                'employment_status' => 'Regular',
            ],
            'FAC-1002' => [
                'faculty_id'        => 2102,
                'employee_number'   => 'FAC-1002',
                'first_name'        => 'Andrea',
                'middle_name'       => null,
                'last_name'         => 'Reyes',
                'email'             => 'a.reyes@uc.edu.ph',
                'contact_number'    => null,
                'sex'               => 'Female',
                'department'        => 'Information Technology',
                'college'           => 'College of Computing Studies',
                'position'          => 'OJT Teacher',
                'employment_status' => 'Regular',
            ],
            'FAC-1003' => [
                'faculty_id'        => 2103,
                'employee_number'   => 'FAC-1003',
                'first_name'        => 'Roberto',
                'middle_name'       => null,
                'last_name'         => 'Lim',
                'email'             => 'r.lim@uc.edu.ph',
                'contact_number'    => null,
                'sex'               => 'Male',
                'department'        => 'Information Technology',
                'college'           => 'College of Computing Studies',
                'position'          => 'OJT Teacher',
                'employment_status' => 'Regular',
            ],
            'FAC-1004' => [
                'faculty_id'        => 2104,
                'employee_number'   => 'FAC-1004',
                'first_name'        => 'Maria',
                'middle_name'       => null,
                'last_name'         => 'Santos',
                'email'             => 'm.santos@uc.edu.ph',
                'contact_number'    => null,
                'sex'               => 'Female',
                'department'        => 'Information Technology',
                'college'           => 'College of Computing Studies',
                'position'          => 'OJT Teacher',
                'employment_status' => 'Regular',
            ],
            'COR-1001' => [
                'faculty_id'        => 2201,
                'employee_number'   => 'COR-1001',
                'first_name'        => 'Arcelito',
                'middle_name'       => 'C.',
                'last_name'         => 'Quiatchon',
                'email'             => 'a.quiatchon@uc.edu.ph',
                'contact_number'    => '09175557891',
                'sex'               => 'Male',
                'department'        => 'CCS',
                'college'           => 'College of Computing Studies',
                'position'          => 'Coordinator',
                'employment_status' => 'Regular',
            ],
            'DIR-1001' => [
                'faculty_id'        => 3001,
                'employee_number'   => 'DIR-1001',
                'first_name'        => 'Gina',
                'middle_name'       => 'M.',
                'last_name'         => 'Oloresisimo',
                'email'             => 'g.oloresisimo@uc.edu.ph',
                'contact_number'    => '09175557892',
                'sex'               => 'Female',
                'department'        => 'Director',
                'college'           => 'University Administration',
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
            // UC formats: 20XX-XXXXX or 7-digit student numbers (e.g. 2300600)
            if (preg_match('/^20\d{2}-\d{5}$/', $key) || preg_match('/^\d{7}$/', $key)) {
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
            'section'                         => $section,
            'faculty_adviser_employee_number' => $facultyEmp,
            'faculty_adviser_name'            => trim(($faculty['first_name'] ?? '') . ' ' . ($faculty['last_name'] ?? '')),
            'academic_year'                   => $academicYear ?? ($existing['academic_year'] ?? null),
            'semester'                        => $semester ?? ($existing['semester'] ?? null),
            'updated_by'                      => $updatedBy,
            'reason'                          => $reason,
            'updated_at'                      => now()->toIso8601String(),
        ];
        Cache::forever(self::OVERRIDE_CACHE_KEY, $overrides);

        return $this->findStudent($key);
    }

    /** @return array<string, array<string, mixed>> */
    private function assignmentOverrides(): array
    {
        $raw = Cache::get(self::OVERRIDE_CACHE_KEY, []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyAssignmentOverride(array $row, string $lookupKey): array
    {
        $overrides = $this->assignmentOverrides();
        $studentNumber = strtoupper(trim((string) ($row['student_number'] ?? $lookupKey)));
        $override = $overrides[$studentNumber]
            ?? $overrides[strtoupper(trim($lookupKey))]
            ?? null;

        if (!is_array($override)) {
            return $row;
        }

        foreach (['section', 'academic_year', 'semester', 'faculty_adviser_employee_number', 'faculty_adviser_name'] as $field) {
            if (array_key_exists($field, $override) && $override[$field] !== null && $override[$field] !== '') {
                $row[$field] = $override[$field];
            }
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

        if (preg_match('/^(FAC|DIR|COORD|COR|EMP|MISD|ADMIN)-[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $key)) {
            return $this->generateGenericFaculty($key);
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public function allStudents(): array
    {
        $out = [];
        foreach ($this->students() as $id => $row) {
            $out[] = $this->applyAssignmentOverride($row, (string) $id);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function allFaculty(): array
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
            'program'           => 'BS Information Technology',
            'college'           => 'College of Computing Studies',
            'department'        => 'Information Technology',
            'course_name'       => 'BS Information Technology',
            'year_level'        => 4,
            'section'           => '4ITD',
            'academic_year'     => '2025-2026',
            'semester'          => 1,
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
            'employee_number'   => $employeeNumber,
            'first_name'        => 'UC',
            'middle_name'       => null,
            'last_name'         => ucfirst(strtolower($prefix)),
            'suffix'            => null,
            'email'             => strtolower(str_replace('-', '.', $employeeNumber)) . '@uc.edu.ph',
            'contact_number'    => null,
            'department'        => $department,
            'college'           => 'University of Cabuyao',
            'position'          => $position,
            'employment_status' => 'Regular',
            'sex'               => null,
        ];
    }
}
