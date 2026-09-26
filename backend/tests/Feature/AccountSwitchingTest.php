<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AUTH-SWITCH (backend side). The browser-state fix lives in
 * frontend/src/utils/sessionState.js (covered by `npm test`); here we prove
 * that the backend still refuses another Student's internship id, and that a
 * Student who sends no stale id always gets their own internship.
 */
class AccountSwitchingTest extends TestCase
{
    use RefreshDatabase;

    private const STUDENTS = ['2300592', '2300611', '2300612']; // finished, ongoing, fresh

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Artisan::call('interntrack:seed-ccs-demo');
    }

    private function student(string $number): User
    {
        return User::findOrFail(StudentProfile::where('student_number', $number)->value('user_id'));
    }

    private function internshipId(User $student): int
    {
        return (int) Internship::where('student_id', $student->id)->orderByDesc('id')->value('id');
    }

    /** AUTH-SWITCH-06: a manual request for another Student's internship is still 403. */
    public function test_another_students_internship_id_is_refused(): void
    {
        $a = $this->student('2300592');
        $b = $this->student('2300611');
        $before = Internship::where('student_id', $b->id)->count();

        Sanctum::actingAs($b);
        $headers = ['X-Internship-Id' => (string) $this->internshipId($a)];
        $this->getJson('/api/v1/student/dashboard', $headers)->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized access to this internship.');
        $this->getJson('/api/v1/student/portfolio', $headers)->assertForbidden();

        // The refused request did not provision anything for B.
        $this->assertSame($before, Internship::where('student_id', $b->id)->count());
    }

    /** AUTH-SWITCH-03 / 05: repeated A→B→C switching, no stale id → each sees only their own data. */
    public function test_repeated_account_switching_without_stale_state_never_403s(): void
    {
        $counts = [];
        foreach (self::STUDENTS as $number) {
            $counts[$number] = Internship::where('student_id', $this->student($number)->id)->count();
        }

        for ($cycle = 0; $cycle < 12; $cycle++) {
            $number = self::STUDENTS[$cycle % count(self::STUDENTS)];
            $student = $this->student($number);
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs($student);

            foreach (['/api/v1/student/dashboard', '/api/v1/student/applications', '/api/v1/student/portfolio', '/api/v1/student/internships'] as $uri) {
                $this->getJson($uri)->assertOk();
            }
            $this->assertSame($this->internshipId($student), (int) $this->getJson('/api/v1/student/dashboard')->json('internship.id'),
                "cycle {$cycle}: {$number} must resolve to their own internship");

            // Same account, its own selection (new tab / reload) keeps working.
            $this->getJson('/api/v1/student/dashboard', ['X-Internship-Id' => (string) $this->internshipId($student)])->assertOk();
        }

        foreach (self::STUDENTS as $number) {
            $this->assertSame($counts[$number], Internship::where('student_id', $this->student($number)->id)->count(), "{$number} gained no stray internship");
        }
    }

    /** Real login/logout endpoints: switching accounts never leaks the previous identity. */
    public function test_login_logout_cycle_returns_the_newly_signed_in_student(): void
    {
        foreach (array_merge(self::STUDENTS, self::STUDENTS) as $number) {
            $this->app['auth']->forgetGuards();
            $login = $this->postJson('/api/v1/auth/login', ['username' => $number, 'password' => 'interntrack123']);
            $login->assertOk()->assertJsonPath('user.id', $this->student($number)->id);
        }
    }
}
