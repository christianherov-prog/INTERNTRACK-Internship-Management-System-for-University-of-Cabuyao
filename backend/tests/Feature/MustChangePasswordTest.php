<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_login_includes_must_change_password_flag(): void
    {
        $user = $this->makeUser('faculty', 'FAC-FORCE');
        $user->update([
            'password' => Hash::make('interntrack123'),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'FAC-FORCE',
            'password' => 'interntrack123',
        ])->assertOk()
            ->assertJsonPath('user.must_change_password', true);
    }

    public function test_change_password_clears_flag(): void
    {
        $user = $this->makeUser('faculty', 'FAC-CLEAR');
        $user->update([
            'password' => Hash::make('interntrack123'),
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'interntrack123',
            'new_password' => 'newsecure1',
            'new_password_confirmation' => 'newsecure1',
        ])->assertOk()
            ->assertJsonPath('user.must_change_password', false);

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_flagged_user_blocked_from_dashboard_but_can_change_password(): void
    {
        $user = $this->makeUser('faculty', 'FAC-BLOCK');
        $user->update([
            'password' => Hash::make('interntrack123'),
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertForbidden()
            ->assertJsonPath('message', 'Password change required before continuing.');

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'interntrack123',
            'new_password' => 'newsecure1',
            'new_password_confirmation' => 'newsecure1',
        ])->assertOk()
            ->assertJsonPath('user.must_change_password', false);

        $this->getJson('/api/v1/dashboard/summary')->assertOk();
    }
}
