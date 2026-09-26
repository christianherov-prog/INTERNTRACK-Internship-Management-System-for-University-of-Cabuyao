<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Internship;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Services\AuthService;
use App\Support\DepartmentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DepartmentOwnershipRepairTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function collegeParty(string $deptCode, string $programName, string $programCode, string $section): array
    {
        $coordinator = $this->makeUser('coordinator', 'COR-'.$deptCode.'-OWN');
        $this->ensureStaffDepartment($coordinator, $deptCode);
        $faculty = $this->makeUser('faculty', 'FAC-'.$deptCode.'-OWN');
        $this->ensureStaffDepartment($faculty, $deptCode);
        $supervisor = $this->makeUser('supervisor', 'SUP-'.$deptCode.'-OWN');
        $student = $this->makeStudentInCollege($deptCode, $programName, $programCode, $section);
        $company = $this->makeEligibleCompany(['company_name' => $deptCode.' Host']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    public function test_ccs_student_resolves_ccs_department_from_program(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.department.code', 'CCS')
            ->assertJsonPath('user.program', 'Bachelor of Science in Information Technology');

        $this->assertSame(
            $this->departmentByCode('CCS')->id,
            DepartmentScope::studentDepartmentId($student->fresh('studentProfile.program'))
        );
    }

    public function test_ccs_student_can_be_placed_with_ccs_faculty(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $ccs['internship']->forceFill(['status' => 'pending_placement', 'company_id' => null])->saveQuietly();
        $company = $this->makeEligibleCompany();

        Sanctum::actingAs($ccs['coordinator']);
        $this->postJson('/api/v1/coordinator/internships/'.$ccs['internship']->id.'/place', [
            'company_id' => $company->id,
            'supervisor_id' => $ccs['supervisor']->id,
            'faculty_id' => $ccs['faculty']->id,
        ])->assertOk();

        $this->assertDatabaseHas('internships', [
            'id' => $ccs['internship']->id,
            'faculty_id' => $ccs['faculty']->id,
        ]);
    }

    public function test_ccs_student_cannot_be_assigned_chas_faculty(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chasFaculty = $this->makeUser('faculty', 'FAC-CHAS-OWN');
        $this->ensureStaffDepartment($chasFaculty, 'CHAS');
        $ccs['internship']->forceFill(['status' => 'pending_placement', 'company_id' => null])->saveQuietly();
        $company = $this->makeEligibleCompany();

        Sanctum::actingAs($ccs['coordinator']);
        $this->postJson('/api/v1/coordinator/internships/'.$ccs['internship']->id.'/place', [
            'company_id' => $company->id,
            'supervisor_id' => $ccs['supervisor']->id,
            'faculty_id' => $chasFaculty->id,
        ])->assertStatus(422)
            ->assertJsonPath('errors.faculty_id.0', "Faculty must belong to the student's department.");
    }

    public function test_ccs_coordinator_sees_ccs_student_and_chas_coordinator_does_not(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');

        Sanctum::actingAs($ccs['coordinator']);
        $ccsRows = collect($this->getJson('/api/v1/coordinator/monitoring')->assertOk()->json('data'));
        $this->assertTrue($ccsRows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $ccs['student']->id));
        $this->assertFalse($ccsRows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $chas['student']->id));

        $this->getJson('/api/v1/coordinator/students/'.$chas['student']->id.'/progress')
            ->assertForbidden();

        Sanctum::actingAs($chas['coordinator']);
        $chasRows = collect($this->getJson('/api/v1/coordinator/monitoring')->assertOk()->json('data'));
        $this->assertTrue($chasRows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $chas['student']->id));
        $this->assertFalse($chasRows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $ccs['student']->id));
        $this->getJson('/api/v1/coordinator/students/'.$ccs['student']->id.'/progress')
            ->assertForbidden();
    }

    public function test_coe_coordinator_sees_only_coe_students(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $coe = $this->collegeParty('COE', 'Bachelor of Science in Civil Engineering', 'BSCE', '4BSCE-A');

        Sanctum::actingAs($coe['coordinator']);
        $rows = collect($this->getJson('/api/v1/coordinator/monitoring')->assertOk()->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $coe['student']->id));
        $this->assertFalse($rows->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $ccs['student']->id));
        $this->getJson('/api/v1/coordinator/students/'.$ccs['student']->id.'/progress')->assertForbidden();
    }

    public function test_ccs_faculty_sees_assigned_ccs_student_and_unrelated_faculty_is_denied(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $this->mapFacultyForSection($ccs['faculty'], '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $chas['internship']->update(['faculty_id' => $ccs['faculty']->id]);

        Sanctum::actingAs($ccs['faculty']);
        $ids = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->pluck('user_id');
        $this->assertTrue($ids->contains($ccs['student']->id));
        $this->assertFalse($ids->contains($chas['student']->id));

        $this->getJson('/api/v1/faculty/students/'.$chas['student']->id.'/progress')->assertForbidden();

        Sanctum::actingAs($chas['faculty']);
        $chasIds = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->pluck('user_id');
        $this->assertFalse($chasIds->contains($ccs['student']->id));
    }

    public function test_application_and_hte_request_notify_only_same_department_coordinators(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $company = $this->makeEligibleCompany();
        // Applying requires a student who is still seeking placement: an accepted,
        // active placement locks new applications (PLACEMENT-LOCK).
        $ccs['internship']->update(['status' => 'pending_placement', 'company_id' => null, 'supervisor_id' => null]);

        // The HTE request goes first: once the student has a pending application,
        // that application is their one current selection and a new-HTE request
        // is locked like any other company application (PLACE-LOCK).
        Sanctum::actingAs($ccs['student']);
        $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Unique CCS HTE '.uniqid(),
            'address' => 'Cabuyao',
            'contact_person' => 'HR',
            'contact_email' => 'hr@unique-ccs.example',
            'contact_number' => '09171234567',
        ])->assertSuccessful();
        $this->postJson('/api/v1/student/applications', [
            'company_id' => $company->id,
        ])->assertSuccessful();

        $this->assertTrue(
            Notification::query()->where('user_id', $ccs['coordinator']->id)->exists()
        );
        $this->assertFalse(
            Notification::query()->where('user_id', $chas['coordinator']->id)->exists()
        );

        Sanctum::actingAs($chas['coordinator']);
        $this->assertCount(0, $this->getJson('/api/v1/coordinator/applications')->assertOk()->json('applications'));
        $this->assertCount(0, $this->getJson('/api/v1/coordinator/hte-requests')->assertOk()->json('requests'));
    }

    public function test_documents_and_journals_respect_faculty_department_scope(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');

        Document::create([
            'internship_id' => $chas['internship']->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'pending_review',
        ]);
        JournalEntry::create([
            'internship_id' => $chas['internship']->id,
            'entry_number' => 1,
            'week_number' => 1,
            'date' => now()->toDateString(),
            'activities_summary' => 'CHAS journal',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($ccs['faculty']);
        $docs = collect($this->getJson('/api/v1/faculty/documents')->assertOk()->json('data'));
        $this->assertFalse($docs->contains(fn ($row) => (int) ($row['internship_id'] ?? 0) === $chas['internship']->id));

        $journals = collect($this->getJson('/api/v1/faculty/journals')->assertOk()->json('data'));
        $this->assertFalse($journals->contains(fn ($row) => (int) ($row['internship_id'] ?? 0) === $chas['internship']->id));
    }

    public function test_coordinator_reports_and_records_are_department_scoped(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');

        Sanctum::actingAs($ccs['coordinator']);
        $records = collect($this->getJson('/api/v1/coordinator/records')->assertOk()->json('data'));
        $this->assertTrue($records->contains(fn ($row) => (int) ($row['id'] ?? $row['user_id'] ?? 0) === $ccs['student']->id));
        $this->assertFalse($records->contains(fn ($row) => (int) ($row['id'] ?? $row['user_id'] ?? 0) === $chas['student']->id));

        $summary = collect($this->getJson('/api/v1/coordinator/reports/student-summary')->assertOk()->json('students'));
        $this->assertTrue($summary->contains(fn ($row) => ($row['student_number'] ?? '') === $ccs['student']->student_number));
        $this->assertFalse($summary->contains(fn ($row) => ($row['student_number'] ?? '') === $chas['student']->student_number));

        $programs = collect($this->getJson('/api/v1/academic/programs')->assertOk()->json());
        $this->assertTrue($programs->contains(fn ($p) => ($p['code'] ?? '') === 'BSIT'));
        $this->assertFalse($programs->contains(fn ($p) => ($p['code'] ?? '') === 'BSN'));

        $options = $this->getJson('/api/v1/coordinator/placement-options')->assertOk()->json();
        $this->assertFalse(collect($options['sections'] ?? [])->contains('4BSN-A'));
        $this->assertFalse(collect($options['faculty'] ?? [])->contains(fn ($f) => (int) ($f['id'] ?? 0) === $chas['faculty']->id));
    }

    public function test_login_does_not_fall_back_to_first_unrelated_coordinator(): void
    {
        $chasCoord = $this->makeUser('coordinator', 'COR-CHAS-FIRST');
        $this->ensureStaffDepartment($chasCoord, 'CHAS');
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-SECOND');
        $this->ensureStaffDepartment($ccsCoord, 'CCS');

        $student = $this->makeStudentWithSection();
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $company = $this->makeEligibleCompany();
        // Login now preserves authoritative assignments; it does not repair them.
        // Keep a valid CCS assignment and prove the first unrelated CHAS user
        // is not substituted during authentication.
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $ccsCoord);

        app(AuthService::class)->login($student->student_number, 'password', '127.0.0.1');

        $this->assertSame($ccsCoord->id, (int) $internship->fresh()->coordinator_id);
        $this->assertNotSame($chasCoord->id, (int) $internship->fresh()->coordinator_id);
    }

    public function test_missing_department_coordinator_stays_unassigned(): void
    {
        $chasCoord = $this->makeUser('coordinator', 'COR-CHAS-ONLY');
        $this->ensureStaffDepartment($chasCoord, 'CHAS');
        $student = $this->makeStudentWithSection();
        $internship = Internship::create([
            'student_id' => $student->id,
            'status' => 'active',
            'school_year' => '2024-2025',
            'semester' => 2,
            'term' => 'AY 2024-2025, Sem 2',
            'target_hours' => 360,
            'coordinator_id' => null,
            'faculty_id' => null,
        ]);

        app(AuthService::class)->login($student->student_number, 'password', '127.0.0.1');

        $this->assertNull($internship->fresh()->coordinator_id);
    }

    public function test_no_first_faculty_fallback_on_user_resource(): void
    {
        $stranger = $this->makeUser('faculty', 'FAC-FIRST');
        $this->ensureStaffDepartment($stranger, 'CCS');
        $stranger->facultyProfile->update(['first_name' => 'Wrong', 'last_name' => 'Faculty']);

        $student = $this->makeStudentWithSection('4ITZ');
        Internship::create([
            'student_id' => $student->id,
            'status' => 'active',
            'school_year' => '2024-2025',
            'semester' => 2,
            'term' => 'AY 2024-2025, Sem 2',
            'target_hours' => 360,
            'faculty_id' => null,
            'coordinator_id' => null,
        ]);

        Sanctum::actingAs($student->fresh());
        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('user.faculty', 'Not Assigned');
    }

    public function test_supervisor_relationship_is_independent_of_department(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');

        Sanctum::actingAs($ccs['supervisor']);
        $ids = collect($this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json('data'))
            ->pluck('id');
        $this->assertTrue($ids->contains($ccs['internship']->id));
    }

    public function test_director_and_misd_remain_university_wide(): void
    {
        $ccs = $this->collegeParty('CCS', 'Bachelor of Science in Information Technology', 'BSIT', '4ITD');
        $chas = $this->collegeParty('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $director = $this->makeUser('director', 'DIR-WIDE');
        $admin = $this->makeUser('admin', 'ADMIN-MISD-OWN');

        Sanctum::actingAs($director);
        $ids = collect($this->getJson('/api/v1/director/internships')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ccs['internship']->id));
        $this->assertTrue($ids->contains($chas['internship']->id));

        Sanctum::actingAs($admin);
        $userIds = collect($this->getJson('/api/v1/admin/users?per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($userIds->contains($ccs['student']->id));
        $this->assertTrue($userIds->contains($chas['student']->id));
        $this->assertTrue($userIds->contains($ccs['coordinator']->id));
        $this->assertTrue($userIds->contains($chas['coordinator']->id));
    }

    public function test_concurrent_department_hte_requests_do_not_cross_route(): void
    {
        $ccsCoord = $this->makeUser('coordinator', 'COR-CCS-CON');
        $this->ensureStaffDepartment($ccsCoord, 'CCS');
        $chasCoord = $this->makeUser('coordinator', 'COR-CHAS-CON');
        $this->ensureStaffDepartment($chasCoord, 'CHAS');

        for ($i = 0; $i < 10; $i++) {
            $ccsStudent = $this->makeStudentWithSection('4ITD');
            $this->assignFixtureAdviser($ccsStudent); // required before requesting an HTE
            Sanctum::actingAs($ccsStudent);
            $this->postJson('/api/v1/student/hte-requests', [
                'company_name' => 'CCS Concurrent '.$i.' '.uniqid(),
                'address' => 'Cabuyao',
                'contact_person' => 'HR',
                'contact_email' => 'ccs'.$i.'@example.test',
                'contact_number' => '0917000000'.$i,
            ])->assertSuccessful();

            $chasStudent = $this->makeStudentInCollege('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
            $this->assignFixtureAdviser($chasStudent);
            Sanctum::actingAs($chasStudent);
            $this->postJson('/api/v1/student/hte-requests', [
                'company_name' => 'CHAS Concurrent '.$i.' '.uniqid(),
                'address' => 'Cabuyao',
                'contact_person' => 'HR',
                'contact_email' => 'chas'.$i.'@example.test',
                'contact_number' => '0918000000'.$i,
            ])->assertSuccessful();
        }

        $this->assertGreaterThanOrEqual(10, Notification::query()->where('user_id', $ccsCoord->id)->count());
        $this->assertGreaterThanOrEqual(10, Notification::query()->where('user_id', $chasCoord->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $ccsCoord->id)->where('message', 'like', '%CHAS Concurrent%')->count());
        $this->assertSame(0, Notification::query()->where('user_id', $chasCoord->id)->where('message', 'like', '%CCS Concurrent%')->count());
    }
}
