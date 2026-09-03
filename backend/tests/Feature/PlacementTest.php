<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PlacementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_rejects_expired_moa_company(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany([
            'moa_expiry_date' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($coordinator);

        $this->postJson("/api/v1/coordinator/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
        ])->assertStatus(422);

        $this->assertSame('pending_placement', $internship->fresh()->status);
    }

    public function test_rejects_zero_slots(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 0]);

        Sanctum::actingAs($coordinator);

        $this->postJson("/api/v1/coordinator/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
        ])->assertStatus(422);
    }

    public function test_rejects_non_supervisor(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $notSupervisor = $this->makeUser('faculty', 'FAC-NOTSUP');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany();

        Sanctum::actingAs($coordinator);

        $this->postJson("/api/v1/coordinator/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $notSupervisor->id,
        ])->assertStatus(422);
    }

    public function test_places_eligible_company_and_consumes_slot(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 3]);

        Sanctum::actingAs($coordinator);

        $this->postJson("/api/v1/coordinator/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
        ])->assertOk()
            ->assertJsonPath('message', 'Placement assigned successfully.');

        $this->assertSame('active', $internship->fresh()->status);
        $this->assertSame(2, (int) $company->fresh()->slots_available);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'placement_assigned',
        ]);
    }
}
