<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Program;
use App\Support\ProgramCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class FunctionalAuditRegressionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_student_can_update_contact_without_changing_identity(): void
    {
        $student = $this->makeStudentWithSection();
        $student->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'contact_number' => '09171234567',
        ]);

        Sanctum::actingAs($student);

        $this->putJson('/api/v1/auth/profile', [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
            'contact' => '09180001111',
        ])->assertOk()
            ->assertJsonPath('user.contact', '09180001111');

        $student->refresh()->load('studentProfile');
        $this->assertSame('09180001111', $student->studentProfile->contact_number);
        $this->assertSame('Clarence', $student->studentProfile->first_name);
        $this->assertNotSame('hacked@example.com', $student->email);
    }

    public function test_logbook_includes_student_name_and_program_for_form_31(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $student->studentProfile->update(['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 2,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => 'Clinic rotation',
        ])->assertCreated();

        $list = $this->getJson('/api/v1/student/logbook')->assertOk();
        $this->assertStringContainsString('Reyes', (string) $list->json('data.0.student_name'));
        $this->assertNotEmpty($list->json('data.0.program'));
        $this->assertStringNotContainsString('BSIT / BSCS', (string) $list->json('data.0.program'));
    }

    public function test_supervisor_pending_evaluations_include_assigned_interns_missing_forms(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makePendingInternship($student);
        $internship->update([
            'supervisor_id' => $supervisor->id,
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'coordinator_id' => $coordinator->id,
            'status' => 'pending_placement',
        ]);

        Sanctum::actingAs($supervisor);
        $dash = $this->getJson('/api/v1/supervisor/dashboard')->assertOk();
        $this->assertGreaterThanOrEqual(1, $dash->json('pending_evals'));

        $evals = $this->getJson('/api/v1/supervisor/evaluations')->assertOk();
        $pending = $evals->json('data.pending');
        $this->assertNotEmpty($pending);
        $this->assertContains('FO-24', $pending[0]['missing_forms']);
    }

    public function test_faculty_dashboard_count_matches_assigned_student_total(): void
    {
        $faculty = $this->makeUser('faculty', 'FAC-1001');
        $this->mapFacultyForSection($faculty, '4IT-D');
        $this->makeStudentWithSection('4IT-D');
        $this->makeStudentWithSection('4ITD');

        Sanctum::actingAs($faculty);
        $summary = $this->getJson('/api/v1/dashboard/summary')->assertOk();
        $list = $this->getJson('/api/v1/faculty/assigned-students')->assertOk();

        $this->assertSame(
            $summary->json('assigned_students_count'),
            $list->json('meta.total')
        );
        $this->assertSame(2, $list->json('meta.total'));
    }

    public function test_coordinator_records_include_department_students_without_coordinator_id(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-REC');
        $student = $this->makeStudentWithSection('4IT-A');
        $this->makePendingInternship($student);

        Sanctum::actingAs($coordinator);
        $records = $this->getJson('/api/v1/coordinator/records')->assertOk();
        $ids = collect($records->json('data'))->pluck('id')->all();
        $this->assertContains($student->id, $ids);
    }

    public function test_director_program_totals_equal_ongoing_plus_completed_plus_other(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $company = $this->makeEligibleCompany();
        $director = $this->makeUser('director');

        $pending = $this->makeStudentWithSection('4ITA');
        $this->makePendingInternship($pending);

        $active = $this->makeStudentWithSection('4ITB');
        $this->makeActiveInternship($active, $company, $supervisor, $faculty, $coordinator);

        $done = $this->makeStudentWithSection('4ITC');
        $completed = $this->makeActiveInternship($done, $company, $supervisor, $faculty, $coordinator);
        $completed->update(['status' => 'completed']);

        Sanctum::actingAs($director);
        $report = $this->getJson('/api/v1/director/dashboard')->assertOk();
        foreach ($report->json('by_program') as $row) {
            $this->assertSame(
                (int) $row['total'],
                (int) $row['ongoing'] + (int) $row['completed'] + (int) $row['other']
            );
        }
    }

    public function test_truncated_program_codes_are_repaired_to_canonical_codes(): void
    {
        $department = $this->departmentByCode('CCS');
        $program = Program::create([
            'name' => 'Bachelor of Science in Information Technology',
            'code' => 'BACHELORO',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $this->assertSame(1, ProgramCatalog::repairStoredCodes());
        $this->assertSame('BSIT', $program->fresh()->code);
        $this->assertSame('Bachelor of Science in Information Technology', ProgramCatalog::displayName('BACHELORO', 'Bachelor of Science in Information Technology'));
    }

    public function test_coordinator_without_department_id_still_receives_college_programs(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-CCS-NOPROF');
        $coordinator->facultyProfile()->update(['department_id' => null]);
        Program::firstOrCreate(
            ['name' => 'Bachelor of Science in Information Technology'],
            ['code' => 'BSIT', 'department_id' => $this->departmentByCode('CCS')->id, 'is_active' => true]
        );

        Sanctum::actingAs($coordinator->fresh());
        $programs = $this->getJson('/api/v1/academic/programs')->assertOk()->json();
        $this->assertNotEmpty($programs);
        $this->assertTrue(collect($programs)->contains(fn ($p) => ($p['code'] ?? '') === 'BSIT'));
    }
}
