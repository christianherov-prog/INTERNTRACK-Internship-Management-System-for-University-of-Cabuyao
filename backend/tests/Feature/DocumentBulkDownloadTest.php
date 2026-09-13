<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DocumentBulkDownloadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_faculty_can_download_assigned_documents_and_others_cannot(): void
    {
        Storage::fake('local');
        $path = 'documents/1/requirement.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');

        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $otherFaculty = $this->makeUser('faculty');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $document = Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'status' => 'pending_faculty',
            'submitted_at' => now(),
        ]);
        DocumentAttachment::create([
            'document_id' => $document->id,
            'file_path' => $path,
            'file_name' => 'requirement.pdf',
        ]);

        Sanctum::actingAs($otherFaculty);
        $this->postJson('/api/v1/faculty/documents/bulk-download', [
            'document_ids' => [$document->id],
        ])->assertForbidden();

        Sanctum::actingAs($faculty);
        $this->post('/api/v1/faculty/documents/bulk-download', [
            'document_ids' => [$document->id],
        ])->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }
}
