<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\Internship;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Support\SignatureCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

/**
 * FO24-SIG-01..07: the Industry Supervisor signature on PNC:AA-FO-24 is the
 * submitting supervisor's (evaluations.evaluated_by), resolved by one shared
 * resolver for the Student Portfolio, the Faculty preview and every other
 * role's preview, and served only to users who may view the internship.
 */
class Fo24SupervisorSignatureTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function supervisor(string $first, string $last, bool $withSignature = true): User
    {
        $user = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $user->id,
            'first_name' => $first,
            'last_name' => $last,
            'position' => 'IT Supervisor',
            'email' => $user->email,
        ]);
        if ($withSignature) {
            // Same path a supervisor's "My Signature" upload produces.
            SignatureCapture::storeProcessedProfile($user, SignatureCapture::visibleInkPng());
        }

        return $user->fresh();
    }

    private function party(User $supervisor): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection('4ITD');
        $company = $this->makeEligibleCompany(['address' => 'Cabuyao, Laguna']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        return compact('coordinator', 'faculty', 'student', 'company', 'internship');
    }

    /** A completed FO-24 exactly as stored when the supervisor submitted before saving a signature. */
    private function fo24(Internship $internship, User $submittedBy, ?string $storedSignature = null): Evaluation
    {
        return Evaluation::forceCreate([
            'internship_id' => $internship->id,
            'evaluator_type' => 'supervisor',
            'evaluated_by' => $submittedBy->id,
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => ['c1' => 5, 'c2' => 4, 'c3' => 4, 'c4' => 5, 'c5' => 4, 'c6' => 5, 'c7' => 4, 'c8' => 5, 'c9' => 4, 'c10' => 5],
            'average_score' => 4.5,
            'general_comments' => 'Reliable intern.',
            'submitted_at' => now()->subDay(),
            'signature_path' => $storedSignature,
            'signer_name' => null,
        ]);
    }

    private function facultyFo24(User $faculty, Internship $internship): ?array
    {
        Sanctum::actingAs($faculty);
        $row = collect($this->getJson('/api/v1/faculty/evaluations')->assertOk()->json('internships'))
            ->firstWhere('id', $internship->id);

        return $row ? collect($row['evaluations'])->firstWhere('form_type', 'FO-24') : null;
    }

    private function portfolioFo24(User $viewer, Internship $internship): ?array
    {
        Sanctum::actingAs($viewer);

        return collect($this->getJson("/api/v1/official-forms/{$internship->id}")->assertOk()->json('evaluations'))
            ->firstWhere('form_type', 'FO-24');
    }

    /** FO24-SIG-01 */
    public function test_completed_fo24_carries_the_submitting_supervisors_signature(): void
    {
        $miguel = $this->supervisor('Miguel', 'Santos');
        $p = $this->party($miguel);
        $this->fo24($p['internship'], $miguel);

        $fo24 = $this->facultyFo24($p['faculty'], $p['internship']);

        $this->assertNotNull($fo24, 'FO-24 is listed for the assigned Faculty.');
        $this->assertSame("signatures/{$miguel->id}_processed.png", $fo24['resolved_signature_path']);
        $this->assertSame('SANTOS, MIGUEL', $fo24['evaluator_name']);

        // The Faculty preview's signature image is downloadable by the assigned Faculty.
        $this->get('/api/v1/files/download?path='.urlencode($fo24['resolved_signature_path']))->assertOk();
    }

    /** FO24-SIG-02 */
    public function test_the_submitting_supervisor_id_is_authoritative_after_reassignment(): void
    {
        $original = $this->supervisor('Miguel', 'Santos');
        $replacement = $this->supervisor('Ana', 'Reyes');
        $p = $this->party($original);
        // A stored reference to someone else's profile signature is never trusted.
        $this->fo24($p['internship'], $original, "signatures/{$replacement->id}_processed.png");
        $p['internship']->update(['supervisor_id' => $replacement->id]);

        $fo24 = $this->facultyFo24($p['faculty'], $p['internship']);

        $this->assertSame("signatures/{$original->id}_processed.png", $fo24['resolved_signature_path']);
        $this->assertSame('SANTOS, MIGUEL', $fo24['evaluator_name']);
        // Viewers of the internship can still load the original signer's image.
        $this->get('/api/v1/files/download?path='.urlencode($fo24['resolved_signature_path']))->assertOk();
    }

    /** FO24-SIG-03 */
    public function test_no_cross_supervisor_signature_leakage(): void
    {
        $unsigned = $this->supervisor('Carlo', 'Dizon', withSignature: false);
        $current = $this->supervisor('Ana', 'Reyes');
        $p = $this->party($unsigned);
        $this->fo24($p['internship'], $unsigned);
        $p['internship']->update(['supervisor_id' => $current->id]);

        $faculty = $this->facultyFo24($p['faculty'], $p['internship']);
        $portfolio = $this->portfolioFo24($p['faculty'], $p['internship']);

        $this->assertNull($faculty['resolved_signature_path'], 'Never the current supervisor’s signature.');
        $this->assertNull($portfolio['signature_path']);
        $this->assertNull($portfolio['resolved_signature_path']);
        $this->assertSame('DIZON, CARLO', $faculty['evaluator_name']);

        // Two supervisors, two internships: each FO-24 keeps its own signer.
        $other = $this->supervisor('Liza', 'Cruz');
        $q = $this->party($other);
        $this->fo24($q['internship'], $other);
        $this->assertSame("signatures/{$other->id}_processed.png", $this->facultyFo24($q['faculty'], $q['internship'])['resolved_signature_path']);
    }

    /** FO24-SIG-04 */
    public function test_portfolio_and_every_role_preview_use_the_same_signature(): void
    {
        $miguel = $this->supervisor('Miguel', 'Santos');
        $p = $this->party($miguel);
        $evaluation = $this->fo24($p['internship'], $miguel);
        $expected = "signatures/{$miguel->id}_processed.png";

        $faculty = $this->facultyFo24($p['faculty'], $p['internship']);
        $facultyPortfolio = $this->portfolioFo24($p['faculty'], $p['internship']);
        $this->assertSame($expected, $faculty['resolved_signature_path']);
        $this->assertSame($expected, $facultyPortfolio['signature_path']);
        $this->assertSame($faculty['evaluator_name'], $facultyPortfolio['evaluator_name']);
        $this->assertSame($faculty['evaluator_name'], $facultyPortfolio['signer_name']);

        // Student Portfolio (after the Faculty release) shows the same signature.
        $evaluation->forceFill(['released_to_student_at' => now()])->save();
        $studentPortfolio = $this->portfolioFo24($p['student'], $p['internship']);
        $this->assertSame($expected, $studentPortfolio['signature_path']);
        Sanctum::actingAs($p['student']);
        $studentList = collect($this->getJson('/api/v1/student/evaluations')->assertOk()->json('data'))->firstWhere('form_type', 'FO-24');
        $this->assertSame($expected, $studentList['resolved_signature_path']);

        // Coordinator, Director and the Supervisor's own previews agree.
        Sanctum::actingAs($p['coordinator']);
        $coordinator = collect($this->getJson('/api/v1/coordinator/evaluations')->assertOk()->json('internships.data'))
            ->firstWhere('id', $p['internship']->id);
        $this->assertSame($expected, collect($coordinator['evaluations'])->firstWhere('form_type', 'FO-24')['resolved_signature_path']);

        Sanctum::actingAs($this->makeUser('director'));
        $director = collect($this->getJson('/api/v1/director/evaluations')->assertOk()->json('internships.data'))
            ->firstWhere('id', $p['internship']->id);
        $this->assertSame($expected, collect($director['evaluations'])->firstWhere('form_type', 'FO-24')['resolved_signature_path']);

        Sanctum::actingAs($miguel);
        $own = collect($this->getJson('/api/v1/supervisor/evaluations')->assertOk()->json('data.completed'))->firstWhere('id', $evaluation->id);
        $this->assertSame($expected, $own['resolved_signature_path']);
    }

    /** FO24-SIG-05 */
    public function test_missing_or_unusable_signature_is_handled_safely(): void
    {
        $unsigned = $this->supervisor('Carlo', 'Dizon', withSignature: false);
        $p = $this->party($unsigned);
        $this->fo24($p['internship'], $unsigned);

        $this->assertNull($this->facultyFo24($p['faculty'], $p['internship'])['resolved_signature_path']);

        // A corrupt file at the profile path is treated as "no signature", not rendered.
        Storage::disk('local')->put("signatures/{$unsigned->id}_processed.png", 'not an image');
        $this->assertNull($this->facultyFo24($p['faculty'], $p['internship'])['resolved_signature_path']);
        $this->assertNull($this->portfolioFo24($p['faculty'], $p['internship'])['signature_path']);
    }

    /** FO24-SIG-06 */
    public function test_unrelated_faculty_is_denied(): void
    {
        $miguel = $this->supervisor('Miguel', 'Santos');
        $p = $this->party($miguel);
        $this->fo24($p['internship'], $miguel);
        $outsider = $this->makeUser('faculty');

        $this->assertNull($this->facultyFo24($outsider, $p['internship']), 'Not listed for an unassigned Faculty.');
        $this->getJson("/api/v1/official-forms/{$p['internship']->id}")->assertForbidden();
        $this->get('/api/v1/files/download?path='.urlencode("signatures/{$miguel->id}_processed.png"))->assertForbidden();

        // Unreleased FO-24: the Student sees completion only — no signature.
        Sanctum::actingAs($p['student']);
        $locked = collect($this->getJson('/api/v1/student/evaluations')->assertOk()->json('data'))->firstWhere('form_type', 'FO-24');
        $this->assertTrue($locked['details_locked']);
        $this->assertArrayNotHasKey('resolved_signature_path', $locked);
        $this->assertArrayNotHasKey('signature_path', $locked);
    }

    /** FO24-SIG-07 */
    public function test_a_newly_registered_supervisor_works_without_code_changes(): void
    {
        $newcomer = $this->supervisor('Bea', 'Villanueva', withSignature: false);
        $p = $this->party($newcomer);
        $this->fo24($p['internship'], $newcomer);
        $this->assertNull($this->facultyFo24($p['faculty'], $p['internship'])['resolved_signature_path']);

        // The supervisor saves "My Signature" later: every preview picks it up.
        SignatureCapture::storeProcessedProfile($newcomer, SignatureCapture::visibleInkPng());

        $this->assertSame("signatures/{$newcomer->id}_processed.png", $this->facultyFo24($p['faculty'], $p['internship'])['resolved_signature_path']);
        $this->assertSame("signatures/{$newcomer->id}_processed.png", $this->portfolioFo24($p['faculty'], $p['internship'])['signature_path']);
        $this->assertSame('VILLANUEVA, BEA', $this->facultyFo24($p['faculty'], $p['internship'])['evaluator_name']);
    }
}
