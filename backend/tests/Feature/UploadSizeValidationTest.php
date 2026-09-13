<?php

namespace Tests\Feature;

use App\Models\OjtRequirementTemplate;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class UploadSizeValidationTest extends TestCase
{
    use CreatesInternshipFixtures;
    use RefreshDatabase;

    public function test_upload_limits_config_defaults_to_ten_mb(): void
    {
        $this->assertSame(10, UploadLimits::maxMb());
        $this->assertSame(10240, UploadLimits::maxKb());
        $this->assertGreaterThanOrEqual(UploadLimits::maxMb(), UploadLimits::maxRequestMb());
    }

    public function test_requirement_template_rejects_oversized_attachment(): void
    {
        Storage::fake('local');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();

        Sanctum::actingAs($faculty);
        $over = UploadedFile::fake()->create('huge.pdf', UploadLimits::maxKb() + 1, 'application/pdf');

        $before = OjtRequirementTemplate::count();
        $res = $this->post('/api/v1/faculty/requirements', [
            'name' => 'Oversized Attachment Test',
            'description' => 'Should fail',
            'category' => 'general',
            'targets' => [
                ['type' => 'student', 'id' => $student->id],
            ],
            'template_files' => [$over],
        ], [
            'Accept' => 'application/json',
        ]);

        $res->assertStatus(422);
        $this->assertSame($before, OjtRequirementTemplate::count());
        $errors = $res->json('errors');
        $this->assertNotEmpty($errors);
    }

    public function test_requirement_template_accepts_file_at_limit(): void
    {
        Storage::fake('local');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();

        Sanctum::actingAs($faculty);
        $ok = UploadedFile::fake()->create('ok.pdf', UploadLimits::maxKb(), 'application/pdf');

        $res = $this->post('/api/v1/faculty/requirements', [
            'name' => 'At Limit Attachment',
            'description' => 'Should pass',
            'category' => 'general',
            'targets' => [
                ['type' => 'student', 'id' => $student->id],
            ],
            'template_files' => [$ok],
        ], [
            'Accept' => 'application/json',
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('ojt_requirement_templates', [
            'name' => 'At Limit Attachment',
            'created_by' => $faculty->id,
        ]);
    }

    public function test_student_document_upload_rejects_oversized_file(): void
    {
        Storage::fake('local');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $faculty);

        OjtRequirementTemplate::updateOrCreate(
            ['system_code' => 'application_letter'],
            [
                'name' => 'Application Letter',
                'description' => 'Application Letter',
                'category' => 'general',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 1,
            ]
        );

        Sanctum::actingAs($student);
        $over = UploadedFile::fake()->create('big.pdf', UploadLimits::maxKb() + 2, 'application/pdf');

        $res = $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'Application Letter',
            'files' => [$over],
        ], [
            'Accept' => 'application/json',
        ]);

        $res->assertStatus(422);
    }
}
