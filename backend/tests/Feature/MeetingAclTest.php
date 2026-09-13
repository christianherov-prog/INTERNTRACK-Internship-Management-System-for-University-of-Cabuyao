<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class MeetingAclTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_coordinator_cannot_attach_unmanaged_internship(): void
    {
        $owner = $this->makeUser('coordinator', 'COR-OWNER');
        $other = $this->makeUser('coordinator', 'COR-OTHER');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $owner);

        Sanctum::actingAs($other);

        $this->postJson('/api/v1/meetings', [
            'title' => 'Check-in',
            'type' => 'check_in',
            'starts_at' => now()->addDay()->toIso8601String(),
            'internship_id' => $internship->id,
        ])->assertForbidden();
    }

    public function test_outsider_attendee_ids_are_rejected_when_internship_set(): void
    {
        $coordinator = $this->makeUser('coordinator', 'COR-MTG');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $outsider = $this->makeUser('student', '20-OUTSIDER');
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($coordinator);

        $this->postJson('/api/v1/meetings', [
            'title' => 'Check-in',
            'type' => 'check_in',
            'starts_at' => now()->addDay()->toIso8601String(),
            'internship_id' => $internship->id,
            'attendee_ids' => [$outsider->id],
        ])->assertStatus(422);
    }
}
