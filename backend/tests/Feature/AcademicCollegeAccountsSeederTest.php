<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\AcademicCollegeAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCollegeAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_coordinator_faculty_and_student_per_new_program(): void
    {
        $this->seed(AcademicCollegeAccountsSeeder::class);

        foreach (['COR-CHAS-001', 'COR-CAS-001', 'COR-CBAA-001'] as $id) {
            $this->assertSame('coordinator', User::where('faculty_number', $id)->value('role'));
        }
        foreach (['FAC-CHAS-001', 'FAC-CAS-001', 'FAC-CBAA-001'] as $id) {
            $this->assertSame('faculty', User::where('faculty_number', $id)->value('role'));
        }

        $expected = [
            '2300603' => 'BSN',
            '2300604' => 'BSPSY',
            '2300605' => 'BSBAMM',
            '2300606' => 'BSBAFM',
            '2300607' => 'BSA',
        ];

        foreach ($expected as $studentNumber => $programCode) {
            $user = User::where('student_number', $studentNumber)->first();
            $this->assertNotNull($user);
            $this->assertSame('student', $user->role);
            $this->assertSame(
                Program::where('code', $programCode)->value('id'),
                $user->studentProfile()->value('program_id')
            );
        }

        $chasCoordDept = User::where('faculty_number', 'COR-CHAS-001')->first()?->facultyProfile?->department_id;
        $this->assertSame($chasCoordDept, User::where('student_number', '2300603')->first()?->studentProfile?->department_id);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(AcademicCollegeAccountsSeeder::class);
        $this->seed(AcademicCollegeAccountsSeeder::class);

        $this->assertSame(1, User::where('faculty_number', 'COR-CHAS-001')->count());
        $this->assertSame(1, User::where('student_number', '2300607')->count());
    }
}
