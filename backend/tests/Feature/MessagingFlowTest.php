<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessagingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $username, string $email): User
    {
        return User::create([
            'username'  => $username,
            'email'     => $email,
            'password'  => Hash::make('password123'),
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function placedInternship(User $student, User $faculty, User $supervisor, User $coordinator): Internship
    {
        return Internship::create([
            'student_id'     => $student->id,
            'faculty_id'     => $faculty->id,
            'supervisor_id'  => $supervisor->id,
            'coordinator_id' => $coordinator->id,
            'academic_year'  => '2025-2026',
            'semester'       => 2,
            'term'           => 'AY 2025-2026, Sem 2',
            'status'         => 'active',
            'target_hours'   => config('interntrack.target_hours', 500),
        ]);
    }

    public function test_participants_can_send_and_read_messages(): void
    {
        $student = $this->user('student', 'STU-M1', 'stu-m1@example.com');
        $faculty = $this->user('faculty', 'FAC-M1', 'fac-m1@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M1', 'sup-m1@example.com');
        $coordinator = $this->user('coordinator', 'COR-M1', 'cor-m1@example.com');
        $internship = $this->placedInternship($student, $faculty, $supervisor, $coordinator);

        $send = $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $faculty->id,
            'body'          => 'Hello faculty, please check my journal.',
        ]);
        $send->assertCreated()->assertJsonPath('data.body', 'Hello faculty, please check my journal.');
        $this->assertSame('student', $send->json('data.sender_role'));
        $this->assertSame('faculty', $send->json('data.recipient_role'));

        $thread = $this->actingAs($faculty, 'sanctum')
            ->getJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}");
        $thread->assertOk();
        $this->assertCount(1, $thread->json('messages'));
        $this->assertArrayHasKey('meta', $thread->json());

        $this->assertNotNull(Message::first()->fresh()->read_at);

        $reply = $this->actingAs($coordinator, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $student->id,
            'body'          => 'Coordinator here — documents look good.',
        ]);
        $reply->assertCreated();

        $conversations = $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/messages/conversations');
        $conversations->assertOk();
        $this->assertGreaterThanOrEqual(2, count($conversations->json('data')));
        $this->assertArrayHasKey('meta', $conversations->json());
    }

    public function test_outsider_cannot_send_or_read_thread(): void
    {
        $student = $this->user('student', 'STU-M2', 'stu-m2@example.com');
        $faculty = $this->user('faculty', 'FAC-M2', 'fac-m2@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M2', 'sup-m2@example.com');
        $coordinator = $this->user('coordinator', 'COR-M2', 'cor-m2@example.com');
        $internship = $this->placedInternship($student, $faculty, $supervisor, $coordinator);

        $otherStudent = $this->user('student', 'STU-OTHER', 'other@example.com');
        $otherCoord = $this->user('coordinator', 'COR-OTHER', 'cor-other@example.com');

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $faculty->id,
            'body'          => 'Private note',
        ])->assertCreated();

        $this->actingAs($otherStudent, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $faculty->id,
            'body'          => 'Hacked?',
        ])->assertStatus(403);

        $this->actingAs($otherCoord, 'sanctum')
            ->getJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}")
            ->assertStatus(403);
    }

    public function test_cannot_message_user_not_on_internship(): void
    {
        $student = $this->user('student', 'STU-M3', 'stu-m3@example.com');
        $faculty = $this->user('faculty', 'FAC-M3', 'fac-m3@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M3', 'sup-m3@example.com');
        $coordinator = $this->user('coordinator', 'COR-M3', 'cor-m3@example.com');
        $internship = $this->placedInternship($student, $faculty, $supervisor, $coordinator);

        $stranger = $this->user('faculty', 'FAC-STRANGER', 'stranger@example.com');

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $stranger->id,
            'body'          => 'Should fail',
        ])->assertStatus(403);
    }

    public function test_new_faculty_sees_prior_role_scoped_history(): void
    {
        $student = $this->user('student', 'STU-M4', 'stu-m4@example.com');
        $oldFaculty = $this->user('faculty', 'FAC-OLD', 'fac-old@example.com');
        $newFaculty = $this->user('faculty', 'FAC-NEW', 'fac-new@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M4', 'sup-m4@example.com');
        $coordinator = $this->user('coordinator', 'COR-M4', 'cor-m4@example.com');
        $internship = $this->placedInternship($student, $oldFaculty, $supervisor, $coordinator);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $oldFaculty->id,
            'body'          => 'History for the faculty role slot',
        ])->assertCreated();

        $internship->update(['faculty_id' => $newFaculty->id]);

        $this->actingAs($oldFaculty, 'sanctum')
            ->getJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}")
            ->assertStatus(403);

        $thread = $this->actingAs($newFaculty, 'sanctum')
            ->getJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}");
        $thread->assertOk();
        $this->assertCount(1, $thread->json('messages'));
        $this->assertSame('History for the faculty role slot', $thread->json('messages.0.body'));
    }

    public function test_completed_internship_appears_in_archived_inbox(): void
    {
        $student = $this->user('student', 'STU-M5', 'stu-m5@example.com');
        $faculty = $this->user('faculty', 'FAC-M5', 'fac-m5@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M5', 'sup-m5@example.com');
        $coordinator = $this->user('coordinator', 'COR-M5', 'cor-m5@example.com');
        $internship = $this->placedInternship($student, $faculty, $supervisor, $coordinator);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
            'internship_id' => $internship->id,
            'recipient_id'  => $faculty->id,
            'body'          => 'Before completion',
        ])->assertCreated();

        $internship->update(['status' => 'completed']);

        $active = $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/messages/conversations?archived=0');
        $active->assertOk();
        $this->assertCount(0, $active->json('data'));

        $archived = $this->actingAs($student, 'sanctum')
            ->getJson('/api/v1/messages/conversations?archived=1');
        $archived->assertOk();
        $this->assertGreaterThanOrEqual(1, count($archived->json('data')));
    }

    public function test_send_is_rate_limited(): void
    {
        $student = $this->user('student', 'STU-M6', 'stu-m6@example.com');
        $faculty = $this->user('faculty', 'FAC-M6', 'fac-m6@example.com');
        $supervisor = $this->user('supervisor', 'SUP-M6', 'sup-m6@example.com');
        $coordinator = $this->user('coordinator', 'COR-M6', 'cor-m6@example.com');
        $internship = $this->placedInternship($student, $faculty, $supervisor, $coordinator);

        // throttle:30,1 — send 31 quickly
        $last = null;
        for ($i = 0; $i < 31; $i++) {
            $last = $this->actingAs($student, 'sanctum')->postJson('/api/v1/messages', [
                'internship_id' => $internship->id,
                'recipient_id'  => $faculty->id,
                'body'          => "Flood {$i}",
            ]);
        }

        $last->assertStatus(429)
            ->assertJsonFragment(['message' => 'Too many messages sent. Please wait a moment before sending again.']);
    }
}
