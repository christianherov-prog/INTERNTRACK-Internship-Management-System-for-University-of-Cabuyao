<?php

namespace Tests\Feature;

use App\Models\HteRequest;
use App\Models\Internship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DepartmentScopeIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_ccs_coordinator_cannot_list_coe_students_on_monitoring(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-ISO');
        $coeCoord = $this->makeUser('coordinator', 'COR-COE-ISO');
        $this->ensureStaffDepartment($coeCoord, 'COE');

        $ccsStudent = $this->makeStudentWithSection();
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );

        Sanctum::actingAs($ccsCoord);
        $ccsRows = collect($this->getJson('/api/v1/coordinator/monitoring')->assertOk()->json('data'));
        $this->assertTrue($ccsRows->contains(fn ($row) => (int) ($row['user_id'] ?? $row['id'] ?? 0) === $ccsStudent->id));
        $this->assertFalse($ccsRows->contains(fn ($row) => (int) ($row['user_id'] ?? $row['id'] ?? 0) === $coeStudent->id));

        Sanctum::actingAs($coeCoord);
        $coeRows = collect($this->getJson('/api/v1/coordinator/monitoring')->assertOk()->json('data'));
        $this->assertTrue($coeRows->contains(fn ($row) => (int) ($row['user_id'] ?? $row['id'] ?? 0) === $coeStudent->id));
        $this->assertFalse($coeRows->contains(fn ($row) => (int) ($row['user_id'] ?? $row['id'] ?? 0) === $ccsStudent->id));
    }

    public function test_ccs_coordinator_cannot_see_or_update_coe_hte_request(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-HTE');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );

        $request = HteRequest::create([
            'student_id' => $coeStudent->id,
            'company_name' => 'Other College HTE',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($ccsCoord);
        $payload = $this->getJson('/api/v1/coordinator/hte-requests')->assertOk()->json('requests');
        $this->assertCount(0, $payload);

        $this->patchJson("/api/v1/coordinator/hte-requests/{$request->id}/status", [
            'status' => 'approved',
        ])->assertForbidden()->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);
    }

    public function test_ccs_coordinator_cannot_change_unclaimed_coe_internship_status(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-STAT');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );
        $internship = Internship::create([
            'student_id' => $coeStudent->id,
            'coordinator_id' => null,
            'school_year' => '2024-2025',
            'semester' => 2,
            'term' => 'AY 2024-2025, Sem 2',
            'status' => 'active',
            'target_hours' => 500,
        ]);

        Sanctum::actingAs($ccsCoord);
        $this->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
            'status' => 'completed',
            'reason' => 'Attempting cross-department status change.',
        ])->assertForbidden()->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);
    }

    public function test_ccs_coordinator_cannot_download_coe_internship_file(): void
    {
        Storage::fake('local');

        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-FILE');
        $coeCoord = $this->makeUser('coordinator', 'COR-COE-FILE');
        $this->ensureStaffDepartment($coeCoord, 'COE');
        $faculty = $this->makeUser('faculty');
        $this->ensureStaffDepartment($faculty, 'COE');
        $supervisor = $this->makeUser('supervisor');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($coeStudent, $company, $supervisor, $faculty, $coeCoord);
        $realPath = 'internships/'.$internship->id.'/documents/secret.pdf';
        Storage::disk('local')->put($realPath, 'secret');

        Sanctum::actingAs($ccsCoord);
        $this->getJson('/api/v1/files/download?path='.urlencode($realPath))
            ->assertForbidden()
            ->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);

        Sanctum::actingAs($coeCoord);
        $this->get('/api/v1/files/download?path='.urlencode($realPath))->assertOk();
    }

    public function test_ccs_coordinator_cannot_request_coe_programs_via_query_param(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-ACAD');
        $coe = $this->departmentByCode('COE');
        \App\Models\Program::firstOrCreate(
            ['name' => 'Bachelor of Science in Civil Engineering'],
            ['code' => 'BSCE', 'department_id' => $coe->id, 'is_active' => true]
        );

        Sanctum::actingAs($ccsCoord);
        $programs = $this->getJson('/api/v1/academic/programs?department_id='.$coe->id)->assertOk()->json();
        $this->assertFalse(collect($programs)->contains(fn ($p) => ($p['code'] ?? '') === 'BSCE'));
    }

    public function test_ccs_faculty_cannot_view_coe_student_progress(): void
    {
        $ccsFaculty = $this->makeUser('faculty', 'FAC-CCS-ISO');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );

        Sanctum::actingAs($ccsFaculty);
        $this->getJson("/api/v1/faculty/students/{$coeStudent->id}/progress")
            ->assertForbidden()
            ->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);
    }

    public function test_ccs_coordinator_cannot_view_coe_student_progress(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-PROG');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );

        Sanctum::actingAs($ccsCoord);
        $this->getJson("/api/v1/coordinator/students/{$coeStudent->id}/progress")
            ->assertForbidden()
            ->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);
    }

    public function test_ccs_coordinator_faculty_options_exclude_other_department(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-FACOPT');
        $ccsFaculty = $this->makeUser('faculty', 'FAC-CCS-OPT');
        $coeFaculty = $this->makeUser('faculty', 'FAC-COE-OPT');
        $this->ensureStaffDepartment($coeFaculty, 'COE');

        Sanctum::actingAs($ccsCoord);
        $ids = collect($this->getJson('/api/v1/coordinator/evaluations')->assertOk()->json('faculty_options'))
            ->pluck('id');

        $this->assertTrue($ids->contains($ccsFaculty->id));
        $this->assertTrue($ids->contains($ccsCoord->id));
        $this->assertFalse($ids->contains($coeFaculty->id));
    }

    public function test_section_mapping_does_not_assign_faculty_from_another_department(): void
    {
        $coeFaculty = $this->makeUser('faculty', 'FAC-COE-MAP');
        $this->ensureStaffDepartment($coeFaculty, 'COE');
        \App\Models\FacultySectionAssignment::create([
            'program' => 'Bachelor of Science in Information Technology',
            'section' => '4ITD',
            'school_year' => '2024-2025',
            'semester' => 2,
            'faculty_user_id' => $coeFaculty->id,
            'is_active' => true,
        ]);

        $ccsStudent = $this->makeStudentWithSection('4ITD');
        $resolved = app(\App\Services\FacultySectionAssignmentService::class)
            ->resolveFacultyForProfile($ccsStudent->studentProfile);

        $this->assertNull($resolved);
    }

    public function test_director_cannot_place_student_with_faculty_from_another_department(): void
    {
        $director = $this->makeUser('director', 'DIR-DEPT');
        $ccsFaculty = $this->makeUser('faculty', 'FAC-CCS-PLACE');
        $coeStudent = $this->makeStudentInCollege(
            'COE',
            'Bachelor of Science in Civil Engineering',
            'BSCE',
            '4BSCE-A'
        );
        $internship = Internship::where('student_id', $coeStudent->id)->first()
            ?? Internship::create([
                'student_id' => $coeStudent->id,
                'coordinator_id' => null,
                'school_year' => '2024-2025',
                'semester' => 2,
                'term' => 'AY 2024-2025, Sem 2',
                'status' => 'pending_placement',
                'target_hours' => 500,
            ]);
        $internship->forceFill(['status' => 'pending_placement', 'faculty_id' => null])->saveQuietly();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');

        Sanctum::actingAs($director);
        $this->postJson("/api/v1/director/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $ccsFaculty->id,
            'supervisor_id' => $supervisor->id,
        ])->assertForbidden()->assertJson(['message' => \App\Support\DepartmentScope::DENIED_MESSAGE]);
    }
}
