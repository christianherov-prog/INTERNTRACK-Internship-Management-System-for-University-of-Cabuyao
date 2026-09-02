<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Contracts\MisdRepositoryInterface;

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
                'course_description'       => 'IT Practicum(500 hours) ',
                'year_level'        => 4,
                'section'           => '4ITD',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300592' => [
                'student_number' => '2300592',
                'first_name'     => 'Clarence',
                'last_name'      => 'Montealegre',
            ],
            '2300603' => [
                'student_number'    => '2300603',
                'first_name'        => 'CHAS',
                'last_name'         => 'Nursing',
                'email'             => 'chas.nursing@uc.edu.ph',
                'sex'               => 'Female',
                'program'           => 'Bachelor of Science in Nursing',
                'department'        => 'College of Health and Allied Sciences',
                'year_level'        => 4,
                'section'           => '4BSN-A',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300604' => [
                'student_number'    => '2300604',
                'first_name'        => 'CAS',
                'last_name'         => 'Psychology',
                'email'             => 'cas.psychology@uc.edu.ph',
                'sex'               => 'Female',
                'program'           => 'Bachelor of Science in Psychology',
                'department'        => 'College of Arts and Sciences',
                'year_level'        => 4,
                'section'           => '4PSY-A',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300605' => [
                'student_number'    => '2300605',
                'first_name'        => 'CBAA',
                'last_name'         => 'Marketing',
                'email'             => 'cbaa.mm@uc.edu.ph',
                'sex'               => 'Female',
                'program'           => 'Bachelor of Science in Business Administration major in Marketing Management',
                'department'        => 'College of Business, Accountancy and Administration',
                'year_level'        => 4,
                'section'           => '4MM-A',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300606' => [
                'student_number'    => '2300606',
                'first_name'        => 'CBAA',
                'last_name'         => 'Finance',
                'email'             => 'cbaa.fm@uc.edu.ph',
                'sex'               => 'Male',
                'program'           => 'Bachelor of Science in Business Administration major in Financial Management',
                'department'        => 'College of Business, Accountancy and Administration',
                'year_level'        => 4,
                'section'           => '4FM-A',
                'academic_year'     => '2025-2026',
                'semester'          => '2nd Semester',
                'enrollment_status' => 'Enrolled',
            ],
            '2300607' => [
                'student_number'    => '2300607',
                'first_name'        => 'CBAA',
                'last_name'         => 'Accountancy',
                'email'             => 'cbaa.accountancy@uc.edu.ph',
                'sex'               => 'Male',
                'program'           => 'Bachelor of Science in Accountancy',
                'department'        => 'College of Business, Accountancy and Administration',
                'year_level'        => 4,
                'section'           => '4BSA-A',
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
            'FAC-1001' => [
                'faculty_number'   => 'FAC-1001',
                'first_name'        => 'Marvin',
                'middle_name'       => 'M',
                'last_name'         => 'Bicua',
                'email'             => 'm.bicua@uc.edu.ph',
                'contact_number'    => '09175557890',
                'sex'               => 'Male',
                'department'        => 'College of Computing Studies',
                'position'          => 'CCS Faculty',
                'employment_status' => 'Regular',
            ],

            'COR-CCS-001' => [
                'faculty_number'   => 'COR-CCS-001',
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
                'faculty_number'   => 'DIR-1001',
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

            'COR-CHAS-001' => [
                'faculty_number'   => 'COR-CHAS-001',
                'first_name'        => 'CHAS',
                'last_name'         => 'Coordinator',
                'email'             => 'coord.chas@uc.edu.ph',
                'department'        => 'College of Health and Allied Sciences',
                'position'          => 'CHAS Coordinator',
                'employment_status' => 'Regular',
            ],
            'FAC-CHAS-001' => [
                'faculty_number'   => 'FAC-CHAS-001',
                'first_name'        => 'CHAS',
                'last_name'         => 'Faculty',
                'email'             => 'faculty.chas@uc.edu.ph',
                'department'        => 'College of Health and Allied Sciences',
                'position'          => 'CHAS Faculty',
                'employment_status' => 'Regular',
            ],
            'COR-CAS-001' => [
                'faculty_number'   => 'COR-CAS-001',
                'first_name'        => 'CAS',
                'last_name'         => 'Coordinator',
                'email'             => 'coord.cas@uc.edu.ph',
                'department'        => 'College of Arts and Sciences',
                'position'          => 'CAS Coordinator',
                'employment_status' => 'Regular',
            ],
            'FAC-CAS-001' => [
                'faculty_number'   => 'FAC-CAS-001',
                'first_name'        => 'CAS',
                'last_name'         => 'Faculty',
                'email'             => 'faculty.cas@uc.edu.ph',
                'department'        => 'College of Arts and Sciences',
                'position'          => 'CAS Faculty',
                'employment_status' => 'Regular',
            ],
            'COR-CBAA-001' => [
                'faculty_number'   => 'COR-CBAA-001',
                'first_name'        => 'CBAA',
                'last_name'         => 'Coordinator',
                'email'             => 'coord.cbaa@uc.edu.ph',
                'department'        => 'College of Business, Accountancy and Administration',
                'position'          => 'CBAA Coordinator',
                'employment_status' => 'Regular',
            ],
            'FAC-CBAA-001' => [
                'faculty_number'   => 'FAC-CBAA-001',
                'first_name'        => 'CBAA',
                'last_name'         => 'Faculty',
                'email'             => 'faculty.cbaa@uc.edu.ph',
                'department'        => 'College of Business, Accountancy and Administration',
                'position'          => 'CBAA Faculty',
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
            'faculty_adviser_faculty_number' => $facultyEmp,
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

        foreach (['section', 'academic_year', 'semester', 'faculty_adviser_faculty_number', 'faculty_adviser_name'] as $field) {
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
            'department'        => 'College of Computing Studies',
            'course_description' => 'IT Practicum (500 hours)',
            'year_level'        => 4,
            'section'           => '4ITD',
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
            'faculty_number'   => $employeeNumber,
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
