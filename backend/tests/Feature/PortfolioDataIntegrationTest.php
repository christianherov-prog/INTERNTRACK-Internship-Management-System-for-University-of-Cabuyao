<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\InternshipApplication;
use App\Models\SupervisorProfile;
use App\Support\ManilaTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PortfolioDataIntegrationTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    private function party(string $section = '4ITD', bool $mapFaculty = true): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        if ($mapFaculty) {
            $this->mapFacultyForSection($faculty, $section);
        }
        $supervisor = $this->makeUser('supervisor');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Industry',
            'last_name' => 'Magtibay',
            'position' => 'IT Supervisor',
            'email' => $supervisor->email,
        ]);
        $student = $this->makeStudentWithSection($section);
        $company = $this->makeEligibleCompany([
            'company_name' => 'TechCorp PH',
            'address' => 'Alabang, Muntinlupa City',
        ]);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update([
            'start_date' => '2026-06-01',
            'end_date' => '2026-09-30',
            'school_year' => '2025-2026',
            'semester' => '2nd Semester',
        ]);

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    public function test_identity_hte_and_empty_essays_come_from_authoritative_records(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $payload = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();

        $this->assertSame(ManilaTime::TZ, $payload['timezone']);
        $this->assertSame('STUDENT, TEST', $payload['identity']['student_name']);
        $this->assertSame($party['student']->student_number, $payload['identity']['student_number']);
        $this->assertSame('Bachelor of Science in Information Technology', $payload['identity']['program']);
        $this->assertSame('4ITD', $payload['identity']['section']);
        $this->assertSame('2025-2026', $payload['identity']['academic_year']);
        $this->assertSame('2nd', $payload['identity']['semester']);
        $this->assertSame('TechCorp PH', $payload['identity']['company_name']);
        $this->assertSame('Alabang, Muntinlupa City', $payload['identity']['company_address']);
        $this->assertSame('MAGTIBAY, INDUSTRY', $payload['identity']['supervisor_name']);
        $this->assertSame('IT Supervisor', $payload['identity']['supervisor_position']);
        $this->assertNotEmpty($payload['identity']['faculty_name']);
        $this->assertNotEmpty($payload['identity']['coordinator_name']);
        $this->assertStringContainsString('Jun 1, 2026', $payload['identity']['training_period']);
        $this->assertSame('', $payload['internship']['portfolio']['company_vision']);
        $this->assertSame('', $payload['internship']['portfolio']['assessment_ethical']);
        $this->assertStringNotContainsString('BSIT / BSCS', json_encode($payload['identity']));
    }

    public function test_fo30_uses_same_attendance_in_asia_manila_without_inventing_sessions(): void
    {
        $party = $this->party();
        Carbon::setTestNow(Carbon::parse('2026-09-09 00:01:00', 'UTC'));
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-09-09 09:02:00', 'UTC'));
        $out = $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();
        $logId = $out->json('record.id');
        $hours = (float) $out->json('record.hours_rendered');

        $attendance = $this->getJson('/api/v1/student/attendance')->assertOk();
        $row = collect($attendance->json('attendance.data') ?? $attendance->json('data') ?? [])->first();
        $this->assertNotNull($row);
        $this->assertSame('08:01', $row['clock_in_display']);
        $this->assertSame('17:02', $row['clock_out_display']);
        $this->assertSame('2026-09-09', $row['date_display']);

        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $dtr = $portfolio['internship']['attendance'][0];
        $this->assertSame('2026-09-09', $dtr['date']);
        $this->assertSame(ManilaTime::TZ, $dtr['timezone']);
        $this->assertSame('08:01', $dtr['am_time_in']);
        $this->assertNull($dtr['am_time_out']);
        $this->assertNull($dtr['pm_time_in']);
        $this->assertSame('17:02', $dtr['pm_time_out']);
        $this->assertEqualsWithDelta($hours, (float) $dtr['hours_rendered'], 0.01);
        $this->assertFalse($dtr['validated']);
        $this->assertNull($dtr['hte_signature_path']);

        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        Sanctum::actingAs($party['supervisor']);
        $this->patchJson('/api/v1/supervisor/attendance/'.$logId.'/validate', [
            'action' => 'validated',
        ])->assertOk();

        Sanctum::actingAs($party['student']);
        $after = $this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.attendance.0');
        $this->assertTrue($after['validated']);
        $this->assertSame('signatures/'.$party['supervisor']->id.'_processed.png', $after['hte_signature_path']);

        Carbon::setTestNow();
    }

    public function test_fo31_journal_and_student_signature_path(): void
    {
        $party = $this->party();
        Storage::disk('local')->put('signatures/'.$party['student']->id.'_processed.png', $this->png());
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 3,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => 'Configured interntrack DTR mapping',
            'challenges' => 'Timezone conversion',
            'learnings' => 'Use Asia/Manila explicitly',
        ])->assertCreated();

        $payload = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $journal = $payload['internship']['journals'][0];
        $this->assertSame(3, (int) $journal['week_number']);
        $this->assertSame('2026-09-01', $journal['date']);
        $this->assertSame('2026-09-05', $journal['end_date']);
        $this->assertSame('Configured interntrack DTR mapping', $journal['activities_summary']);
        $this->assertSame('Timezone conversion', $journal['challenges']);
        $this->assertSame('Use Asia/Manila explicitly', $journal['learnings']);
        $this->assertSame('signatures/'.$party['student']->id.'_processed.png', $payload['identity']['student_signature_path']);
    }

    public function test_evaluations_fo22_fo23_fo24_and_fo03_without_faculty_eval(): void
    {
        $party = $this->party();
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        Storage::disk('local')->put('signatures/'.$party['faculty']->id.'_processed.png', $this->png());
        $this->approveEvaluationPeriod($party['internship'], $party['faculty']);

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final',
            'form_type' => 'FO-22',
            'responses' => [
                'q1' => 5, 'q2' => 5, 'q3' => 4, 'q4' => 4, 'q5' => 5, 'q6' => 4, 'q7' => 5,
                'recommend' => 'yes',
                'recommend_reason' => 'Strong mentorship',
            ],
            'general_comments' => 'HTE comments',
        ])->assertCreated();
        $this->postJson('/api/v1/student/evaluations', [
            'evaluation_period' => 'final',
            'form_type' => 'FO-23',
            'responses' => ['q1' => 5, 'q2' => 4],
            'general_comments' => 'Program comments',
        ])->assertCreated();

        Sanctum::actingAs($party['supervisor']);
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => 'FO-24',
            'responses' => [
                'c1' => 100, 'c2' => 80, 'c3' => 80, 'c4' => 80, 'c5' => 80,
                'c6' => 80, 'c7' => 80, 'c8' => 80, 'c9' => 80, 'c10' => 80,
                'recommendations' => 'Keep documenting work',
            ],
            'general_comments' => 'Solid intern',
        ])->assertCreated();
        $this->postJson('/api/v1/supervisor/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'form_type' => 'FO-03',
            'responses' => ['crit_0' => 5, 'would_hire' => 'yes'],
        ])->assertCreated();

        Sanctum::actingAs($party['faculty']);
        $this->postJson('/api/v1/faculty/evaluations/'.$party['internship']->id, [
            'evaluation_period' => 'final',
            'overall_score' => 88,
            'general_comments' => 'Faculty remarks',
        ])->assertCreated();

        Sanctum::actingAs($party['student']);
        $evals = collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.evaluations'));

        $this->assertNull($evals->firstWhere('form_type', 'faculty_eval'));
        $this->assertFalse($evals->contains(fn ($e) => ($e['form_type'] ?? '') === 'faculty_eval'));

        $fo24 = $evals->firstWhere('form_type', 'FO-24');
        $this->assertNotNull($fo24);
        $this->assertSame('completed', $fo24['status']);
        $this->assertNotEmpty($fo24['submitted_at']);
        $this->assertEquals(25.0, (float) $fo24['responses']['eq1']);
        $this->assertEquals(10.0, (float) $fo24['responses']['eq2']);
        $this->assertEquals(85.0, (float) $fo24['average_score']);
        $this->assertSame('Solid intern', $fo24['general_comments']);
        $this->assertSame('Keep documenting work', $fo24['responses']['recommendations']);
        $this->assertSame('signatures/'.$party['supervisor']->id.'_processed.png', $fo24['signature_path']);
        $this->assertSame('MAGTIBAY, INDUSTRY', $fo24['evaluator_name']);

        $fo22 = $evals->firstWhere('form_type', 'FO-22');
        $this->assertSame('completed', $fo22['status']);
        $this->assertSame(5, (int) $fo22['responses']['q1']);
        $this->assertSame('yes', $fo22['responses']['recommend']);
        $this->assertNotEmpty($fo22['responses']['interpretation']);

        $fo23 = $evals->firstWhere('form_type', 'FO-23');
        $this->assertSame('completed', $fo23['status']);
        $this->assertSame('Program comments', $fo23['general_comments']);

        $fo03 = $evals->firstWhere('form_type', 'FO-03');
        $this->assertSame('completed', $fo03['status']);
        $this->assertSame('yes', $fo03['responses']['would_hire']);
    }

    public function test_chapter_text_fields_persist_in_portfolio_payload(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->postJson('/api/v1/student/portfolio/builder', [
            'company_background' => 'Accenture company profile',
            'company_vision' => 'Company Vision text',
            'company_mission' => 'Company Mission text',
            'prof_ethical_responsibilities' => 'Ethical essay',
            'things_learned' => 'Learnings essay',
            'experience_with_people' => 'People essay',
            'industry_best_practices' => 'Practices essay',
            'recommendations' => 'Recommendation essay',
            'advice' => 'Advice essay',
        ])->assertOk();

        $portfolio = $this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.portfolio');
        $this->assertSame('Accenture company profile', $portfolio['company_background'] ?? $portfolio['company_history'] ?? null);
        $this->assertSame('Company Vision text', $portfolio['company_vision']);
        $this->assertSame('Company Mission text', $portfolio['company_mission']);
        $this->assertSame('Ethical essay', $portfolio['prof_ethical_responsibilities'] ?? $portfolio['assessment_ethical'] ?? null);
        $this->assertSame('Learnings essay', $portfolio['things_learned'] ?? $portfolio['assessment_learnings'] ?? null);
        $this->assertSame('People essay', $portfolio['experience_with_people'] ?? $portfolio['assessment_experience'] ?? null);
        $this->assertSame('Practices essay', $portfolio['industry_best_practices'] ?? $portfolio['assessment_standards'] ?? null);
        $this->assertSame('Recommendation essay', $portfolio['recommendations'] ?? $portfolio['assessment_recommendations'] ?? null);
        $this->assertSame('Advice essay', $portfolio['advice'] ?? $portfolio['assessment_advice'] ?? null);
    }

    public function test_moa_and_approved_requirement_are_auto_linked_rejected_is_not(): void
    {
        $party = $this->party();
        Storage::disk('local')->put('placement-moa/applications/1/moa.pdf', 'moa');
        InternshipApplication::create([
            'student_id' => $party['student']->id,
            'company_id' => $party['company']->id,
            'status' => 'approved',
            'moa_path' => 'placement-moa/applications/1/moa.pdf',
            'moa_original_name' => 'TechCorp MOA.pdf',
        ]);

        $cv = Document::create([
            'internship_id' => $party['internship']->id,
            'document_type' => 'Curriculum Vitae',
            'status' => 'approved',
            'current_stage' => 'completed',
        ]);
        DocumentAttachment::create([
            'document_id' => $cv->id,
            'file_path' => 'documents/cv.pdf',
            'file_name' => 'cv.pdf',
        ]);
        $rejected = Document::create([
            'internship_id' => $party['internship']->id,
            'document_type' => 'Application Letter',
            'status' => 'rejected',
        ]);
        DocumentAttachment::create([
            'document_id' => $rejected->id,
            'file_path' => 'documents/rejected-letter.pdf',
            'file_name' => 'rejected-letter.pdf',
        ]);

        Sanctum::actingAs($party['student']);
        $photos = collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.portfolio.photos'));
        $this->assertTrue($photos->contains(fn ($doc) => ($doc['file_path'] ?? '') === 'placement-moa/applications/1/moa.pdf'));
        $this->assertTrue($photos->contains(fn ($doc) => ($doc['type'] ?? '') === 'student_cv'));
        $this->assertFalse($photos->contains(fn ($doc) => ($doc['file_name'] ?? '') === 'rejected-letter.pdf'));
    }

    public function test_portfolio_authorization_and_cross_student_isolation(): void
    {
        $partyA = $this->party();
        $partyB = $this->party('4ITA', false);

        Sanctum::actingAs($partyA['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-09-01',
            'end_date' => '2026-09-05',
            'activities_summary' => 'Student A only',
        ])->assertCreated();

        Sanctum::actingAs($partyB['student']);
        $b = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $summaries = collect($b['internship']['journals'])->pluck('activities_summary');
        $this->assertFalse($summaries->contains('Student A only'));
        $this->assertSame($partyB['student']->id, $b['user']['id']);

        $this->getJson('/api/v1/student/portfolio?internship_id='.$partyA['internship']->id)
            ->assertForbidden();

        Sanctum::actingAs($this->makeUser('supervisor', 'SUP-STRANGER'));
        $this->getJson('/api/v1/student/portfolio?internship_id='.$partyA['internship']->id)
            ->assertForbidden();
    }

    public function test_manila_time_converts_stored_utc_without_adding_eight_hours_manually(): void
    {
        $at = ManilaTime::fromStoredDateAndTime('2026-09-09', '00:01:00');
        $this->assertSame('08:01', ManilaTime::clockHm($at));
        $this->assertSame('2026-09-09', $at->toDateString());
        $this->assertSame('Asia/Manila', $at->timezoneName);
    }
}
