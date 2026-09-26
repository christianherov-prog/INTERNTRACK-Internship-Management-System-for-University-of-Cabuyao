<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\Message;
use App\Models\MessageThreadState;
use App\Support\CompanyNameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Covers the generic (non-Student-specific) fixes from the company
 * cleanup / placement locking / messages archive / certificate banner
 * brief: COMPANY-*, PLACE-LOCK-*, MSG-ARCHIVE-*.
 */
class CompanyCleanupAndPlacementLockTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    // ── COMPANY-01 / COMPANY-02: normalized matching ───────────────────

    public function test_normalizer_matches_case_and_whitespace_variants(): void
    {
        Company::create(['company_name' => 'Accenture Philippines', 'moa_status' => 'active', 'is_active' => true, 'slots_available' => 5]);

        $variants = ['accenture philippines', ' Accenture   Philippines ', 'ACCENTURE PHILIPPINES', "Accenture\tPhilippines"];
        foreach ($variants as $variant) {
            $found = CompanyNameNormalizer::findExisting($variant);
            $this->assertNotNull($found, "Expected to match variant: {$variant}");
            $this->assertSame('Accenture Philippines', $found->company_name);
        }
    }

    public function test_normalizer_does_not_merge_unrelated_companies(): void
    {
        Company::create(['company_name' => 'Accenture Philippines', 'moa_status' => 'active', 'is_active' => true, 'slots_available' => 5]);

        $this->assertNull(CompanyNameNormalizer::findExisting('Accent Philippines Trading Co'));
        $this->assertNull(CompanyNameNormalizer::findExisting('Infor'));
    }

    // ── COMPANY-03/04/05 (generic mechanism): retired companies excluded ──

    public function test_soft_deleted_company_is_excluded_from_student_eligible_list(): void
    {
        $keep = $this->makeEligibleCompany(['company_name' => 'Keep Me Corp']);
        $retired = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);
        $retired->delete(); // soft delete, mirrors how obsolete demo companies were retired

        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $companies = $this->getJson('/api/v1/student/companies')->assertOk()->json('companies');
        $names = collect($companies)->pluck('company_name')->all();

        $this->assertContains('Keep Me Corp', $names);
        $this->assertNotContains('TechCorp PH', $names);
    }

    // ── COMPANY-06: count comes from real rows, not a hardcoded number ──

    public function test_eligible_company_count_reflects_real_active_rows(): void
    {
        $student = $this->makeStudentWithSection();
        Sanctum::actingAs($student);

        $this->makeEligibleCompany();
        $this->makeEligibleCompany();
        $this->makeEligibleCompany(['moa_status' => 'on-process']); // ineligible, must not count

        $companies = $this->getJson('/api/v1/student/companies')->assertOk()->json('companies');
        $this->assertCount(2, $companies);
    }

    // ── COMPANY-07: HTE approval reuses existing company for a name variant ──

    public function test_hte_request_approval_reuses_existing_company_for_name_variant(): void
    {
        $company = $this->makeEligibleCompany(['company_name' => 'Wipro Philippines']);
        $coordinator = $this->makeUser('coordinator');
        $student = $this->makeStudentWithSection();

        $req = HteRequest::create([
            'student_id' => $student->id,
            'company_name' => '  wipro   philippines ', // whitespace/case variant of an existing company
            'address' => 'BGC, Taguig',
            'organization_type' => 'Private',
            'contact_person' => 'HR',
            'contact_email' => 'hr@wipro.example',
            'contact_number' => '0917123456789',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($coordinator);
        $this->patchJson("/api/v1/coordinator/hte-requests/{$req->id}/status", ['status' => 'approved'])
            ->assertOk();

        $this->assertSame(1, Company::where('id', $company->id)->count());
        $this->assertSame(1, Company::query()->get()->filter(
            fn ($c) => CompanyNameNormalizer::normalize($c->company_name) === CompanyNameNormalizer::normalize('Wipro Philippines')
        )->count());
    }

    public function test_director_cannot_create_duplicate_company_by_name_variant(): void
    {
        $this->makeEligibleCompany(['company_name' => 'Cognizant Philippines']);
        $director = $this->makeUser('director');

        Sanctum::actingAs($director);
        $this->postJson('/api/v1/director/companies', [
            'company_name' => 'COGNIZANT   PHILIPPINES',
            'moa_status' => 'active',
        ])->assertStatus(422);

        $this->assertSame(1, Company::query()->get()->filter(
            fn ($c) => CompanyNameNormalizer::normalize($c->company_name) === CompanyNameNormalizer::normalize('Cognizant Philippines')
        )->count());
    }

    // ── PLACE-LOCK-01: no active application → may apply ───────────────

    public function test_student_with_no_active_application_can_apply(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany();

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])
            ->assertOk();

        $this->assertDatabaseHas('internship_applications', [
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
    }

    // ── PLACE-LOCK-02 / PLACE-LOCK-03 / PLACE-LOCK-04 ──────────────────

    public function test_pending_application_locks_other_companies_and_backend_rejects_direct_post(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $companyA = $this->makeEligibleCompany(['company_name' => 'Company A']);
        $companyB = $this->makeEligibleCompany(['company_name' => 'Company B']);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $companyA->id])->assertOk();

        // PLACE-LOCK-03 equivalent: the authoritative lock the frontend renders from.
        $apps = $this->getJson('/api/v1/student/applications')->assertOk();
        $this->assertTrue($apps->json('placement_lock.locked'));
        $this->assertSame('pending_application', $apps->json('placement_lock.source'));
        $this->assertSame($companyA->id, $apps->json('placement_lock.company_id'));

        // PLACE-LOCK-04: direct backend POST to a different company is rejected.
        $this->postJson('/api/v1/student/applications', ['company_id' => $companyB->id])
            ->assertStatus(409);

        $this->assertDatabaseMissing('internship_applications', [
            'student_id' => $student->id,
            'company_id' => $companyB->id,
        ]);
    }

    // ── PLACE-LOCK-05 / PLACE-LOCK-06: withdraw then apply elsewhere ───

    public function test_pending_application_can_be_withdrawn_then_student_can_apply_elsewhere(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $companyA = $this->makeEligibleCompany(['company_name' => 'Company A']);
        $companyB = $this->makeEligibleCompany(['company_name' => 'Company B']);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $companyA->id])->assertOk();
        $application = InternshipApplication::where('student_id', $student->id)->where('company_id', $companyA->id)->firstOrFail();

        $this->postJson("/api/v1/student/applications/{$application->id}/withdraw")->assertOk();

        $this->assertSame('withdrawn', $application->fresh()->status);

        $lock = $this->getJson('/api/v1/student/applications')->assertOk();
        $this->assertFalse($lock->json('placement_lock.locked'));

        $this->postJson('/api/v1/student/applications', ['company_id' => $companyB->id])
            ->assertOk();
        $this->assertDatabaseHas('internship_applications', [
            'student_id' => $student->id,
            'company_id' => $companyB->id,
            'status' => 'pending',
        ]);
    }

    // ── PLACE-LOCK-07: accepted placement cannot be casually overwritten ──

    public function test_accepted_placement_cannot_be_withdrawn_via_change_company(): void
    {
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $application = InternshipApplication::create([
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($student);
        $this->postJson("/api/v1/student/applications/{$application->id}/withdraw")
            ->assertStatus(422);

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('active', Internship::where('student_id', $student->id)->first()->status);

        // Direct application to another company is also still rejected.
        $otherCompany = $this->makeEligibleCompany(['company_name' => 'Other Co']);
        $this->postJson('/api/v1/student/applications', ['company_id' => $otherCompany->id])
            ->assertStatus(409);
    }

    // ── PLACE-LOCK-08: duplicate submission does not double-consume a slot ──

    public function test_duplicate_submission_to_same_company_does_not_double_consume_slot(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 5]);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();

        $this->assertSame(1, InternshipApplication::where('student_id', $student->id)->where('company_id', $company->id)->count());
        // A pending application never consumes a slot (only acceptance/placement does).
        $this->assertSame(5, $company->fresh()->slots_available);
    }

    // ── Slot correctness: pending never consumes; only real acceptance does ──

    public function test_slot_only_consumed_on_acceptance_not_on_pending_application(): void
    {
        $student = $this->makeStudentWithSection();
        $this->makePendingInternship($student);
        $company = $this->makeEligibleCompany(['slots_available' => 5]);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $company->id])->assertOk();

        $this->assertSame(5, $company->fresh()->slots_available, 'A pending application must not consume a slot.');
    }

    // ── MSG-ARCHIVE tests ───────────────────────────────────────────────

    private function makeConversationFixture(): array
    {
        $company = $this->makeEligibleCompany();
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Message::create([
            'internship_id' => $internship->id,
            'sender_id' => $student->id,
            'sender_role' => 'student',
            'recipient_id' => $faculty->id,
            'recipient_role' => 'faculty',
            'body' => 'Hello faculty',
        ]);

        return [$student, $faculty, $internship];
    }

    public function test_active_conversation_reports_user_archived_false(): void
    {
        [$student, $faculty, $internship] = $this->makeConversationFixture();

        Sanctum::actingAs($faculty);
        $threads = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $thread = $this->findThread($threads, $internship->id, $student->id);

        $this->assertNotNull($thread);
        $this->assertFalse($thread['user_archived']);
    }

    /** A thread is identified by (internship_id, peer.id) — an internship
     * can have several synthetic peer pairings (student/supervisor/faculty/
     * coordinator), so matching by internship_id alone can pick up an
     * unrelated pairing. */
    private function findThread(array $threads, int $internshipId, int $peerId): ?array
    {
        return collect($threads)->first(
            fn ($t) => (int) $t['internship_id'] === $internshipId && (int) $t['peer']['id'] === $peerId
        );
    }

    public function test_archived_conversation_reports_user_archived_true(): void
    {
        [$student, $faculty, $internship] = $this->makeConversationFixture();

        Sanctum::actingAs($faculty);
        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => true])
            ->assertOk();

        $active = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $this->assertNull($this->findThread($active, $internship->id, $student->id));

        $archived = $this->getJson('/api/v1/messages/conversations?archived=1')->assertOk()->json('data');
        $thread = $this->findThread($archived, $internship->id, $student->id);
        $this->assertNotNull($thread);
        $this->assertTrue($thread['user_archived']);
    }

    public function test_unarchive_moves_conversation_back_to_active_and_preserves_messages(): void
    {
        [$student, $faculty, $internship] = $this->makeConversationFixture();

        Sanctum::actingAs($faculty);
        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => true])->assertOk();
        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => false])->assertOk();

        $active = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $thread = $this->findThread($active, $internship->id, $student->id);
        $this->assertNotNull($thread);
        $this->assertFalse($thread['user_archived']);
        $this->assertNotNull($thread['last_message']);
        $this->assertSame('Hello faculty', $thread['last_message']['body'] ?? $thread['last_message']['preview'] ?? null);
    }

    /** Regression test for the exact reported bug: an ended internship must
     * not force a never-explicitly-archived conversation into the Archived
     * tab (which desynced the Unarchive/Archive button from the tab). */
    public function test_ended_internship_thread_without_explicit_archive_stays_in_active_tab(): void
    {
        $company = $this->makeEligibleCompany();
        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'completed']);

        Message::create([
            'internship_id' => $internship->id,
            'sender_id' => $student->id,
            'sender_role' => 'student',
            'recipient_id' => $faculty->id,
            'recipient_role' => 'faculty',
            'body' => 'Thanks for everything!',
        ]);

        Sanctum::actingAs($faculty);
        $active = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $thread = $this->findThread($active, $internship->id, $student->id);

        $this->assertNotNull($thread, 'A conversation the user never archived must remain in Active even after the internship ends.');
        $this->assertFalse($thread['user_archived']);
    }

    /** MSG-ARCHIVE-06: the Archive/Unarchive action follows the persisted per-user state across refreshes. */
    public function test_archive_state_survives_refresh_for_ended_and_live_internships(): void
    {
        [$student, $faculty, $internship] = $this->makeConversationFixture();
        $internship->update(['status' => 'completed']);

        Sanctum::actingAs($faculty);
        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => true])->assertOk();

        foreach ([1, 2] as $refresh) {
            $archived = $this->getJson('/api/v1/messages/conversations?archived=1')->assertOk()->json('data');
            $thread = $this->findThread($archived, $internship->id, $student->id);
            $this->assertNotNull($thread, "Refresh {$refresh}: archived thread must stay in the Archived tab.");
            $this->assertTrue($thread['user_archived'], "Refresh {$refresh}: action must read Unarchive.");
        }

        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => false])->assertOk();

        $archived = $this->getJson('/api/v1/messages/conversations?archived=1')->assertOk()->json('data');
        $this->assertNull($this->findThread($archived, $internship->id, $student->id));
        $active = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $this->assertFalse($this->findThread($active, $internship->id, $student->id)['user_archived']);
    }

    public function test_other_participant_archive_state_is_independent(): void
    {
        [$student, $faculty, $internship] = $this->makeConversationFixture();

        Sanctum::actingAs($faculty);
        $this->postJson("/api/v1/messages/conversations/{$internship->id}/{$student->id}/archive", ['archived' => true])->assertOk();

        Sanctum::actingAs($student);
        $studentActive = $this->getJson('/api/v1/messages/conversations?archived=0')->assertOk()->json('data');
        $thread = $this->findThread($studentActive, $internship->id, $faculty->id);

        $this->assertNotNull($thread, "The student's own view must be unaffected by the faculty archiving their side.");
        $this->assertFalse($thread['user_archived']);
    }
}
