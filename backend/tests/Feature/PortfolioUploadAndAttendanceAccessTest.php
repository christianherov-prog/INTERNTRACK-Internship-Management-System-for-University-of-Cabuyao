<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\InternshipApplication;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PortfolioUploadAndAttendanceAccessTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    private function party(string $section = '4ITD', bool $withSupervisor = true): array
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty, $section);
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection($section);
        $company = $this->makeEligibleCompany(['company_name' => 'Accenture PH']);
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        if (! $withSupervisor) {
            $internship->update(['supervisor_id' => null]);
        }

        return compact('coordinator', 'faculty', 'supervisor', 'student', 'company', 'internship');
    }

    public function test_documents_table_does_not_store_file_path(): void
    {
        $this->assertFalse(Schema::hasColumn('documents', 'file_path'));
        $this->assertTrue(Schema::hasColumn('document_attachments', 'file_path'));
    }

    public function test_company_logo_upload_persists_on_attachments_and_preview(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $upload = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image('logo.png', 80, 80),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = $upload->json('document.file_path');
        $this->assertNotEmpty($path);
        $this->assertStringStartsWith('internships/'.$party['internship']->id.'/portfolio/', $path);
        Storage::disk('local')->assertExists($path);

        $this->assertDatabaseHas('document_attachments', [
            'file_path' => $path,
            'file_name' => 'logo.png',
        ]);

        $documentId = $upload->json('document.id');
        $this->assertDatabaseHas('documents', [
            'id' => $documentId,
            'internship_id' => $party['internship']->id,
            'document_type' => 'company_logo',
            'status' => 'approved',
        ]);

        $preview = $this->getJson('/api/v1/student/portfolio')->assertOk()->json();
        $this->assertSame($path, $preview['internship']['portfolio']['company_logo_path']);
        $photos = collect($preview['internship']['portfolio']['photos']);
        $this->assertTrue($photos->contains(fn ($doc) => ($doc['type'] ?? '') === 'company_logo' && ($doc['file_path'] ?? '') === $path));
    }

    public function test_org_chart_and_ojt_photo_and_image_certificate_upload(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'org_chart',
            'file' => UploadedFile::fake()->image('chart.jpg', 120, 80),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'ojt_photo',
            'week_number' => 2,
            'label' => 'Standup at Accenture',
            'file' => UploadedFile::fake()->image('week2.png', 60, 60),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'training_certificate',
            'file' => UploadedFile::fake()->image('certificate.png', 80, 80),
        ], ['Accept' => 'application/json'])->assertCreated();

        $preview = $this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.portfolio');
        $this->assertNotEmpty($preview['org_chart_path']);
        $photos = collect($preview['photos']);
        $ojt = $photos->first(fn ($doc) => ($doc['type'] ?? '') === 'ojt_photo');
        $this->assertSame(2, (int) ($ojt['week_number'] ?? 0));
        $this->assertSame('Standup at Accenture', $ojt['label'] ?? null);
        $this->assertTrue($photos->contains(fn ($doc) => ($doc['type'] ?? '') === 'training_certificate'));
    }

    public function test_portfolio_builder_rejects_pdf_for_all_upload_types(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'training_certificate',
            'file' => UploadedFile::fake()->create('certificate.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_image_section_rejects_pdf_and_executables(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->create('logo.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'training_certificate',
            'file' => UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_ojt_photo_requires_week_number(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Sanctum::actingAs($party['student']);

        $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'ojt_photo',
            'file' => UploadedFile::fake()->image('photo.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_other_student_cannot_download_or_delete_portfolio_file(): void
    {
        Storage::fake('local');
        $partyA = $this->party();
        $partyB = $this->party('4ITA');
        Sanctum::actingAs($partyA['student']);

        $path = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image('secret.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated()->json('document.file_path');
        $documentId = Document::where('internship_id', $partyA['internship']->id)->value('id');

        Sanctum::actingAs($partyB['student']);
        $this->getJson('/api/v1/files/download?path='.urlencode($path))->assertForbidden();
        $this->deleteJson('/api/v1/student/portfolio/photos/'.$documentId)->assertForbidden();
    }

    public function test_moa_from_placement_application_is_linked_without_duplicate_upload(): void
    {
        Storage::fake('local');
        $party = $this->party();
        Storage::disk('local')->put('placement-moa/applications/9/moa.pdf', 'moa-bytes');
        InternshipApplication::create([
            'student_id' => $party['student']->id,
            'company_id' => $party['company']->id,
            'status' => 'approved',
            'moa_path' => 'placement-moa/applications/9/moa.pdf',
            'moa_original_name' => 'Accenture MOA.pdf',
        ]);

        Sanctum::actingAs($party['student']);
        $photos = collect($this->getJson('/api/v1/student/portfolio')->assertOk()->json('internship.portfolio.photos'));
        $this->assertTrue($photos->contains(fn ($doc) => ($doc['file_path'] ?? '') === 'placement-moa/applications/9/moa.pdf'));
    }

    public function test_attendance_locked_without_supervisor_and_open_with_approved_supervisor(): void
    {
        $locked = $this->party('4ITD', false);
        Sanctum::actingAs($locked['student']);
        $this->getJson('/api/v1/student/attendance')
            ->assertForbidden()
            ->assertJsonPath('message', 'Attendance tracking is locked until your HTE Supervisor is approved.');
        $this->getJson('/api/v1/student/supervisor-invite/status')
            ->assertOk()
            ->assertJsonPath('has_supervisor', false);

        $locked['internship']->update(['supervisor_id' => $locked['supervisor']->id]);
        $this->getJson('/api/v1/student/attendance')->assertOk();
        $this->getJson('/api/v1/student/supervisor-invite/status')
            ->assertOk()
            ->assertJsonPath('has_supervisor', true)
            ->assertJsonPath('state', 'assigned');
    }

    public function test_unrelated_supervisor_cannot_see_assigned_intern(): void
    {
        $party = $this->party();
        $stranger = $this->makeUser('supervisor', 'SUP-STRANGER');
        Sanctum::actingAs($stranger);
        $payload = $this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json();
        $ids = collect($payload['data'] ?? $payload)->pluck('id');
        $this->assertFalse($ids->contains($party['internship']->id));

        Sanctum::actingAs($party['supervisor']);
        $mine = $this->getJson('/api/v1/supervisor/assigned-interns')->assertOk()->json();
        $mineIds = collect($mine['data'] ?? $mine)->pluck('id');
        $this->assertTrue($mineIds->contains($party['internship']->id));
    }

    public function test_api_query_exception_does_not_expose_sql(): void
    {
        $request = Request::create('/api/v1/student/portfolio/photos', 'POST');
        $request->headers->set('Accept', 'application/json');
        $exception = new QueryException(
            'mysql',
            'insert into `documents` (`file_path`) values (?)',
            ['x'],
            new \Exception('SQLSTATE[42S22]: Column not found: 1054 Unknown column \'file_path\' in \'field list\'')
        );

        $response = app(ExceptionHandler::class)->render($request, $exception);
        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('Something went wrong. Please try again.', $payload['message'] ?? null);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('file_path', $response->getContent());
    }
}
