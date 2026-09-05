<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\InternshipStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class InternshipStatusTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function createActiveInternship($student, $coordinator = null): Internship
    {
        return Internship::create([
            'student_id'     => $student->id,
            'coordinator_id' => $coordinator?->id,
            'school_year'    => '2024-2025',
            'semester'       => 2,
            'term'           => 'AY 2024-2025, Sem 2',
            'status'         => 'active',
            'target_hours'   => config('interntrack.target_hours', 500),
        ]);
    }

    public function test_coordinator_can_patch_active_to_completed_with_reason(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-1001');
        $student = $this->makeStudentWithSection();
        $internship = $this->createActiveInternship($student, $coordinator);

        $response = $this->actingAs($coordinator, 'sanctum')
            ->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
                'status' => 'completed',
                'reason' => 'Student completed required hours.',
            ]);

        $response->assertOk()
            ->assertJsonPath('internship.status', 'completed');

        $internship->refresh();
        $this->assertSame('completed', $internship->status);
        $this->assertSame('pending', $internship->absorption_status);

        $this->assertDatabaseHas('internship_status_histories', [
            'internship_id' => $internship->id,
            'from_status'   => 'active',
            'to_status'     => 'completed',
            'changed_by'    => $coordinator->id,
        ]);

        $this->assertSame(1, InternshipStatusHistory::where('internship_id', $internship->id)->count());
    }

    public function test_patch_status_without_reason_returns_422(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-1002');
        $student = $this->makeStudentWithSection();
        $internship = $this->createActiveInternship($student, $coordinator);

        $response = $this->actingAs($coordinator, 'sanctum')
            ->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertStatus(422);
        $this->assertSame('active', $internship->fresh()->status);
    }

    public function test_patch_to_same_status_returns_422(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-1003');
        $student = $this->makeStudentWithSection();
        $internship = $this->createActiveInternship($student, $coordinator);

        $response = $this->actingAs($coordinator, 'sanctum')
            ->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
                'status' => 'active',
                'reason' => 'Already active status update.',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Internship is already in this status.']);
    }
}
