<?php

namespace App\Imports;

use App\Models\User;
use App\Models\StudentProfile;
use App\Models\FacultySectionAssignment;
use App\Models\Program;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StudentsImport implements ToCollection, WithHeadingRow
{
    protected $facultyId;
    protected $section;
    protected $program;
    protected $schoolYear;
    protected $semester;

    public function __construct(int $facultyId, string $section, string $program, string $schoolYear, string $semester)
    {
        $this->facultyId = $facultyId;
        $this->section = $section;
        $this->program = $program;
        $this->schoolYear = $schoolYear;
        $this->semester = $semester;
    }

    public function collection(Collection $rows)
    {
        // First, ensure the faculty-section assignment exists
        FacultySectionAssignment::firstOrCreate([
            'faculty_user_id' => $this->facultyId,
            'section'         => $this->section,
            'program'         => $this->program,
            'school_year'     => $this->schoolYear,
            'semester'        => $this->semester,
        ], [
            'is_active' => true,
        ]);

        $facultyProfile = \App\Models\FacultyProfile::where('user_id', $this->facultyId)->first();
        $facultyEmp = $facultyProfile?->faculty_number ?? 'FAC-1001';

        $programModel = Program::where('name', $this->program)
            ->orWhere('code', $this->program)
            ->first();
        if (! $programModel) {
            throw new \Exception("Unknown program \"{$this->program}\". Choose a program from the academic catalog.");
        }

        $errors = [];
        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            if (empty($row['email']) || empty($row['student_id'])) {
                continue;
            }

            $existingById = StudentProfile::where('student_number', $row['student_id'])->first();
            if ($existingById && !empty($existingById->section) && $existingById->section !== $this->section) {
                $errors[] = "Row {$rowNum}: Student {$row['student_id']} is already assigned to section {$existingById->section}.";
            }
        }

        if (!empty($errors)) {
            throw new \Exception(implode(" | ", $errors));
        }

        foreach ($rows as $row) {
            if (empty($row['email']) || empty($row['student_id'])) {
                continue; // Skip invalid rows
            }

            // Create or update User
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'student_number' => $row['student_id'],
                    'password' => Hash::make($row['student_id']), // Default password is ID
                    'role'     => 'student',
                    'is_active'=> true,
                ]
            );

            // Create or update StudentProfile
            StudentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'student_number' => $row['student_id'],
                    'first_name'     => $row['first_name'] ?? 'Unknown',
                    'last_name'      => $row['last_name'] ?? 'Unknown',
                    'middle_name'    => $row['middle_name'] ?? null,
                    'program_id'     => $programModel->id,
                    'department_id'  => $programModel->department_id,
                    'section'        => $this->section,
                    'school_year'    => $this->schoolYear,
                    'semester'       => $this->semester,
                    'email'          => $row['email'],
                ]
            );

            // Sync with MISD / iEnroll
            app(\App\Services\MisdWriteService::class)->updateStudentSectionFaculty([
                'student_number'          => (string) $row['student_id'],
                'section'                 => $this->section,
                'faculty_number'          => $facultyEmp,
                'school_year'             => $this->schoolYear,
                'semester'                => $this->semester,
                'updated_by'              => 'excel_upload',
                'reason'                  => 'Class list upload by faculty/admin',
                'actor_user_id'           => $this->facultyId,
            ]);
        }
    }
}
