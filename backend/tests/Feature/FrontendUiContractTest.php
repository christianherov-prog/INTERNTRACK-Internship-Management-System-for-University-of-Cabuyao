<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * PLACE-LOCK-03, MSG-ARCHIVE-01/02 (UI side). The frontend has no JS test
 * runner, so these assert the shipped component source renders from the
 * authoritative API fields (placement_lock.*, thread.user_archived) rather
 * than from local guesses such as which tab is open. Behaviour behind those
 * fields is covered by the HTTP tests in CompanyCleanupAndPlacementLockTest.
 */
class FrontendUiContractTest extends TestCase
{
    private function source(string $relative): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/frontend/src/'.$relative);
    }

    public function test_placement_hub_locks_other_companies_from_the_api_lock(): void
    {
        $src = $this->source('pages/student/StudentCompanies.jsx');

        $this->assertStringContainsString('const locked = Boolean(placementLock?.locked)', $src);
        $this->assertStringContainsString('<i className="fa fa-lock me-1"></i>Locked', $src);
        $this->assertStringContainsString('You already have an active company application. Choose Change Company to apply elsewhere.', $src);
        $this->assertStringContainsString('You already have an active internship placement.', $src);
        $this->assertStringContainsString('Your internship placement is already completed.', $src);
        $this->assertStringContainsString('title={lockNotice(placementLock.source)}', $src);
        // Locked buttons are visually distinct from the primary Send button.
        $this->assertMatchesRegularExpression('/className="btn btn-sm px-3 btn-outline-secondary"\s+disabled/', $src);
    }

    public function test_placement_hub_labels_current_company_by_application_state(): void
    {
        $src = $this->source('pages/student/StudentCompanies.jsx');

        $this->assertStringContainsString('Applied / Pending', $src);
        $this->assertStringContainsString('Accepted / Current Placement', $src);
        $this->assertStringContainsString('Completed Placement', $src);
        // Change Company is offered only for a pending application and calls the withdraw endpoint.
        $this->assertMatchesRegularExpression("/placementLock\.source === 'pending_application' && \(\s*<button[\s\S]*?Change Company/", $src);
        $this->assertStringContainsString('/student/applications/${placementLock.application_id}/withdraw', $src);
    }

    /** PLACE-ADV (UI side): no Faculty adviser → Send and Request New HTE are unavailable, with the API message. */
    public function test_placement_hub_blocks_actions_without_a_faculty_adviser(): void
    {
        $src = $this->source('pages/student/StudentCompanies.jsx');

        $this->assertStringContainsString('adviser: appRes.data.adviser || null', $src);
        $this->assertStringContainsString('const adviserMissing = adviser ? !adviser.assigned : false', $src);
        $this->assertStringContainsString('data-testid="adviser-required-banner"', $src);
        $this->assertMatchesRegularExpression('/!locked && adviserMissing \? \(\s*<button[\s\S]*?disabled[\s\S]*?Adviser required/', $src);
        $this->assertStringContainsString('disabled={submitting || placementLock?.locked || adviserMissing}', $src);
    }

    /** Only the PALD Director finalizes absorption, so the Coordinator page is read-only. */
    public function test_coordinator_absorption_page_is_read_only(): void
    {
        $page = $this->source('pages/coordinator/CoordAbsorption.jsx');
        $shared = $this->source('components/RoleAbsorption.jsx');

        $this->assertStringContainsString('canRecord={false}', $page);
        $this->assertStringContainsString('Absorption outcomes are finalized by the PALD Director.', $shared);
        // Confirm/Update and the modal only render when the role may record outcomes.
        $this->assertMatchesRegularExpression('/\{canRecord && \(\s*<td>\s*<button[\s\S]*?\'Confirm\' : \'Update\'/', $shared);
        $this->assertStringContainsString('{canRecord && modal && (', $shared);
        // The outcome badge is still shown for every row.
        $this->assertStringContainsString("<td><span className={badge(outcome)}>{outcome.replace('_', ' ')}</span></td>", $shared);
    }

    public function test_message_actions_follow_per_user_archive_state(): void
    {
        $src = $this->source('components/MessagesInbox.jsx');

        $this->assertStringContainsString('const isUserArchived = Boolean(thread.user_archived)', $src);
        $this->assertStringContainsString("aria-label={isUserArchived ? 'Unarchive conversation' : 'Archive conversation'}", $src);
        $this->assertStringContainsString("title={isUserArchived ? 'Unarchive conversation' : 'Archive conversation'}", $src);
        $this->assertStringContainsString("{activeUserArchived ? 'Unarchive' : 'Archive'}", $src);
        $this->assertStringContainsString('onToggleArchive(thread, !isUserArchived)', $src);
    }
}
