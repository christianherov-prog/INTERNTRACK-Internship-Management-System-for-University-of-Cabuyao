<?php

namespace Tests\Feature;

use App\Mail\AccountLockedMail;
use App\Mail\PasswordChangeMail;
use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalDeadline;
use App\Models\JournalEntry;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\AuthService;
use App\Services\OfficialFormDataService;
use App\Support\AuthCookie;
use App\Support\InternshipProvisioning;
use App\Support\PlacementEligibility;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the final functional, security, and workflow changes.
 * Every account is created fresh by the fixtures, so these cases also show the
 * behavior works for new Students, Faculty, Supervisors, and Directors.
 */
class FinalWorkflowImprovementsTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private const SPA = ['X-Requested-With' => 'XMLHttpRequest'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function party(string $section = '4ITD'): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Industry',
            'last_name' => 'Reviewer',
            'position' => 'IT Supervisor',
            'email' => $supervisor->email,
        ]);
        $student = $this->makeStudentWithSection($section);
        $company = $this->makeEligibleCompany(['company_name' => 'Deploy HTE '.$student->id]);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'start_date' => '2026-06-01',
            'end_date' => '2026-09-30',
        ]);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    private function userWithPassword(string $role, string $password = 'Correct#Pass1'): User
    {
        $user = $this->makeUser($role);
        $user->forceFill(['password' => Hash::make($password), 'is_active' => true, 'email' => "{$role}.{$user->id}@example.com"])->save();

        return $user->fresh();
    }

    private function loginIdFor(User $user): string
    {
        return $user->student_number ?: $user->faculty_number;
    }

    private function submitSupervisorForm(array $party, string $formType): Evaluation
    {
        $this->approveEvaluationPeriod($party['internship'], $party['faculty']);
        Sanctum::actingAs($party['supervisor']);
        $responses = $formType === 'FO-24'
            ? ['c1' => 90, 'c2' => 88, 'c3' => 88, 'c4' => 88, 'c5' => 88, 'c6' => 88, 'c7' => 88, 'c8' => 88, 'c9' => 88, 'c10' => 88, 'recommendations' => 'Private supervisor note']
            : ['crit_0' => 5, 'would_hire' => 'yes', 'comments' => 'Private HTE program note'];
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => $formType,
            'responses' => $responses,
            'general_comments' => 'Confidential comment '.$formType,
        ])->assertCreated();

        return Evaluation::where('internship_id', $party['internship']->id)->where('form_type', $formType)->firstOrFail();
    }

    // ── Session (AUTH-SESSION-01) ────────────────────────────────────────────

    public function test_auth_session_01_cookie_session_is_shared_by_a_new_tab_and_ends_on_logout(): void
    {
        $user = $this->userWithPassword('student');

        $login = $this->withHeaders(self::SPA)->postJson('/api/v1/auth/login', [
            'username' => $this->loginIdFor($user),
            'password' => 'Correct#Pass1',
        ])->assertOk();

        $this->assertArrayNotHasKey('token', $login->json(), 'The SPA never receives the token in JavaScript.');
        $cookie = $login->getCookie(AuthCookie::name(), false);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $token = $cookie->getValue();

        // "New tab": a fresh request carrying only the browser cookie.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withCredentials()->withUnencryptedCookie(AuthCookie::name(), $token)->withHeaders(self::SPA)
            ->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('user.id', $user->id);

        // Without the SPA header the cookie is ignored (cross-site forgery guard).
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withCredentials()->withUnencryptedCookie(AuthCookie::name(), $token)
            ->getJson('/api/v1/auth/user')->assertUnauthorized();

        // Logout in one tab revokes the shared session for every tab.
        $this->app['auth']->forgetGuards();
        $logout = $this->withCredentials()->withUnencryptedCookie(AuthCookie::name(), $token)->withHeaders(self::SPA)
            ->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertTrue($logout->getCookie(AuthCookie::name(), false)->isCleared());
        $this->assertNull(PersonalAccessToken::find((int) explode('|', $token, 2)[0]));

        $this->app['auth']->forgetGuards();
        $this->withCredentials()->withUnencryptedCookie(AuthCookie::name(), $token)->withHeaders(self::SPA)
            ->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_auth_session_02_deactivated_account_token_is_rejected_on_next_request(): void
    {
        $user = $this->userWithPassword('faculty');
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/user')->assertOk();

        $user->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }

    // ── Forgot Password (AUTH-FORGOT-01..03) ─────────────────────────────────

    public function test_auth_forgot_01_supervisor_receives_reset_link(): void
    {
        Mail::fake();
        $supervisor = $this->userWithPassword('supervisor');
        $supervisor->forceFill(['login_username' => 'hte.forgot'])->save();

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'hte.forgot'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $supervisor->email]);
        Mail::assertSent(PasswordChangeMail::class, fn ($mail) => $mail->hasTo($supervisor->email));
    }

    public function test_auth_forgot_02_and_03_internal_roles_cannot_use_forgot_password(): void
    {
        // Role rules are under test here, not the per-minute throttle.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Mail::fake();
        foreach (['student', 'faculty', 'coordinator', 'director', 'admin'] as $role) {
            $user = $this->userWithPassword($role);
            $identifiers = array_filter([$this->loginIdFor($user), $user->email]);
            foreach ($identifiers as $identifier) {
                $this->postJson('/api/v1/auth/forgot-password', ['identifier' => $identifier])
                    ->assertOk()->assertJsonPath('success', true);
            }
            $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

            // A token planted for an internal account is refused at confirmation too.
            DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => Hash::make('planted'), 'created_at' => now()]);
            $this->postJson('/api/v1/auth/confirm-password-change', [
                'email' => $user->email,
                'token' => 'planted',
                'new_password' => 'Brand#New123',
                'new_password_confirmation' => 'Brand#New123',
            ])->assertStatus(422);
            $this->assertTrue(Hash::check('Correct#Pass1', $user->fresh()->password), "{$role} password must be unchanged");
        }
        Mail::assertNothingSent();
    }

    public function test_locked_supervisor_is_not_unlocked_through_forgot_password(): void
    {
        Mail::fake();
        $supervisor = $this->userWithPassword('supervisor');
        $supervisor->forceFill(['login_username' => 'hte.locked', 'locked_at' => now(), 'failed_login_attempts' => 3])->save();

        $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'hte.locked'])->assertOk();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $supervisor->email]);
        $this->assertNotNull($supervisor->fresh()->locked_at);
    }

    // ── Lockout (AUTH-LOCK-01..07) ───────────────────────────────────────────

    public function test_auth_lock_01_to_05_three_failures_lock_and_email_the_owner(): void
    {
        Mail::fake();
        $user = $this->userWithPassword('student');
        $id = $this->loginIdFor($user);

        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'wrong-1'])->assertStatus(422);
        $this->assertSame(1, (int) $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->locked_at);

        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'wrong-2'])->assertStatus(422);
        $this->assertSame(2, (int) $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->locked_at);

        $third = $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'wrong-3'])->assertStatus(422);
        $this->assertStringContainsString('Invalid credentials', $third->json('message'));
        $this->assertNotNull($user->fresh()->locked_at);

        // AUTH-LOCK-04: the correct password is refused while locked.
        $locked = $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'Correct#Pass1'])->assertStatus(422);
        $this->assertSame(AuthService::LOCKED_MESSAGE, $locked->json('message'));
        $this->assertSame(0, $user->fresh()->tokens()->count());

        // AUTH-LOCK-05: one email to the account owner, without secrets.
        Mail::assertSent(AccountLockedMail::class, 1);
        Mail::assertSent(AccountLockedMail::class, function (AccountLockedMail $mail) use ($user) {
            $html = $mail->render();
            $this->assertStringNotContainsString('Correct#Pass1', $html);
            $this->assertStringNotContainsString('wrong-3', $html);
            $this->assertStringNotContainsString('token', strtolower($html));
            $this->assertStringContainsString('(Asia/Manila)', $html);

            return $mail->hasTo($user->email);
        });
    }

    public function test_auth_lock_06_success_before_third_failure_resets_the_counter(): void
    {
        $user = $this->userWithPassword('faculty');
        $id = $this->loginIdFor($user);

        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'bad'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'bad'])->assertStatus(422);
        $this->assertSame(2, (int) $user->fresh()->failed_login_attempts);

        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'Correct#Pass1'])->assertOk();
        $this->assertSame(0, (int) $user->fresh()->failed_login_attempts);

        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'bad'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['username' => $id, 'password' => 'bad'])->assertStatus(422);
        $this->assertNull($user->fresh()->locked_at, 'Two failures after a reset must not lock the account.');
    }

    public function test_auth_lock_07_attempts_on_locked_account_do_not_resend_email_and_admin_unlocks(): void
    {
        Mail::fake();
        $user = $this->userWithPassword('supervisor');
        $user->forceFill(['login_username' => 'hte.lock7'])->save();

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => 'hte.lock7', 'password' => 'nope'])->assertStatus(422);
        }
        Mail::assertSent(AccountLockedMail::class, 1);
        $this->assertSame(3, (int) $user->fresh()->failed_login_attempts);

        // Only Admin/MISD may unlock.
        Sanctum::actingAs($this->makeUser('coordinator'));
        $this->postJson("/api/v1/admin/users/{$user->id}/unlock")->assertForbidden();

        Sanctum::actingAs($this->makeUser('admin'));
        $this->getJson('/api/v1/admin/users?search='.urlencode($user->email))->assertOk();
        $this->postJson("/api/v1/admin/users/{$user->id}/unlock")->assertOk();
        $this->assertNull($user->fresh()->locked_at);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['username' => 'hte.lock7', 'password' => 'Correct#Pass1'])->assertOk();
    }

    public function test_unknown_account_gets_the_same_response_as_a_wrong_password(): void
    {
        $user = $this->userWithPassword('supervisor');
        $user->forceFill(['login_username' => 'hte.enum'])->save();

        $unknown = $this->postJson('/api/v1/auth/login', ['username' => 'nobody@example.com', 'password' => 'x'])->assertStatus(422);
        $wrong = $this->postJson('/api/v1/auth/login', ['username' => 'hte.enum', 'password' => 'x'])->assertStatus(422);

        $this->assertSame($unknown->json('message'), $wrong->json('message'));
    }

    // ── Student Performance Evaluation (EVAL-STUDENT-01..04) ─────────────────

    public function test_eval_student_01_to_04_fo24_details_hidden_until_assigned_faculty_releases(): void
    {
        $party = $this->party();
        $fo24 = $this->submitSupervisorForm($party, 'FO-24');

        Sanctum::actingAs($party['student']);
        $row = collect($this->getJson('/api/v1/student/evaluations')->assertOk()->json('data'))->firstWhere('form_type', 'FO-24');
        $this->assertSame('completed', $row['status']);
        $this->assertTrue($row['details_locked']);
        foreach (['responses', 'average_score', 'total_score', 'rating', 'general_comments', 'signature_path'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $row);
        }

        // Every other student-facing path is redacted too.
        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.evaluations');
        $this->assertTrue(collect($portfolio)->firstWhere('form_type', 'FO-24')['details_locked']);
        $bundle = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk();
        $this->assertStringNotContainsString('Confidential comment FO-24', $bundle->getContent());
        $this->assertStringNotContainsString('Private supervisor note', $bundle->getContent());
        $this->assertStringNotContainsString('Confidential comment FO-24', $this->getJson('/api/v1/student/evaluations?internship_id='.$party['internship']->id)->getContent());
        $stats = $this->getJson('/api/v1/student/dashboard')->assertOk()->json('stats');
        $this->assertArrayHasKey('evaluation_score', $stats);
        $this->assertNull($stats['evaluation_score'], 'Unreleased FO-24 scores must not feed the student dashboard.');

        // Staff still see the details.
        Sanctum::actingAs($party['faculty']);
        $facultyRows = $this->getJson('/api/v1/faculty/evaluations')->assertOk()->json('internships');
        $this->assertStringContainsString('Confidential comment FO-24', json_encode($facultyRows));

        // An unassigned faculty cannot release it.
        Sanctum::actingAs($this->makeUser('faculty'));
        $this->postJson('/api/v1/faculty/evaluations/'.$party['internship']->id.'/release-performance')->assertForbidden();
        $this->assertNull($fo24->fresh()->released_to_student_at);

        // EVAL-STUDENT-03: assigned faculty releases.
        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$party['internship']->id.'/release-performance')
            ->assertOk()->assertJsonPath('released', true);
        $this->assertNotNull($fo24->fresh()->released_to_student_at);
        $this->assertSame($party['faculty']->id, (int) $fo24->fresh()->released_to_student_by);

        // EVAL-STUDENT-04: student now sees the details.
        Sanctum::actingAs($party['student']);
        $row = collect($this->getJson('/api/v1/student/evaluations')->assertOk()->json('data'))->firstWhere('form_type', 'FO-24');
        $this->assertFalse($row['details_locked']);
        $this->assertSame('Confidential comment FO-24', $row['general_comments']);
        $this->assertEquals(88, (int) $row['responses']['c2']);
    }

    public function test_supervisor_revision_hides_a_released_fo24_again(): void
    {
        $party = $this->party();
        $fo24 = $this->submitSupervisorForm($party, 'FO-24');
        $fo24->forceFill(['released_to_student_at' => now(), 'released_to_student_by' => $party['faculty']->id])->save();

        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => ['c1' => 70, 'c2' => 70, 'c3' => 70, 'c4' => 70, 'c5' => 70, 'c6' => 70, 'c7' => 70, 'c8' => 70, 'c9' => 70, 'c10' => 70],
        ])->assertCreated();

        $this->assertNull($fo24->fresh()->released_to_student_at);
    }

    // ── HTE Evaluation (HTE-EVAL-01..03) ─────────────────────────────────────

    public function test_hte_eval_01_to_03_fo03_details_hidden_until_director_releases(): void
    {
        $party = $this->party();
        $fo03 = $this->submitSupervisorForm($party, 'FO-03');
        $fo24 = $this->submitSupervisorForm($party, 'FO-24');

        Sanctum::actingAs($party['student']);
        $row = collect($this->getJson('/api/v1/student/evaluations')->json('data'))->firstWhere('form_type', 'FO-03');
        $this->assertTrue($row['details_locked']);
        $this->assertSame('director', $row['release_authority']);
        $this->assertArrayNotHasKey('responses', $row);

        // Faculty and students cannot use the Director release; FO-24 is not a Director release.
        Sanctum::actingAs($party['faculty']);
        $this->postJson("/api/v1/director/evaluations/{$fo03->id}/release")->assertForbidden();
        Sanctum::actingAs($party['student']);
        $this->postJson("/api/v1/director/evaluations/{$fo03->id}/release")->assertForbidden();
        $director = $this->makeUser('director');
        Sanctum::actingAs($director);
        $this->postJson("/api/v1/director/evaluations/{$fo24->id}/release")->assertStatus(422);

        // HTE-EVAL-02: Director releases FO-03.
        $this->postJson("/api/v1/director/evaluations/{$fo03->id}/release")->assertOk()->assertJsonPath('released', true);

        // HTE-EVAL-03: student sees FO-03, while FO-24 stays locked (separate authority).
        Sanctum::actingAs($party['student']);
        $rows = collect($this->getJson('/api/v1/student/evaluations')->json('data'));
        $this->assertFalse($rows->firstWhere('form_type', 'FO-03')['details_locked']);
        $this->assertSame('yes', $rows->firstWhere('form_type', 'FO-03')['responses']['would_hire']);
        $this->assertTrue($rows->firstWhere('form_type', 'FO-24')['details_locked']);
    }

    // ── Coordinator section (SECTION-01) ─────────────────────────────────────

    public function test_section_01_coordinator_cannot_change_student_section(): void
    {
        $party = $this->party('4ITB');
        Sanctum::actingAs($party['coordinator']);

        $this->patchJson("/api/v1/coordinator/students/{$party['student']->id}/section", ['section' => '4ITA'])
            ->assertStatus(404);
        $this->patchJson('/api/v1/coordinator/students/bulk-section', ['student_ids' => [$party['student']->id], 'section' => '4ITA'])
            ->assertStatus(404);

        $this->assertSame('4ITB', $party['student']->fresh()->studentProfile->section);
        $roster = file_get_contents(base_path('../frontend/src/pages/coordinator/CoordRecords.jsx'));
        $this->assertStringNotContainsString('Change Section', $roster);
        $this->assertStringNotContainsString('Update Section', $roster);
        $this->assertStringNotContainsString('/section`', $roster);
    }

    // ── FO-30 / FO-31 logo (FORM-LOGO-01/02) ─────────────────────────────────

    public function test_form_logo_01_and_02_fo30_and_fo31_have_no_hte_logo(): void
    {
        $party = $this->party();
        $service = app(OfficialFormDataService::class);

        $dtr = $service->pdfDtr($party['internship']);
        $this->assertArrayNotHasKey('company_logo', $dtr);
        $html = view('pdf.form30_dtr', $dtr)->render();
        $this->assertStringNotContainsString('HTE Logo', $html);
        $this->assertStringNotContainsString('Logo<br>of<br>HTE', $html);
        $this->assertStringContainsString('UNIVERSITY OF CABUYAO', $html);
        $this->assertStringContainsString('STUDENT INTERNSHIP DAILY TIME RECORD', $html);

        JournalEntry::create([
            'internship_id' => $party['internship']->id, 'week_number' => 1, 'entry_number' => 1,
            'date' => '2026-06-01', 'end_date' => '2026-06-05', 'activities_summary' => 'Setup', 'status' => 'approved',
        ]);
        $journal = $service->pdfJournal($party['internship']);
        $this->assertArrayNotHasKey('company_logo', $journal);
        $html = view('pdf.form31_journal', $journal)->render();
        $this->assertStringNotContainsString('HTE Logo', $html);
        $this->assertStringNotContainsString('Logo<br>of<br>HTE', $html);
        $this->assertStringContainsString('WEEKLY STUDENT INTERNSHIP JOURNAL', $html);

        foreach (['DailyTimeRecord.jsx', 'WeeklyInternshipJournal.jsx'] as $component) {
            $src = file_get_contents(base_path('../frontend/src/components/portfolio/'.$component));
            $this->assertStringNotContainsString('alt="HTE Logo"', $src, $component);
            $this->assertStringNotContainsString('Logo<br />of<br />HTE', $src, $component);
            $this->assertStringContainsString('hte-logo-slot', $src, $component);
        }
    }

    // ── Placement lock (PLACEMENT-LOCK-01..03) ───────────────────────────────

    public function test_placement_lock_01_to_03_accepted_student_cannot_apply_elsewhere(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $student = $this->makeStudentWithSection('4ITA');
        $this->makePendingInternship($student);
        $companyA = $this->makeEligibleCompany(['company_name' => 'Alpha HTE']);
        $companyB = $this->makeEligibleCompany(['company_name' => 'Bravo HTE']);

        // PLACEMENT-LOCK-01: a student without an accepted placement applies normally.
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/applications', ['company_id' => $companyA->id])->assertSuccessful();
        $this->assertFalse($this->getJson('/api/v1/student/applications')->json('placement_lock.locked'));

        Sanctum::actingAs($coordinator);
        $application = InternshipApplication::where('student_id', $student->id)->where('company_id', $companyA->id)->firstOrFail();
        $this->patchJson("/api/v1/coordinator/applications/{$application->id}/status", ['status' => 'approved'])->assertOk();

        // PLACEMENT-LOCK-02/03: locked in the listing and rejected by the API.
        Sanctum::actingAs($student);
        $lock = $this->getJson('/api/v1/student/applications')->assertOk()->json('placement_lock');
        $this->assertTrue($lock['locked']);
        $this->assertSame($companyA->id, $lock['company_id']);
        $this->assertSame(PlacementEligibility::LOCK_MESSAGE, $lock['message']);

        $this->postJson('/api/v1/student/applications', ['company_id' => $companyB->id])
            ->assertStatus(409)->assertJsonPath('message', PlacementEligibility::LOCK_MESSAGE);
        $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Charlie HTE', 'address' => 'Cabuyao, Laguna', 'contact_person' => 'Juan Dela Cruz',
            'contact_email' => 'hr@charlie.test', 'contact_number' => '09171234567',
        ])->assertStatus(409);
        $this->assertSame(0, InternshipApplication::where('student_id', $student->id)->where('company_id', $companyB->id)->count());

        // Cancelled placement: eligibility is recomputed from the authoritative status.
        Internship::where('student_id', $student->id)->update(['status' => 'cancelled']);
        Carbon::setTestNow(now()->addMinutes(5));
        $this->postJson('/api/v1/student/applications', ['company_id' => $companyB->id])->assertSuccessful();
    }

    // ── Journal deadlines (JOURNAL-DEADLINE-01..04) ──────────────────────────

    public function test_journal_deadline_01_to_04(): void
    {
        $party = $this->party();
        $other = $this->party('4ITA');
        Carbon::setTestNow(Carbon::parse('2026-06-10 02:00:00', 'UTC')); // 10:00 Manila

        // JOURNAL-DEADLINE-02: unassigned faculty (or a mixed list) cannot set it; nothing is saved.
        Sanctum::actingAs($other['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id], 'week_number' => 1, 'due_at' => '2026-06-12T23:59',
        ])->assertForbidden();
        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id, $other['internship']->id], 'week_number' => 1, 'due_at' => '2026-06-12T23:59',
        ])->assertForbidden();
        $this->assertSame(0, JournalDeadline::count());

        // JOURNAL-DEADLINE-01: assigned faculty sets it (Asia/Manila input, stored in UTC).
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id], 'week_number' => 1, 'due_at' => '2026-06-12T23:59',
        ])->assertOk()->assertJsonPath('data.0.due_at_display', 'June 12, 2026, 11:59 PM');
        $this->assertSame('2026-06-12 15:59:00', JournalDeadline::first()->due_at->utc()->format('Y-m-d H:i:s'));
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id], 'week_number' => 61, 'due_at' => '2026-06-12T23:59',
        ])->assertStatus(422);

        // JOURNAL-DEADLINE-03: the student receives the deadline.
        Sanctum::actingAs($party['student']);
        $deadlines = $this->getJson('/api/v1/student/logbook')->assertOk()->json('journal_deadlines');
        $this->assertCount(1, $deadlines);
        $this->assertSame(1, $deadlines[0]['week_number']);
        $this->assertSame('June 12, 2026, 11:59 PM', $deadlines[0]['due_at_display']);

        // JOURNAL-DEADLINE-04: on-time submission keeps its real timestamp.
        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-06-01', 'end_date' => '2026-06-05', 'activities_summary' => 'Week one work',
        ])->assertCreated();
        $week1 = JournalEntry::where('internship_id', $party['internship']->id)->where('week_number', 1)->firstOrFail();
        $this->assertFalse($week1->submitted_late);
        $this->assertSame('2026-06-10 02:00:00', $week1->submitted_at->utc()->format('Y-m-d H:i:s'));

        // A deadline that has already passed: accepted, but marked late.
        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id], 'week_number' => 2, 'due_at' => '2026-06-12T12:00',
        ])->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-06-13 01:00:00', 'UTC')); // June 13, 9:00 AM Manila
        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-06-08', 'end_date' => '2026-06-12', 'activities_summary' => 'Week two work',
        ])->assertCreated();
        $week2 = JournalEntry::where('internship_id', $party['internship']->id)->where('week_number', 2)->firstOrFail();
        $this->assertTrue($week2->submitted_late);

        // Moving a deadline earlier re-evaluates already submitted journals.
        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/journal-deadlines', [
            'internship_ids' => [$party['internship']->id], 'week_number' => 1, 'due_at' => '2026-06-09T23:59',
        ])->assertOk();
        $this->assertTrue($week1->fresh()->submitted_late);
        $this->assertSame('2026-06-10 02:00:00', $week1->fresh()->submitted_at->utc()->format('Y-m-d H:i:s'));
    }

    // ── Evaluation period message (EVAL-MESSAGE-01/02) ───────────────────────

    public function test_eval_message_01_and_02_period_state_follows_faculty_approval(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/evaluations')->assertOk()
            ->assertJsonPath('evaluation_period_approved', false)
            ->assertJsonPath('evaluation_period_status', 'pending');

        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$party['internship']->id.'/approve-period')->assertOk();

        Sanctum::actingAs($party['student']);
        $this->getJson('/api/v1/student/evaluations')->assertOk()
            ->assertJsonPath('evaluation_period_approved', true)
            ->assertJsonPath('internship_id', $party['internship']->id);

        $page = file_get_contents(base_path('../frontend/src/pages/student/StudentEvaluations.jsx'));
        $this->assertStringContainsString("period?.status === 'pending_faculty_approval'", $page);
    }

    // ── Duplicate students (DUPLICATE-01) ────────────────────────────────────

    public function test_duplicate_01_faculty_evaluation_review_lists_each_student_once(): void
    {
        $party = $this->party();
        // Legacy duplicate open rows for the same student (bypassing provisioning).
        foreach ([1, 2] as $n) {
            DB::table('internships')->insert([
                'student_id' => $party['student']->id, 'faculty_id' => $party['faculty']->id,
                'status' => 'pending_placement', 'program' => 'BSIT', 'term' => 'dup '.$n,
                'school_year' => '2024-2025', 'semester' => '2',
                'target_hours' => 360, 'total_hours_rendered' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $homonym = $this->makeStudentWithSection('4ITD'); // same "Test Student" name, different person
        $this->makeActiveInternship($homonym, $party['company'], $party['supervisor'], $party['faculty'], $party['coordinator']);

        Sanctum::actingAs($party['faculty']);
        $rows = collect($this->getJson('/api/v1/faculty/evaluations')->assertOk()->json('internships'));
        $this->assertSame(1, $rows->where('student_id', $party['student']->id)->count());
        $this->assertSame(1, $rows->where('student_id', $homonym->id)->count(), 'Same-name students are still listed separately.');

        // The data repair keeps the row that holds work and cancels the empty duplicates.
        JournalEntry::create([
            'internship_id' => $party['internship']->id, 'week_number' => 1, 'entry_number' => 1,
            'date' => '2026-06-01', 'end_date' => '2026-06-05', 'activities_summary' => 'Real work', 'status' => 'submitted',
        ]);
        InternshipProvisioning::supersedeDuplicateOpenInternships();
        $this->assertSame('active', $party['internship']->fresh()->status);
        $this->assertSame(1, Internship::where('student_id', $party['student']->id)->whereIn('status', \App\Support\InternshipStatuses::openCurrent())->count());
    }

    // ── Attendance Monitor DTR preview (DTR-PREVIEW-01..03) ──────────────────

    public function test_dtr_preview_01_to_03_attendance_monitor_fo30_is_assignment_scoped(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['faculty']);
        $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->assertJsonStructure(['fo30', 'identity', 'attendance']);

        Sanctum::actingAs($this->makeUser('faculty'));
        $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertForbidden();

        // A newly created student assigned later works without code changes.
        $newStudent = $this->makeStudentWithSection('4ITC');
        $pending = $this->makePendingInternship($newStudent);
        $pending->forceFill(['faculty_id' => $party['faculty']->id])->save();
        Sanctum::actingAs($party['faculty']);
        $this->getJson('/api/v1/official-forms/'.$pending->id)->assertOk();
        $row = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))->firstWhere('student_id', $newStudent->id);
        $this->assertTrue($row['handled_by_faculty']);

        $page = file_get_contents(base_path('../frontend/src/pages/faculty/FacultyAssignedStudents.jsx'));
        $this->assertStringContainsString('Preview DTR FO-30', $page);
        $this->assertStringContainsString('openOfficialFo30(internshipId', $page);
    }

    // ── Change Password removed (PASSWORD-01, frontend) ──────────────────────

    public function test_password_01_frontend_has_no_change_password_controls(): void
    {
        $root = base_path('../frontend/src');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! preg_match('/\.(jsx?|tsx?)$/', $file->getFilename())) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('/auth/change-password', $src, $file->getPathname());
            $this->assertStringNotContainsString('/auth/request-password-change', $src, $file->getPathname());
            $this->assertDoesNotMatchRegularExpression('/>\s*Change Password\s*</', $src, $file->getPathname());
        }

        $login = file_get_contents($root.'/pages/LoginPage.jsx');
        $this->assertMatchesRegularExpression('/supervisorMode \? \(\s*<button/s', $login, 'Forgot Password renders only in Supervisor mode.');
    }

    // ── Input validation (min/max) ───────────────────────────────────────────

    public function test_input_limits_are_enforced_by_the_backend(): void
    {
        $party = $this->party();

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/portfolio', ['company_vision' => str_repeat('a', 10001)])->assertStatus(422);
        $this->postJson('/api/v1/student/portfolio', ['company_name' => str_repeat('a', 256)])->assertStatus(422);
        $this->postJson('/api/v1/student/logbook', [
            'date' => '2026-06-01', 'end_date' => '2026-06-05', 'activities_summary' => str_repeat('a', 5001),
        ])->assertStatus(422);

        $student = $this->makeStudentWithSection('4ITA');
        $this->makePendingInternship($student);
        Sanctum::actingAs($student);
        $long = $this->postJson('/api/v1/student/hte-requests', [
            'company_name' => 'Long Address HTE', 'address' => str_repeat('a', 256), 'contact_person' => 'Ana',
            'contact_email' => 'a@b.test', 'contact_number' => '09171234567',
        ])->assertStatus(422);
        $this->assertStringContainsString('255', $long->json('message'));

        $this->approveEvaluationPeriod($party['internship'], $party['faculty']);
        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final', 'form_type' => 'FO-24', 'responses' => ['c1' => 101],
        ])->assertStatus(422);
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'later', 'form_type' => 'FO-24', 'responses' => ['c1' => 90],
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', ['username' => str_repeat('x', 151), 'password' => 'x'])->assertStatus(422);
    }
}
