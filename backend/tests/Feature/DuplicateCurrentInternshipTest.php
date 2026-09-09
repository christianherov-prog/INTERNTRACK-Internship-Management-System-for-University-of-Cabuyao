<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\ProgramHteRequirement;
use App\Services\InternshipProgressService;
use App\Support\InternshipProvisioning;
use App\Support\InternshipStatuses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DuplicateCurrentInternshipTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_create_pending_if_none_does_not_duplicate_open_internships(): void
    {
        $student = $this->makeStudentWithSection();

        $first = InternshipProvisioning::createPendingIfNone($student);
        $second = InternshipProvisioning::createPendingIfNone($student);
        $third = InternshipProvisioning::createPendingIfNone($student);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->id, $third->id);
        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
    }

    public function test_dashboard_get_does_not_create_a_second_open_internship(): void
    {
        $student = $this->makeStudentWithSection();
        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->getJson('/api/v1/student/dashboard')->assertOk();
        $this->getJson('/api/v1/student/records')->assertOk();

        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
    }

    public function test_ten_students_can_read_progress_without_creating_extra_internships(): void
    {
        $students = [];
        for ($i = 0; $i < 10; $i++) {
            $students[] = $this->makeStudentWithSection('4IT'.chr(65 + ($i % 4)));
        }

        foreach ($students as $student) {
            Sanctum::actingAs($student);
            $this->getJson('/api/v1/student/dashboard')->assertOk();
            $this->getJson('/api/v1/auth/user')
                ->assertOk()
                ->assertJsonPath('user.department.code', 'CCS');
        }

        foreach ($students as $student) {
            $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
        }
    }

    public function test_start_new_is_blocked_while_a_pending_internship_exists(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/internships/start-new')
            ->assertStatus(422)
            ->assertJsonPath('message', 'You still have an ongoing internship. Finish it first.');

        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
    }

    public function test_start_new_is_allowed_after_the_current_internship_is_completed(): void
    {
        $student = $this->makeStudentWithSection();
        $open = InternshipProvisioning::openForStudent($student->id);
        $this->assertNotNull($open);
        $open->update(['status' => 'completed']);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/internships/start-new')->assertOk();

        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
        $this->assertSame(2, Internship::where('student_id', $student->id)->count());
        $this->assertSame(1, Internship::where('student_id', $student->id)->where('status', 'completed')->count());
    }

    public function test_nursing_multi_hte_stays_on_one_internship(): void
    {
        $student = $this->makeStudentInCollege('CHAS', 'Bachelor of Science in Nursing', 'BSN', '4BSN-A');
        $this->seedProgramHours($student->studentProfile->program_id, 5, 540.60);
        $internship = InternshipProvisioning::openForStudent($student->id);
        InternshipProgressService::synchronize($internship->fresh());

        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
        $this->assertSame(5, $internship->fresh()->placements()->count());
    }

    public function test_duplicate_open_rows_are_superseded_not_deleted(): void
    {
        $student = $this->makeStudentWithSection();
        $keep = InternshipProvisioning::openForStudent($student->id);
        $keep->update(['company_id' => null, 'supervisor_id' => null]);

        $duplicateA = $student->internshipsAsStudent()->create([
            'status' => 'pending_placement',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'term' => 'AY 2025-2026, 2nd Semester',
            'program' => 'BSIT',
            'target_hours' => 500,
            'total_hours_rendered' => 0,
        ]);
        $duplicateB = $student->internshipsAsStudent()->create([
            'status' => 'pending_placement',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
            'term' => 'AY 2025-2026, 2nd Semester',
            'program' => 'BSIT',
            'company_id' => null,
            'target_hours' => 500,
            'total_hours_rendered' => 0,
        ]);

        $company = $this->makeEligibleCompany();
        $duplicateB->update(['company_id' => $company->id]);

        $cancelled = InternshipProvisioning::supersedeDuplicateOpenInternships();

        $this->assertSame(2, $cancelled);
        $this->assertSame(1, InternshipProvisioning::openQuery($student->id)->count());
        $this->assertSame($duplicateB->id, InternshipProvisioning::openForStudent($student->id)?->id);
        $this->assertSame('cancelled', $keep->fresh()->status);
        $this->assertSame('cancelled', $duplicateA->fresh()->status);
        $this->assertContains($duplicateB->fresh()->status, InternshipStatuses::openCurrent());
        $this->assertNotNull($keep->fresh());
        $this->assertNull($keep->fresh()->deleted_at);
    }

    private function seedProgramHours(int $programId, int $count, float $hoursEach): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ProgramHteRequirement::updateOrCreate(
                ['program_id' => $programId, 'sequence_order' => $i],
                ['label' => "HTE {$i}", 'required_hours' => $hoursEach]
            );
        }
    }
}
