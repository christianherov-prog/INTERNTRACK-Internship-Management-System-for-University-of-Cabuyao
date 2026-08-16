<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DirectorAbsorptionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_director_can_finalize_absorption(): void
    {
        $director = $this->makeUser('director', 'DIR-TEST1');
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'completed', 'absorption_status' => 'pending', 'end_date' => now()]);

        Sanctum::actingAs($director);

        $this->patchJson("/api/v1/director/internships/{$internship->id}/absorption", [
            'absorption_status' => 'absorbed',
            'absorbed_at' => now()->toDateString(),
            'job_title' => 'Junior Developer',
        ])->assertOk()
            ->assertJsonPath('internship.absorption_status', 'absorbed')
            ->assertJsonPath('internship.absorption_recorded_by_role', 'director');
    }

    public function test_supervisor_cannot_finalize_absorption(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'completed', 'absorption_status' => 'pending', 'end_date' => now()]);

        Sanctum::actingAs($supervisor);

        $this->patchJson("/api/v1/supervisor/internships/{$internship->id}/absorption", [
            'absorption_status' => 'absorbed',
        ])->assertNotFound();
    }

    public function test_student_declare_notifies_director(): void
    {
        $director = User::factory()->role('director')->create([
            'faculty_number' => 'DIR-NOTIFY',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'completed', 'absorption_status' => 'pending', 'end_date' => now()]);

        Sanctum::actingAs($student);

        $this->postJson('/api/v1/student/absorption/declare', [
            'notes' => 'I was hired as a trainee.',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $director->id,
            'type' => 'student_declared_hired',
        ]);
    }
}
