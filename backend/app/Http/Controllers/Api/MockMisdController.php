<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * MockMisdController
 *
 * Simulates the University of Cabuyao MISD (iEnroll) API.
 * Returns realistic JSON payloads matching the agreed-upon data contract.
 *
 * When the official MISD API is released, update MISD_USE_MOCK=false and
 * MISD_API_BASE_URL in .env. No changes to this controller or anywhere else
 * in the application will be required.
 */
class MockMisdController extends Controller
{
    /** All mock students keyed by student number */
    private array $students = [
        '2021-00123' => [
            'student_id'        => 1001,
            'student_number'    => '2021-00123',
            'first_name'        => 'Juan',
            'middle_name'       => 'Santos',
            'last_name'         => 'dela Cruz',
            'email'             => 'juan.delacruz@uc.edu.ph',
            'contact_number'    => '09171234567',
            'birthday'          => '2003-05-14',
            'sex'               => 'Male',
            'program'           => 'BS Information Technology',
            'college'           => 'College of Computing and Information Sciences',
            'department'        => 'Information Technology',
            'course_name'       => 'BS Information Technology',
            'year_level'        => 4,
            'section'           => '4-D',
            'academic_year'     => '2024-2025',
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
            'college'           => 'College of Computing and Information Sciences',
            'department'        => 'Computer Science',
            'course_name'       => 'BS Computer Science',
            'year_level'        => 4,
            'section'           => '4-A',
            'academic_year'     => '2024-2025',
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
            'college'           => 'College of Computing and Information Sciences',
            'department'        => 'Information Technology',
            'course_name'       => 'BS Information Technology',
            'year_level'        => 4,
            'section'           => '4-B',
            'academic_year'     => '2024-2025',
            'semester'          => 2,
            'enrollment_status' => 'Enrolled',
        ],
    ];

    /** All mock faculty keyed by employee number */
    private array $faculty = [
        'FAC-001' => [
            'faculty_id'        => 2001,
            'employee_number'   => 'FAC-001',
            'first_name'        => 'Andrea',
            'middle_name'       => 'Cruz',
            'last_name'         => 'Reyes',
            'email'             => 'a.reyes@uc.edu.ph',
            'contact_number'    => '09175557890',
            'department'        => 'Information Technology',
            'college'           => 'College of Computing and Information Sciences',
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
            'department'        => 'Computer Science',
            'college'           => 'College of Computing and Information Sciences',
            'position'          => 'Associate Professor',
            'employment_status' => 'Regular',
        ],
    ];

    /**
     * GET /api/v1/mock-misd/students/{studentNumber}
     */
    public function student(string $studentNumber): JsonResponse
    {
        $key  = strtoupper($studentNumber);
        $data = $this->students[$key] ?? null;

        if (!$data) {
            // Dynamically generate a record for any valid student-format ID
            if (preg_match('/^20\d{2}-\d{5}$/', $key)) {
                $data = $this->generateGenericStudent($key);
            } else {
                return response()->json(['message' => 'Student not found'], 404);
            }
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/mock-misd/faculty/{employeeNumber}
     */
    public function faculty(string $employeeNumber): JsonResponse
    {
        $key  = strtoupper($employeeNumber);
        $data = $this->faculty[$key] ?? null;

        if (!$data) {
            return response()->json(['message' => 'Faculty not found'], 404);
        }

        return response()->json($data);
    }

    /**
     * GET /api/v1/mock-misd/students — list all mock students
     */
    public function allStudents(): JsonResponse
    {
        return response()->json(array_values($this->students));
    }

    /**
     * GET /api/v1/mock-misd/faculty — list all mock faculty
     */
    public function allFaculty(): JsonResponse
    {
        return response()->json(array_values($this->faculty));
    }

    /**
     * Generate a generic student profile for any valid-format ID
     * that is not in the seed list (supports open registration).
     */
    private function generateGenericStudent(string $studentNumber): array
    {
        return [
            'student_id'        => rand(9000, 9999),
            'student_number'    => $studentNumber,
            'first_name'        => 'UC',
            'middle_name'       => null,
            'last_name'         => 'Student',
            'email'             => strtolower(str_replace('-', '.', $studentNumber)) . '@uc.edu.ph',
            'contact_number'    => null,
            'birthday'          => null,
            'sex'               => null,
            'program'           => 'BS Information Technology',
            'college'           => 'College of Computing and Information Sciences',
            'department'        => 'Information Technology',
            'course_name'       => 'BS Information Technology',
            'year_level'        => 4,
            'section'           => null,
            'academic_year'     => '2024-2025',
            'semester'          => 2,
            'enrollment_status' => 'Enrolled',
        ];
    }
}
