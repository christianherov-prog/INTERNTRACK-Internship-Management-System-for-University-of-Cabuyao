<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\InternshipApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * PLACE-LOCK-08/09 and the slot brief: a slot is consumed only when a
 * placement becomes occupying and is restored when it frees, so
 *
 *     available = capacity - occupying placements
 *
 * no matter how many applications were submitted, rejected or withdrawn.
 * Every scenario uses newly created Students and companies — the rule is
 * generic, not tied to any demo account.
 */
class PlacementSlotLifecycleTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function place(Internship $internship, Company $company, $faculty, $supervisor)
    {
        return $this->postJson("/api/v1/coordinator/internships/{$internship->id}/place", [
            'company_id' => $company->id,
            'faculty_id' => $faculty->id,
            'supervisor_id' => $supervisor->id,
        ]);
    }

    private function setStatus(Internship $internship, string $status)
    {
        return $this->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
            'status' => $status,
            'reason' => 'Automated slot lifecycle check',
        ]);
    }

    // ── PLACE-LOCK-09: a brand-new Student follows the same rule ───────

    public function test_new_student_is_locked_after_first_application_and_unlocked_after_withdrawal(): void
    {
        $student = $this->makeStudentWithSection('4ITA');
        $this->makePendingInternship($student);
        $first = $this->makeEligibleCompany(['company_name' => 'Future Co One']);
        $second = $this->makeEligibleCompany(['company_name' => 'Future Co Two']);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/applications')->assertOk()->assertJsonPath('placement_lock.locked', false);

        $this->postJson('/api/v1/student/applications', ['company_id' => $first->id])->assertOk();
        $this->postJson('/api/v1/student/applications', ['company_id' => $second->id])->assertStatus(409);
        $this->getJson('/api/v1/student/applications')->assertOk()
            ->assertJsonPath('placement_lock.locked', true)
            ->assertJsonPath('placement_lock.company_id', $first->id);

        $application = InternshipApplication::where('student_id', $student->id)->firstOrFail();
        $this->postJson("/api/v1/student/applications/{$application->id}/withdraw")->assertOk();
        $this->postJson('/api/v1/student/applications', ['company_id' => $second->id])->assertOk();
    }

    public function test_rejected_and_withdrawn_applications_never_lock_or_consume_slots(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $rejected = $this->makeEligibleCompany(['slots_available' => 4]);
        $withdrawn = $this->makeEligibleCompany(['slots_available' => 4]);
        $target = $this->makeEligibleCompany(['slots_available' => 4]);

        InternshipApplication::create(['student_id' => $student->id, 'company_id' => $rejected->id, 'status' => 'rejected']);
        InternshipApplication::create(['student_id' => $student->id, 'company_id' => $withdrawn->id, 'status' => 'withdrawn']);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/applications')->assertOk()->assertJsonPath('placement_lock.locked', false);
        $this->postJson('/api/v1/student/applications', ['company_id' => $target->id])->assertOk();

        foreach ([$rejected, $withdrawn, $target] as $company) {
            $this->assertSame(4, (int) $company->fresh()->slots_available);
        }
    }

    // ── Slot: consumed on placement, restored on cancel / completion ───

    public function test_slot_is_consumed_once_on_placement_and_never_double_decremented(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 3]);

        // A pending application (and repeating it) changes nothing.
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->assertSame(3, (int) $company->fresh()->slots_available);

        Sanctum::actingAs($coordinator);
        $this->place($internship, $company, $faculty, $supervisor)->assertOk();
        $this->assertSame(2, (int) $company->fresh()->slots_available);

        // Re-submitting the same placement must not consume another slot.
        $this->place($internship->fresh(), $company, $faculty, $supervisor);
        $this->assertSame(2, (int) $company->fresh()->slots_available);
        $this->assertSame(1, Internship::where('company_id', $company->id)->count());
    }

    public function test_institutional_status_change_restores_the_slot_but_student_cannot_self_replace(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 3]);
        $other = $this->makeEligibleCompany(['slots_available' => 3]);
        InternshipApplication::create(['student_id' => $student->id, 'company_id' => $company->id, 'status' => 'approved']);

        Sanctum::actingAs($coordinator);
        $this->place($internship, $company, $faculty, $supervisor)->assertOk();
        $this->assertSame(2, (int) $company->fresh()->slots_available);

        // The Student cannot replace an accepted placement themselves.
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $other->id])->assertStatus(409);

        // The institutional action (Coordinator defers the placement) restores the slot once.
        Sanctum::actingAs($coordinator);
        $this->setStatus($internship->fresh(), 'deferred')->assertOk();
        $this->assertSame(3, (int) $company->fresh()->slots_available);
        $this->setStatus($internship->fresh(), 'deferred')->assertStatus(422);
        $this->assertSame(3, (int) $company->fresh()->slots_available);
    }

    /** PLACE-LOCK-07: a finished OJT is never re-pointed to another company by the Student. */
    public function test_completed_internship_locks_applications_and_is_never_repointed(): void
    {
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $home = $this->makeEligibleCompany(['company_name' => 'Finished At Co']);
        $other = $this->makeEligibleCompany(['company_name' => 'Somewhere Else Co']);
        $internship = $this->makeActiveInternship($student, $home, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'completed']);

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/applications')->assertOk()
            ->assertJsonPath('placement_lock.locked', true)
            ->assertJsonPath('placement_lock.source', 'completed')
            ->assertJsonPath('placement_lock.company_id', $home->id);

        $this->postJson('/api/v1/student/applications', ['company_id' => $other->id])->assertStatus(409);

        $this->assertSame($home->id, (int) $internship->fresh()->company_id);
        $this->assertSame(0, InternshipApplication::where('student_id', $student->id)->where('company_id', $other->id)->count());
    }

    public function test_completed_internship_frees_its_slot_exactly_once(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 2]);

        Sanctum::actingAs($coordinator);
        $this->place($internship, $company, $faculty, $supervisor)->assertOk();
        $this->assertSame(1, (int) $company->fresh()->slots_available);

        $this->setStatus($internship->fresh(), 'completed')->assertOk();
        $this->assertSame(2, (int) $company->fresh()->slots_available);

        // Repeating the same transition is refused and must not release a second slot.
        $this->setStatus($internship->fresh(), 'completed')->assertStatus(422);
        $this->assertSame(2, (int) $company->fresh()->slots_available);
    }
}
