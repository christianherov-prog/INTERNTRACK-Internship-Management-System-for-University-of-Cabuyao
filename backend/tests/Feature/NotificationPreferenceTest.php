<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $prefs): User
    {
        return User::create([
            'username' => 'STU-PREF-'.uniqid(),
            'email' => 'pref-'.uniqid().'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
            'notification_preferences' => $prefs,
        ]);
    }

    public function test_opted_out_preference_suppresses_notification(): void
    {
        $student = $this->student([
            'emailReminders' => false,
            'attendanceAlerts' => true,
            'evaluationReminders' => true,
        ]);

        $created = Notification::notify(
            $student->id,
            'new_message',
            'New message',
            'Hello',
        );

        $this->assertNull($created);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $student->id,
            'type' => 'new_message',
        ]);
    }

    public function test_opted_in_preference_allows_notification(): void
    {
        $student = $this->student([
            'emailReminders' => true,
            'attendanceAlerts' => true,
            'evaluationReminders' => false,
        ]);

        $created = Notification::notify(
            $student->id,
            'new_message',
            'New message',
            'Hello',
        );

        $this->assertNotNull($created);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'new_message',
        ]);
    }

    public function test_second_type_respects_its_own_preference(): void
    {
        $student = $this->student([
            'emailReminders' => true,
            'attendanceAlerts' => false,
            'evaluationReminders' => true,
        ]);

        $blocked = Notification::notify(
            $student->id,
            'attendance_validated',
            'Attendance validated',
            'Your DTR was approved.',
        );
        $allowed = Notification::notify(
            $student->id,
            'document_approved',
            'Document approved',
            'Your MOA was approved.',
        );

        $this->assertNull($blocked);
        $this->assertNotNull($allowed);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $student->id,
            'type' => 'attendance_validated',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'document_approved',
        ]);
    }
}
