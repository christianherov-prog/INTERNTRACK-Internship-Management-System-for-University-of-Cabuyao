<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class ClockAttendanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    private function setupParty(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $otherSupervisor = $this->makeUser('supervisor', 'SUP-OTHER');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('coordinator', 'faculty', 'supervisor', 'otherSupervisor', 'student', 'company', 'internship');
    }

    public function test_student_can_clock_in_and_out(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')
            ->assertCreated()
            ->assertJsonPath('record.status', 'pending');

        $this->postJson('/api/v1/student/attendance/clock-out')
            ->assertOk();

        $this->assertDatabaseHas('attendance_logs', [
            'internship_id' => $party['internship']->id,
            'status' => 'pending',
        ]);
    }

    public function test_double_clock_in_returns_422(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->postJson('/api/v1/student/attendance/clock-in')->assertStatus(422);
    }

    public function test_supervisor_can_validate_clocked_out_record(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $logId = $out->json('record.id');

        Sanctum::actingAs($party['supervisor']);
        $this->patchJson("/api/v1/supervisor/attendance/{$logId}/validate", [
            'action' => 'validated',
        ])->assertOk()->assertJsonPath('record.status', 'validated');
    }

    public function test_wrong_supervisor_gets_403(): void
    {
        $party = $this->setupParty();
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $logId = $out->json('record.id');

        Sanctum::actingAs($party['otherSupervisor']);
        $this->patchJson("/api/v1/supervisor/attendance/{$logId}/validate", [
            'action' => 'validated',
        ])->assertForbidden();
    }
}
