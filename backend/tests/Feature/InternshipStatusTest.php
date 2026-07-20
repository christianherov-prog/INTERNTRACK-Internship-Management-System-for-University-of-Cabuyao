<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\InternshipStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InternshipStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role, string $username, string $email): User
    {
        return User::create([
            'username'  => $username,
            'email'     => $email,
            'password'  => Hash::make('password123'),
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function createActiveInternship(User $student, ?User $coordinator = null): Internship
    {
        return Internship::create([
            'student_id'     => $student->id,
            'coordinator_id' => $coordinator?->id,
            'academic_year'  => '2024-2025',
            'semester'       => 2,
            'term'           => 'AY 2024-2025, Sem 2',
            'status'         => 'active',
            'target_hours'   => 360,
        ]);
    }

    public function test_coordinator_can_patch_active_to_completed_with_reason(): void
    {
        $coordinator = $this->createUser('coordinator', 'COR-1001', 'coord@example.com');
        $student = $this->createUser('student', 'STU-2001', 'stu2001@example.com');
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
        $coordinator = $this->createUser('coordinator', 'COR-1002', 'coord2@example.com');
        $student = $this->createUser('student', 'STU-2002', 'stu2002@example.com');
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
        $coordinator = $this->createUser('coordinator', 'COR-1003', 'coord3@example.com');
        $student = $this->createUser('student', 'STU-2003', 'stu2003@example.com');
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
