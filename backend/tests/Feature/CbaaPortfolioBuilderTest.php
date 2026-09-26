<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\PortfolioSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CbaaPortfolioBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function cbaaStudent(): User
    {
        $student = User::where('student_number', '2300605')->first();
        $this->assertNotNull($student, 'CBAA demo student 2300605 must be seeded.');
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/portfolio')->assertOk();

        return $student;
    }

    public function test_sections_are_sanitized_saved_and_returned_in_payload(): void
    {
        $this->cbaaStudent();

        $this->putJson('/api/v1/student/portfolio/sections', [
            'sections' => [
                'bio_sketch' => '<p style="text-align:center" onclick="steal()">Hello <script>alert(1)</script><strong>World</strong></p><img src=x onerror=alert(1)>',
                'rec_hte' => '<ul><li>More mentoring</li></ul>',
            ],
        ])->assertOk();

        $bio = PortfolioSection::where('section_key', 'bio_sketch')->value('content');
        $this->assertSame('<p style="text-align: center;">Hello <strong>World</strong></p>', $bio);

        $this->getJson('/api/v1/student/portfolio')
            ->assertOk()
            ->assertJsonPath('internship.portfolio.sections.rec_hte.content', '<ul><li>More mentoring</li></ul>')
            ->assertJsonStructure(['internship' => ['record_status' => ['documents', 'journals', 'attendance']]]);
    }

    public function test_unknown_section_keys_are_rejected(): void
    {
        $this->cbaaStudent();

        $this->putJson('/api/v1/student/portfolio/sections', ['sections' => ['assessment_ethical' => 'x']])
            ->assertStatus(422);
    }

    public function test_photo_caption_and_order_are_persisted_and_scoped_to_owner(): void
    {
        Storage::fake('local');
        $this->cbaaStudent();

        $ids = [];
        foreach (['first', 'second'] as $name) {
            $ids[] = $this->post('/api/v1/student/portfolio/photos', [
                'file' => UploadedFile::fake()->image("{$name}.jpg"),
                'type' => 'cbaa_hte_photo',
                'label' => $name,
            ], ['Accept' => 'application/json'])->assertCreated()->json('document.id');
        }

        $this->patchJson("/api/v1/student/portfolio/photos/{$ids[0]}", ['label' => 'Inventory count'])->assertOk();
        $this->postJson('/api/v1/student/portfolio/photos/reorder', ['ids' => [$ids[1], $ids[0]]])->assertOk();

        $this->assertSame('Inventory count', Document::find($ids[0])->remarks);
        $this->assertSame(1, Document::find($ids[1])->sort_order);
        $this->assertSame(2, Document::find($ids[0])->sort_order);

        // Another student cannot caption or reorder these files.
        $other = User::where('role', 'student')->where('student_number', '!=', '2300605')->whereHas('internshipsAsStudent')->first()
            ?? User::where('role', 'student')->where('student_number', '2300604')->first();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/student/portfolio');
        $this->patchJson("/api/v1/student/portfolio/photos/{$ids[0]}", ['label' => 'hijack'])->assertForbidden();
        $this->postJson('/api/v1/student/portfolio/photos/reorder', ['ids' => [$ids[0]]])->assertForbidden();
    }

    public function test_cbaa_uploads_accept_images_only(): void
    {
        Storage::fake('local');
        $this->cbaaStudent();

        foreach (['cbaa_app_ef_set', 'cbaa_training_plan', 'cbaa_hte_photo'] as $type) {
            $this->post('/api/v1/student/portfolio/photos', [
                'file' => UploadedFile::fake()->create('scan.pdf', 120, 'application/pdf'),
                'type' => $type,
            ], ['Accept' => 'application/json'])->assertStatus(422);

            $this->post('/api/v1/student/portfolio/photos', [
                'file' => UploadedFile::fake()->image('scan.jpg'),
                'type' => $type,
            ], ['Accept' => 'application/json'])->assertCreated();
        }
    }
}
