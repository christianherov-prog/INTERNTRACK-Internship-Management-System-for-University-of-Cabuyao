<?php

namespace Tests\Feature;

use App\Models\Internship;
use App\Models\SupervisorInviteToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * REVIEW-HIST-01..04: the sample "Clarence Magtibay" supervisor requests are
 * removed from the authoritative table (not filtered in the UI), legitimate
 * history survives, and new Faculty reviews still create history entries.
 */
class ReviewHistoryCleanupTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function party(string $studentNumber): array
    {
        $faculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection();
        $student->forceFill(['student_number' => $studentNumber])->save();
        $student->studentProfile()->update(['student_number' => $studentNumber]);
        $company = $this->makeEligibleCompany();
        $internship = $this->makePendingInternship($student);
        $internship->update(['company_id' => $company->id, 'faculty_id' => $faculty->id, 'supervisor_id' => null, 'status' => 'active']);

        return compact('faculty', 'student', 'company', 'internship');
    }

    private function invite(array $party, array $attributes, array $files = []): SupervisorInviteToken
    {
        $forms = [];
        foreach ($files as $name => $path) {
            Storage::disk('local')->put($path, 'pdf');
            $forms[] = ['path' => $path, 'name' => $name, 'mime' => 'application/pdf'];
        }

        return SupervisorInviteToken::forceCreate(array_merge([
            'internship_id' => $party['internship']->id,
            'student_id' => $party['student']->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
            'company_id' => $party['company']->id,
            'position' => 'IT Manager',
            'fo29_file_path' => $forms[0]['path'] ?? null,
            'acceptance_form_paths' => $forms ?: null,
            'reviewed_by' => $party['faculty']->id,
            'reviewed_at' => now()->subDay(),
        ], $attributes));
    }

    private function runCleanup(): void
    {
        (require database_path('migrations/2026_09_25_120100_remove_sample_supervisor_review_history.php'))->up();
    }

    public function test_sample_rows_are_deleted_and_legitimate_history_is_preserved(): void
    {
        Storage::fake('local');
        $party = $this->party('2300600');
        $base = "internships/{$party['internship']->id}/supervisor-invites";

        $samples = [
            $this->invite($party, ['status' => 'rejected', 'first_name' => 'Clarence', 'last_name' => 'Magtibay', 'email' => 'pogi@gmail.com', 'review_remarks' => 'Where is the acceptance form?'], ['Progress Report.pdf' => "$base/2/a.pdf"]),
            $this->invite($party, ['status' => 'rejected', 'first_name' => 'Clarence', 'last_name' => 'Magtibay', 'email' => 'pogi@gmail.com', 'review_remarks' => 'NOOOOO!!!'], ['CAPSTONE_PROGRESS_REPORT.pdf' => "$base/4/b.pdf"]),
            $this->invite($party, ['status' => 'expired', 'first_name' => 'Clarence', 'last_name' => 'Magtibay', 'email' => 'magtibay@gmail.com'], ['CAPSTONE_PROGRESS_REPORT.pdf' => "$base/shared/c.pdf"]),
        ];
        // A legitimate request that happens to reference one of the same files.
        $legit = $this->invite($party, ['status' => 'approved', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria.santos@hte.example', 'review_remarks' => 'Complete acceptance form.'], ['acceptance.pdf' => "$base/shared/c.pdf"]);
        DB::table('notifications')->insert(['user_id' => $party['student']->id, 'type' => 'supervisor_rejected', 'title' => 't', 'message' => 'm', 'data' => json_encode(['invite_id' => $samples[0]->id]), 'created_at' => now(), 'updated_at' => now()]);

        $this->runCleanup();
        $this->runCleanup(); // idempotent

        // REVIEW-HIST-01
        foreach ($samples as $sample) {
            $this->assertNull(SupervisorInviteToken::find($sample->id));
        }
        $this->assertSame(0, DB::table('notifications')->where('data', 'like', '%"invite_id":'.$samples[0]->id.'}%')->count());

        // REVIEW-HIST-02: sample-only files removed; a file still referenced is kept; no orphan refs.
        Storage::disk('local')->assertMissing("$base/2/a.pdf");
        Storage::disk('local')->assertMissing("$base/4/b.pdf");
        Storage::disk('local')->assertExists("$base/shared/c.pdf");
        foreach (SupervisorInviteToken::whereNotNull('acceptance_form_paths')->get() as $row) {
            foreach (json_decode($row->getRawOriginal('acceptance_form_paths'), true) as $form) {
                Storage::disk('local')->assertExists($form['path']);
            }
        }

        // REVIEW-HIST-03: legitimate history remains and is still shown to Faculty.
        $this->assertNotNull(SupervisorInviteToken::find($legit->id));
        Sanctum::actingAs($party['faculty']);
        $history = collect($this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk()->json('history'));
        $this->assertTrue($history->pluck('id')->contains($legit->id));
        $this->assertFalse($history->pluck('id')->intersect(collect($samples)->pluck('id'))->isNotEmpty());
    }

    public function test_cleanup_ignores_the_same_names_for_other_students(): void
    {
        $party = $this->party('2399777');
        $row = $this->invite($party, ['status' => 'rejected', 'first_name' => 'Clarence', 'last_name' => 'Magtibay', 'email' => 'pogi@gmail.com', 'review_remarks' => 'Real remark']);

        $this->runCleanup();

        $this->assertNotNull(SupervisorInviteToken::find($row->id), 'Only the identified sample for Student 2300600 is removed.');
    }

    /** REVIEW-HIST-04 */
    public function test_new_faculty_review_still_creates_a_history_entry(): void
    {
        Storage::fake('local');
        $party = $this->party('2399778');

        Sanctum::actingAs($party['student']);
        $token = $this->postJson('/api/v1/student/supervisor-invite')->assertOk()->json('token');
        $this->post('/api/v1/supervisor-register', [
            'token' => $token,
            'login_username' => 'real.supervisor',
            'first_name' => 'Real',
            'last_name' => 'Supervisor',
            'email' => 'real.supervisor@hte.example',
            'contact_number' => '09171234567',
            'position' => 'IT Manager',
            'sex' => 'Female',
            'company_id' => $party['company']->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'acceptance_forms' => [UploadedFile::fake()->create('acceptance-form.pdf', 60, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $invite = SupervisorInviteToken::where('token', $token)->firstOrFail();

        Sanctum::actingAs($party['faculty']);
        $this->patchJson("/api/v1/faculty/supervisor-approvals/{$invite->id}/reject", ['remarks' => 'Acceptance form is unsigned.'])->assertOk();

        $entry = collect($this->getJson('/api/v1/faculty/supervisor-approvals')->assertOk()->json('history'))->firstWhere('id', $invite->id);
        $this->assertNotNull($entry, 'The review appears in Review History.');
        $invite->refresh();
        $this->assertSame('rejected', $invite->status);
        $this->assertSame($party['faculty']->id, (int) $invite->reviewed_by);
        $this->assertNotNull($invite->reviewed_at);
        $this->assertSame('Acceptance form is unsigned.', $invite->review_remarks);
        $this->assertNotEmpty($invite->acceptance_forms);
    }
}
