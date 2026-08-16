<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class CoordinatorOwnershipAclTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_coordinator_cannot_change_other_coordinators_internship_status(): void
    {
        $owner = $this->makeUser('coordinator', 'COR-OWNER');
        $other = $this->makeUser('coordinator', 'COR-OTHER');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $owner);

        Sanctum::actingAs($other);

        $this->patchJson("/api/v1/coordinator/internships/{$internship->id}/status", [
            'status' => 'completed',
            'reason' => 'Attempting to change another coordinator placement.',
        ])->assertForbidden();
    }

    public function test_logbook_lists_only_owned_internships(): void
    {
        $owner = $this->makeUser('coordinator', 'COR-OWNER');
        $other = $this->makeUser('coordinator', 'COR-OTHER');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $owner);

        JournalEntry::create([
            'internship_id' => $internship->id,
            'entry_number' => 1,
            'week_number' => 1,
            'date' => now()->toDateString(),
            'status' => 'submitted',
            'activities_summary' => 'Uploaded logbook file',
            'file_path' => 'journals/1/test.pdf',
        ]);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/coordinator/logbook')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/coordinator/logbook')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_coordinator_cannot_edit_others_announcement(): void
    {
        $owner = $this->makeUser('coordinator', 'COR-OWNER');
        $other = $this->makeUser('coordinator', 'COR-OTHER');

        $announcement = Announcement::create([
            'created_by' => $owner->id,
            'title' => 'Owner post',
            'content' => 'Only owner may edit',
            'target_role' => 'all',
            'is_pinned' => false,
        ]);

        Sanctum::actingAs($other);
        $this->putJson("/api/v1/coordinator/announcements/{$announcement->id}", [
            'title' => 'Hacked',
            'content' => 'Nope',
            'target_role' => 'all',
        ])->assertNotFound();
    }

    public function test_absorption_list_scoped_to_own_internships(): void
    {
        $owner = $this->makeUser('coordinator', 'COR-ABS-OWN');
        $other = $this->makeUser('coordinator', 'COR-ABS-OTH');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $owner);
        $internship->update([
            'status' => 'completed',
            'end_date' => now()->toDateString(),
            'absorption_status' => 'pending',
        ]);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/coordinator/absorption')
            ->assertOk()
            ->assertJsonCount(0, 'internships');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/coordinator/absorption')
            ->assertOk()
            ->assertJsonCount(1, 'internships')
            ->assertJsonPath('internships.0.id', $internship->id);
    }
}
