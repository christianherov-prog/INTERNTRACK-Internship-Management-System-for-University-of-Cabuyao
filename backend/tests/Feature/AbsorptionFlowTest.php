<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbsorptionFlowTest extends TestCase
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

    private function createInternship(User $student, User $supervisor, array $overrides = []): Internship
    {
        return Internship::create(array_merge([
            'student_id'     => $student->id,
            'supervisor_id'  => $supervisor->id,
            'academic_year'  => '2024-2025',
            'semester'       => 2,
            'term'           => 'AY 2024-2025, Sem 2',
            'status'         => 'completed',
            'target_hours'   => 360,
            'absorption_status' => 'pending',
        ], $overrides));
    }

    public function test_student_can_declare_hired_after_completed_internship(): void
    {
        $student = $this->createUser('student', 'STU-3001', 'stu3001@example.com');
        $supervisor = $this->createUser('supervisor', 'SUP-3001', 'sup3001@example.com');
        $internship = $this->createInternship($student, $supervisor);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/student/absorption/declare', [
                'internship_id' => $internship->id,
                'notes'         => 'I was offered a full-time role.',
            ]);

        $response->assertOk();

        $internship->refresh();
        $this->assertTrue($internship->student_declared_hired);
        $this->assertSame('pending', $internship->absorption_status);
    }

    public function test_supervisor_can_record_absorbed_outcome(): void
    {
        $student = $this->createUser('student', 'STU-3002', 'stu3002@example.com');
        $supervisor = $this->createUser('supervisor', 'SUP-3002', 'sup3002@example.com');
        $internship = $this->createInternship($student, $supervisor);

        $response = $this->actingAs($supervisor, 'sanctum')
            ->patchJson("/api/v1/supervisor/internships/{$internship->id}/absorption", [
                'absorption_status' => 'absorbed',
                'job_title'         => 'Junior Developer',
                'absorbed_at'       => '2025-06-01',
            ]);

        $response->assertOk();

        $internship->refresh();
        $this->assertSame('absorbed', $internship->absorption_status);
    }

    public function test_cannot_record_absorption_if_internship_not_completed(): void
    {
        $student = $this->createUser('student', 'STU-3003', 'stu3003@example.com');
        $supervisor = $this->createUser('supervisor', 'SUP-3003', 'sup3003@example.com');
        $internship = $this->createInternship($student, $supervisor, [
            'status' => 'active',
            'absorption_status' => null,
        ]);

        $response = $this->actingAs($supervisor, 'sanctum')
            ->patchJson("/api/v1/supervisor/internships/{$internship->id}/absorption", [
                'absorption_status' => 'absorbed',
            ]);

        $response->assertStatus(422);

        $internship->refresh();
        $this->assertNotSame('absorbed', $internship->absorption_status);
    }
}
