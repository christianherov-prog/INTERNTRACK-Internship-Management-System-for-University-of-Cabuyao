<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'student_number'  => 'STU-1001',
            'email'     => 'student@example.com',
            'password'  => Hash::make('password123'),
            'role'      => 'student',
            'is_active' => true,
        ], $overrides));
    }

    public function test_login_success_returns_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'STU-1001',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_invalid_password_returns_422(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'STU-1001',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_missing_username_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_with_sanctum_token_invalidates_session(): void
    {
        $this->createUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'STU-1001',
            'password' => 'password123',
        ]);

        $login->assertOk();
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $logout = $this->withToken($token)->postJson('/api/v1/auth/logout');
        $logout->assertOk()
            ->assertJsonFragment(['message' => 'Logged out successfully.']);

        // Token id is the part before "|" in Sanctum plain text tokens.
        $tokenId = (int) explode('|', $token, 2)[0];
        $this->assertNull(PersonalAccessToken::find($tokenId));

        // Previous request left the user on the auth guard; clear so Bearer is re-checked.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/user')
            ->assertUnauthorized();
    }

    public function test_forgot_password_generates_token_and_returns_success(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => $user->student_number,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_faculty_login_resolves_id_from_faculty_profile_when_users_column_is_empty(): void
    {
        $user = $this->createUser([
            'student_number' => null,
            'faculty_number' => null,
            'email' => 'faculty-profile-login@example.com',
            'role' => 'faculty',
        ]);

        \App\Models\FacultyProfile::create([
            'user_id' => $user->id,
            'faculty_number' => 'FAC-LOGIN-1',
            'first_name' => 'Marvin',
            'last_name' => 'Bicua',
            'email' => $user->email,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'FAC-LOGIN-1',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'faculty');

        $this->assertSame('FAC-LOGIN-1', $user->fresh()->faculty_number);
    }

    public function test_local_login_accepts_legacy_default_password_and_rehashes(): void
    {
        config(['interntrack.allow_default_password_provision' => true]);
        config(['interntrack.default_password' => 'InternTrack123!']);

        $this->createUser([
            'student_number' => null,
            'faculty_number' => 'FAC-LEGACY-1',
            'email' => 'faculty-legacy@example.com',
            'role' => 'faculty',
            'password' => Hash::make('InternTrack123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'FAC-LEGACY-1',
            'password' => 'interntrack123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('InternTrack123!', User::where('faculty_number', 'FAC-LEGACY-1')->first()->password));
    }

    public function test_forgot_password_unknown_identifier_returns_generic_success(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => 'NON-EXISTENT-ID',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['success' => true]);
    }
}

