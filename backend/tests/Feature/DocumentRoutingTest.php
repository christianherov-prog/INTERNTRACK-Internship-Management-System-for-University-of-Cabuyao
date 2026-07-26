<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class DocumentRoutingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_student_upload_then_coordinator_approve_forwards_to_faculty(): void
    {
        Storage::fake('public');

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $upload = $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'Application Letter',
            'attestation_name' => 'Test Student',
            'file' => UploadedFile::fake()->create('application.pdf', 100, 'application/pdf'),
        ]);
        $upload->assertCreated();
        $docId = $upload->json('document.id');

        $this->assertDatabaseHas('documents', [
            'id' => $docId,
            'status' => 'pending_review',
            'current_stage' => 'coordinator',
        ]);

        Sanctum::actingAs($coordinator);
        $this->patch("/api/v1/coordinator/documents/{$docId}/approve", [
            'remarks' => 'Looks good',
            'signer_name' => 'Coordinator Tester',
            'signature' => UploadedFile::fake()->image('sign.png'),
        ])->assertOk();

        $doc = Document::find($docId);
        $this->assertSame('pending_faculty', $doc->status);
        $this->assertSame('faculty', $doc->current_stage);
    }

    public function test_coordinator_reject_requires_remarks(): void
    {
        Storage::fake('public');

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $doc = Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'file_path' => 'tmp/x.pdf',
            'file_name' => 'x.pdf',
            'status' => 'pending_review',
            'current_stage' => 'coordinator',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($coordinator);
        $this->patchJson("/api/v1/coordinator/documents/{$doc->id}/reject", [])
            ->assertStatus(422);
    }

    public function test_faculty_verify_finalizes_document(): void
    {
        Storage::fake('public');

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $doc = Document::create([
            'internship_id' => $internship->id,
            'document_type' => 'Application Letter',
            'file_path' => 'tmp/x.pdf',
            'file_name' => 'x.pdf',
            'status' => 'pending_faculty',
            'current_stage' => 'faculty',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($faculty);
        $this->patch("/api/v1/faculty/documents/{$doc->id}/verify", [
            'signer_name' => 'Faculty Tester',
            'signature' => UploadedFile::fake()->image('sign.png'),
        ])
            ->assertOk();

        $this->assertSame('approved', $doc->fresh()->status);
        $this->assertSame('done', $doc->fresh()->current_stage);
    }

    public function test_upload_rejects_unknown_document_type(): void
    {
        Storage::fake('public');

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'Fake Document Type',
            'attestation_name' => 'Test Student',
            'file' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf'),
        ])->assertStatus(422);
    }
}
