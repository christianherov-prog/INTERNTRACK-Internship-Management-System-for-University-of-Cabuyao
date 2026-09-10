<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\SupervisorProfile;
use App\Services\OfficialFormDataService;
use App\Services\OneWeekOjtDemoService;
use App\Support\ManilaTime;
use App\Support\OfficialFormAsset;
use App\Support\SignatureCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class OfficialFormConsistencyTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    private function party(): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $director = $this->makeUser('director');
        $supervisor = $this->makeUser('supervisor', 'SUP-0002');
        SupervisorProfile::create([
            'user_id' => $supervisor->id,
            'first_name' => 'Adrian',
            'last_name' => 'Reyes',
            'position' => 'Industry Supervisor',
            'email' => $supervisor->email,
        ]);
        $otherSupervisor = $this->makeUser('supervisor', 'SUP-OTHER');
        SupervisorProfile::create([
            'user_id' => $otherSupervisor->id,
            'first_name' => 'Other',
            'last_name' => 'Supervisor',
            'email' => $otherSupervisor->email,
        ]);
        $student = $this->makeStudentWithSection();
        $student->studentProfile->update([
            'first_name' => 'Clarence',
            'last_name' => 'Montealegre',
            'student_number' => '2300592',
        ]);
        $student->update(['student_number' => '2300592']);
        $otherStudent = $this->makeStudentWithSection('4ITA');
        $otherStudent->studentProfile->update([
            'first_name' => 'Angel',
            'last_name' => 'Taac',
            'student_number' => '2300590',
        ]);
        $otherStudent->update(['student_number' => '2300590']);
        $company = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);
        $otherCompany = $this->makeEligibleCompany(['company_name' => 'TechCorp PH']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $internship->update(['status' => 'ongoing']);
        $otherInternship = $this->makeActiveInternship($otherStudent, $otherCompany, $otherSupervisor, $faculty, $coordinator);
        $otherInternship->update(['status' => 'ongoing']);

        return compact(
            'coordinator',
            'faculty',
            'director',
            'supervisor',
            'otherSupervisor',
            'student',
            'otherStudent',
            'company',
            'otherCompany',
            'internship',
            'otherInternship'
        );
    }

    private function uploadLogo($student, string $name): string
    {
        Sanctum::actingAs($student);
        $res = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image($name, 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated();

        return (string) $res->json('document.file_path');
    }

    public function test_hte_logo_comes_from_this_internship_only(): void
    {
        Storage::fake('local');
        $party = $this->party();
        $logoA = $this->uploadLogo($party['student'], 'accenture.png');
        $logoB = $this->uploadLogo($party['otherStudent'], 'techcorp.png');
        $this->assertNotSame($logoA, $logoB);

        Sanctum::actingAs($party['student']);
        $a = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->json();
        Sanctum::actingAs($party['otherStudent']);
        $b = $this->getJson('/api/v1/official-forms/'.$party['otherInternship']->id)->assertOk()->json();

        $this->assertSame($logoA, $a['company_logo_path']);
        $this->assertSame($logoA, $a['fo30']['company_logo_path']);
        $this->assertSame($logoA, $a['identity']['company_logo_path']);
        $this->assertSame('Accenture PH', $a['fo30']['company_name']);
        $this->assertSame($logoB, $b['company_logo_path']);
        $this->assertSame('TechCorp PH', $b['fo30']['company_name']);
        $this->assertNotSame($logoB, $a['company_logo_path']);
        $this->assertNotSame($logoA, $b['company_logo_path']);
    }

    public function test_roles_receive_the_same_fo30_payload(): void
    {
        Storage::fake('local');
        $party = $this->party();
        $logo = $this->uploadLogo($party['student'], 'accenture.png');
        Storage::disk('local')->put('signatures/'.$party['student']->id.'_processed.png', $this->png());
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);

        $ids = [];
        foreach (['student', 'faculty', 'coordinator', 'supervisor', 'director'] as $role) {
            Sanctum::actingAs($party[$role]);
            $json = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->json();
            $ids[$role] = [
                'name' => $json['fo30']['student_name'],
                'program' => $json['fo30']['program'],
                'company' => $json['fo30']['company_name'],
                'logo' => $json['fo30']['company_logo_path'],
                'supervisor' => $json['fo30']['supervisor_name'],
                'hours' => collect($json['fo30']['logs'])->sum('hours_rendered'),
                'am' => $json['fo30']['logs'][0]['am_time_in'] ?? null,
                'pm' => $json['fo30']['logs'][0]['pm_time_out'] ?? null,
                'hte' => $json['fo30']['logs'][0]['hte_signature_path'] ?? null,
                'tz' => $json['timezone'],
            ];
        }

        foreach (['faculty', 'coordinator', 'supervisor', 'director'] as $role) {
            $this->assertSame($ids['student'], $ids[$role], $role.' FO-30 diverged from student payload');
        }
        $this->assertSame($logo, $ids['student']['logo']);
        $this->assertSame(ManilaTime::TZ, $ids['student']['tz']);
        $this->assertSame('08:00', $ids['student']['am']);
        $this->assertSame('17:00', $ids['student']['pm']);
        $this->assertEquals(40.0, $ids['student']['hours']);
        $this->assertSame('signatures/'.$party['supervisor']->id.'_processed.png', $ids['student']['hte']);
        $this->assertStringContainsString('MONTEALEGRE', strtoupper($ids['student']['name']));
        $this->assertStringContainsString('REYES', strtoupper($ids['student']['supervisor']));
    }

    public function test_unverified_attendance_does_not_receive_hte_signature(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        AttendanceLog::create([
            'internship_id' => $party['internship']->id,
            'date' => '2026-08-24',
            'clock_in' => '00:00:00',
            'clock_out' => '09:00:00',
            'hours_rendered' => 8,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($party['student']);
        $log = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->json('fo30.logs.0');
        $this->assertFalse($log['validated']);
        $this->assertNull($log['hte_signature_path']);
        $this->assertSame('08:00', $log['am_time_in']);
        $this->assertSame('17:00', $log['pm_time_out']);
    }

    public function test_faculty_and_student_fo30_share_logo_and_manila_times(): void
    {
        Storage::fake('local');
        $party = $this->party();
        $logo = $this->uploadLogo($party['student'], 'accenture.png');
        app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);

        Sanctum::actingAs($party['faculty']);
        $roster = collect($this->getJson('/api/v1/faculty/assigned-students')->assertOk()->json('data'))
            ->first(fn ($row) => ($row['student']['student_number'] ?? null) === '2300592');
        $this->assertNotNull($roster);
        $this->assertSame($logo, $roster['company']['company_logo_path'] ?? null);
        $this->assertSame('08:00', $roster['attendance_logs'][0]['am_time_in']);
        $this->assertNotSame('00:00', $roster['attendance_logs'][0]['am_time_in']);
        $this->assertTrue($roster['attendance_logs'][0]['validated']);

        Sanctum::actingAs($party['student']);
        $student = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame($logo, $student['identity']['company_logo_path']);
        $this->assertSame($roster['attendance_logs'][0]['am_time_in'], $student['internship']['attendance'][0]['am_time_in']);
    }

    public function test_unauthorized_roles_cannot_read_another_interns_form(): void
    {
        $party = $this->party();
        Sanctum::actingAs($party['otherSupervisor']);
        $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertForbidden();
        Sanctum::actingAs($party['otherStudent']);
        $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertForbidden();
    }

    public function test_missing_logo_and_signature_stay_blank(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);
        $json = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->json();
        $this->assertNull($json['company_logo_path']);
        $this->assertNull($json['identity']['student_signature_path']);
        $this->assertNull($json['identity']['supervisor_signature_path']);

        $pdfData = app(OfficialFormDataService::class)->pdfDtr($party['internship']);
        $this->assertNull($pdfData['company_logo']);
        $this->assertNull($pdfData['student_signature']);
        $html = view('pdf.form30_dtr', $pdfData)->render();
        $this->assertStringContainsString('Logo', $html);
        $this->assertStringContainsString('HTE', $html);
    }

    public function test_logo_replace_and_remove_update_every_role_preview(): void
    {
        Storage::fake('local');
        $party = $this->party();
        $first = $this->uploadLogo($party['student'], 'logo-a.png');
        $second = $this->uploadLogo($party['student'], 'logo-b.png');
        $this->assertNotSame($first, $second);

        Sanctum::actingAs($party['faculty']);
        $this->assertSame($second, $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->json('company_logo_path'));
        Sanctum::actingAs($party['coordinator']);
        $this->assertSame($second, $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->json('company_logo_path'));

        Sanctum::actingAs($party['student']);
        $photos = collect($this->getJson('/api/v1/student/portfolio')->json('internship.portfolio.photos'));
        $logo = $photos->first(fn ($p) => ($p['type'] ?? '') === 'company_logo');
        $this->assertNotNull($logo);
        $this->deleteJson('/api/v1/student/portfolio/photos/'.$logo['id'])->assertOk();
        $this->assertEmpty($this->getJson('/api/v1/official-forms/'.$party['internship']->id)->json('company_logo_path'));
    }

    public function test_fo31_and_fo24_use_canonical_signatures(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Storage::disk('local')->put('signatures/'.$party['student']->id.'_processed.png', $this->png());
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'Configured interntrack DTR mapping',
            'challenges' => 'Timezone conversion',
            'learnings' => 'Use Asia/Manila explicitly',
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

        Sanctum::actingAs($party['student']);
        $bundle = $this->getJson('/api/v1/official-forms/'.$party['internship']->id)->assertOk()->json();
        $this->assertSame('signatures/'.$party['student']->id.'_processed.png', $bundle['identity']['student_signature_path']);
        $this->assertSame('signatures/'.$party['supervisor']->id.'_processed.png', $bundle['identity']['supervisor_signature_path']);
        $this->assertNotEmpty($bundle['journals']);
        $fo24 = collect($bundle['evaluations'])->firstWhere('form_type', 'FO-24');
        $this->assertNotNull($fo24);
        $this->assertSame('signatures/'.$party['supervisor']->id.'_processed.png', $fo24['signature_path']);
        $this->assertSame($party['supervisor']->id, (int) $fo24['evaluated_by']);
        $this->assertSame($party['internship']->supervisor_id, $party['internship']->fresh()->supervisor_id);
    }

    public function test_server_pdf_embeds_logo_and_manila_times(): void
    {
        Storage::fake('local');
        $party = $this->party();
        $this->uploadLogo($party['student'], 'accenture.png');
        Storage::disk('local')->put('signatures/'.$party['student']->id.'_processed.png', $this->png());
        Storage::disk('local')->put('signatures/'.$party['supervisor']->id.'_processed.png', $this->png());
        app(OneWeekOjtDemoService::class)->syncAttendance($party['internship'], $party['supervisor']);

        $html = view('pdf.form30_dtr', app(OfficialFormDataService::class)->pdfDtr($party['internship']))->render();
        $this->assertStringContainsString('data:image', $html);
        $this->assertStringContainsString('8:00 AM', $html);
        $this->assertStringContainsString('5:00 PM', $html);
        $this->assertStringContainsString('Accenture PH', $html);
        $this->assertStringNotContainsString('12:00 AM', $html);
        $this->assertSame('8:00 AM', OfficialFormAsset::clockLabel('08:00'));

        Sanctum::actingAs($party['faculty']);
        $pdf = $this->get('/api/v1/official-forms/'.$party['internship']->id.'/dtr.pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        Sanctum::actingAs($party['student']);
        $this->postJson('/api/v1/student/logbook', [
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => 'A',
            'challenges' => 'B',
            'learnings' => 'C',
        ])->assertCreated();
        $journalPdf = $this->get('/api/v1/official-forms/'.$party['internship']->id.'/journal.pdf');
        $journalPdf->assertOk();
        $this->assertStringStartsWith('%PDF', $journalPdf->getContent());
    }

    public function test_frontend_previews_use_shared_official_form_endpoint(): void
    {
        $faculty = file_get_contents(base_path('../frontend/src/pages/faculty/FacultyAssignedStudents.jsx'));
        $this->assertStringContainsString('openOfficialFo30', $faculty);
        $this->assertStringNotContainsString("companyLogoPath: row.company?.company_logo_path || ''", $faculty);
        $coord = file_get_contents(base_path('../frontend/src/pages/coordinator/CoordRecords.jsx'));
        $this->assertStringContainsString('openOfficialFo30', $coord);
        $dtr = file_get_contents(base_path('../frontend/src/components/portfolio/DailyTimeRecord.jsx'));
        $this->assertStringContainsString("log?.status === 'validated'", $dtr);
        $this->assertStringContainsString('objectFit: "contain"', $dtr);
    }

    public function test_angel_faculty_relationship_is_the_assigned_section_faculty(): void
    {
        $party = $this->party();
        $this->assertSame($party['faculty']->id, $party['otherInternship']->faculty_id);
        $this->assertSame('2300590', $party['otherStudent']->student_number);
        $this->assertSame($party['faculty']->id, $party['internship']->faculty_id);
    }
}
