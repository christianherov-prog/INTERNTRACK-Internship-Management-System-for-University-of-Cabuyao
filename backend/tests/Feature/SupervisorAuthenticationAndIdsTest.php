<?php

namespace Tests\Feature;

use App\Models\SupervisorProfile;
use App\Models\User;
use App\Support\SupervisorIds;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class SupervisorAuthenticationAndIdsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_seeded_style_supervisor_can_login_with_id_or_email(): void
    {
        $supervisor = $this->makeSupervisor('SUP-0001', 'patrick.bateman@techcorp.ph', 'interntrack123');

        $byId = $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-0001',
            'password' => 'interntrack123',
        ])->assertOk();

        $this->assertNotEmpty($byId->json('token'));
        $this->assertSame('supervisor', $byId->json('user.role'));
        $this->assertSame('/supervisor/dashboard', $byId->json('user.dashRoute'));
        $this->assertSame('SUP-0001', $byId->json('user.faculty_number'));

        $this->withToken($byId->json('token'))->postJson('/api/v1/auth/logout')->assertOk();

        $byEmail = $this->postJson('/api/v1/auth/login', [
            'username' => 'Patrick.Bateman@techcorp.ph',
            'password' => 'interntrack123',
        ])->assertOk();

        $this->assertSame($supervisor->id, $byEmail->json('user.id'));
        $this->assertSame('SUP-0001', $byEmail->json('user.username'));
    }

    public function test_inactive_supervisor_cannot_login(): void
    {
        $this->makeSupervisor('SUP-0002', 'inactive.sup@example.com', 'interntrack123', active: false);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'inactive.sup@example.com',
            'password' => 'interntrack123',
        ])->assertStatus(422);
    }

    public function test_invalid_supervisor_password_is_rejected(): void
    {
        $this->makeSupervisor('SUP-0003', 'valid.sup@example.com', 'interntrack123');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'SUP-0003',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_next_supervisor_id_ignores_null_ids_and_avoids_collisions(): void
    {
        User::factory()->role('supervisor')->create([
            'faculty_number' => null,
            'email' => 'null.id@example.com',
            'password' => Hash::make('password'),
        ]);
        User::factory()->role('supervisor')->create([
            'faculty_number' => 'SUP-0001',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);

        $next = SupervisorIds::nextFacultyNumber();
        $this->assertSame('SUP-0002', $next);

        $second = SupervisorIds::nextFacultyNumber();
        $this->assertSame('SUP-0002', $second);
        $this->assertNotSame('SUP-0001', $second);
    }

    public function test_backfill_assigns_ids_without_deleting_accounts(): void
    {
        $missing = User::factory()->role('supervisor')->create([
            'faculty_number' => null,
            'email' => 'needs.id@example.com',
            'password' => Hash::make('password'),
        ]);
        User::factory()->role('supervisor')->create([
            'faculty_number' => 'SUP-0001',
            'email' => 'taken@example.com',
            'password' => Hash::make('password'),
        ]);

        $assigned = SupervisorIds::backfillMissing();

        $this->assertCount(1, $assigned);
        $this->assertSame($missing->id, $assigned[0]['user_id']);
        $this->assertSame('SUP-0002', $assigned[0]['faculty_number']);
        $this->assertDatabaseHas('users', ['id' => $missing->id, 'faculty_number' => 'SUP-0002']);
        $this->assertDatabaseHas('users', ['email' => 'taken@example.com', 'faculty_number' => 'SUP-0001']);
    }

    public function test_faculty_number_is_unique_at_the_database_level(): void
    {
        User::factory()->role('supervisor')->create([
            'faculty_number' => 'SUP-0001',
            'email' => 'first@example.com',
        ]);

        $this->expectException(QueryException::class);

        User::factory()->role('supervisor')->create([
            'faculty_number' => 'SUP-0001',
            'email' => 'second@example.com',
        ]);
    }

    private function makeSupervisor(string $id, string $email, string $password, bool $active = true): User
    {
        $supervisor = User::create([
            'faculty_number' => $id,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'supervisor',
            'is_active' => $active,
        ]);

        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Patrick',
            'last_name' => 'Bateman',
            'email' => $email,
            'contact_number' => '09170000001',
            'sex' => 'Male',
            'position' => 'Supervisor',
        ]);

        return $supervisor->fresh('supervisorProfile');
    }
}
