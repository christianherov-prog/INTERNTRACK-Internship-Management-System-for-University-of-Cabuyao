<?php

namespace Tests\Feature;

use App\Mail\PasswordChangeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordChangeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_renders_html_and_text_mail_views(): void
    {
        Mail::fake();

        $user = User::create([
            'student_number' => '2300592',
            'email' => 'clarence.montealegre@uc.edu.ph',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => '2300592',
        ])->assertOk()->assertJsonPath('success', true);

        Mail::assertSent(PasswordChangeMail::class, function (PasswordChangeMail $mail) use ($user) {
            $built = $mail->content();
            $this->assertSame('emails.password_change', $built->view);
            $this->assertSame('emails.password_change.text', $built->text);
            $this->assertNotEmpty($built->with['confirmationLink'] ?? null);
            $this->assertStringContainsString('change-password-confirm', $built->with['confirmationLink']);
            $this->assertStringNotContainsString('password123', $built->with['confirmationLink']);

            $rendered = $mail->render();
            $this->assertStringContainsString('Password Reset Request', $rendered);
            $this->assertStringNotContainsString($user->password, $rendered);

            return $mail->hasTo($user->email);
        });
    }
}
