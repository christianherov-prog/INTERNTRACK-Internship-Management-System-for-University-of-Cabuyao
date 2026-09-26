<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Change Password was removed for every role (PASSWORD-01). The legacy
 * must_change_password flag is still reported but no longer gates the API,
 * because there is no in-app way left to clear it.
 */
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

    public function test_change_password_endpoints_are_removed_for_all_roles(): void
    {
        foreach (['student', 'faculty', 'coordinator', 'supervisor', 'director', 'admin'] as $role) {
            $user = $this->makeUser($role);
            $user->update(['password' => Hash::make('interntrack123')]);
            Sanctum::actingAs($user);

            $this->postJson('/api/v1/auth/change-password', [
                'current_password' => 'interntrack123',
                'new_password' => 'newsecure1',
                'new_password_confirmation' => 'newsecure1',
            ])->assertNotFound();
            $this->postJson('/api/v1/auth/request-password-change')->assertNotFound();

            $this->assertTrue(Hash::check('interntrack123', $user->fresh()->password), "{$role} password must be unchanged");
        }
    }

    public function test_flagged_user_is_not_trapped_behind_removed_password_change(): void
    {
        $user = $this->makeUser('faculty', 'FAC-BLOCK');
        $user->update([
            'password' => Hash::make('interntrack123'),
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/summary')->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk();
        $this->getJson('/api/v1/auth/signature/status')->assertOk();
    }
}
