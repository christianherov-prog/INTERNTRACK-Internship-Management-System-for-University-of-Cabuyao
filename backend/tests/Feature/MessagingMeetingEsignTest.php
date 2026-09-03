<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class MessagingMeetingEsignTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_party_can_send_flat_dm_and_read_thread(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $supervisor->id,
            'body'          => 'Hello supervisor, confirming my schedule.',
        ])->assertCreated();

        $this->assertDatabaseHas('messages', [
            'internship_id' => $internship->id,
            'sender_id'     => $student->id,
            'recipient_id'  => $supervisor->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type'    => 'new_message',
        ]);

        Sanctum::actingAs($supervisor);
        $this->getJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Hello supervisor, confirming my schedule.');
    }

    public function test_outsider_cannot_send_flat_dm(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $outsider = $this->makeStudentWithSection('4ITA');
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($outsider);
        $this->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $supervisor->id,
            'body'          => 'Should fail',
        ])->assertStatus(403);
    }

    public function test_non_invitee_cannot_rsvp_to_meeting(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $outsider = $this->makeUser('student', '99-99999');

        Sanctum::actingAs($coordinator);
        $meetingId = $this->postJson('/api/v1/meetings', [
            'title' => 'Closed meeting',
            'type' => 'other',
            'starts_at' => now()->addDay()->toIso8601String(),
            'attendee_ids' => [$coordinator->id],
        ])->json('meeting.id');

        Sanctum::actingAs($outsider);
        $this->patchJson("/api/v1/meetings/{$meetingId}/rsvp", [
            'rsvp' => 'accepted',
        ])->assertStatus(404);
    }

    public function test_coordinator_can_create_meeting_and_invitee_can_rsvp(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($coordinator);
        $created = $this->postJson('/api/v1/meetings', [
            'title' => 'OJT Orientation',
            'type' => 'orientation',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'internship_id' => $internship->id,
        ])->assertCreated();

        $meetingId = $created->json('meeting.id');

        Sanctum::actingAs($student);
        $this->patchJson("/api/v1/meetings/{$meetingId}/rsvp", [
            'rsvp' => 'accepted',
        ])->assertOk()
            ->assertJsonPath('meeting.my_rsvp', 'accepted');
    }

}
