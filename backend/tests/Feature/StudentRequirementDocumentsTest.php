<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\Notification;
use App\Models\OjtRequirementTemplate;
use App\Models\Program;
use App\Models\RequirementTarget;
use App\Models\StudentProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class StudentRequirementDocumentsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_student_sees_only_matching_program_and_section_requirements(): void
    {
        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection('4ITD');
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $visible = OjtRequirementTemplate::create([
            'name' => 'BSIT Resume',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 1,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $visible->id,
            'target_type' => 'program',
            'target_id' => 'Bachelor of Science in Information Technology',
        ]);

        $sectionReq = OjtRequirementTemplate::create([
            'name' => 'Section Waiver',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 2,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $sectionReq->id,
            'target_type' => 'section',
            'target_id' => '4ITD',
        ]);

        $other = OjtRequirementTemplate::create([
            'name' => 'Civil Engineering MOA',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 3,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $other->id,
            'target_type' => 'program',
            'target_id' => 'Bachelor of Science in Civil Engineering',
        ]);

        Sanctum::actingAs($student);
        $res = $this->getJson('/api/v1/student/documents')->assertOk();
        $names = collect($res->json('data'))->pluck('document_type')->all();

        $this->assertContains('BSIT Resume', $names);
        $this->assertContains('Section Waiver', $names);
        $this->assertNotContains('Civil Engineering MOA', $names);
    }

    public function test_resubmit_replaces_previous_files_and_notifies_faculty(): void
    {
        Storage::fake('local');

        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection('4ITD');
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $this->mapFacultyForSection($faculty, '4ITD');

        $template = OjtRequirementTemplate::create([
            'name' => 'Application Letter',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 1,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $template->id,
            'target_type' => 'student',
            'target_id' => (string) $student->id,
        ]);

        Sanctum::actingAs($student);
        $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'Application Letter',
            'files' => [UploadedFile::fake()->create('first.pdf', 20, 'application/pdf')],
        ])->assertCreated();

        $this->assertSame(1, Notification::query()
            ->where('user_id', $faculty->id)
            ->where('type', 'document_pending_faculty')
            ->count());

        $document = Document::where('document_type', 'Application Letter')->first();
        $this->assertNotNull($document);
        $this->assertSame(1, $document->attachments()->count());

        $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'Application Letter',
            'files' => [UploadedFile::fake()->create('second.pdf', 20, 'application/pdf')],
        ])->assertCreated();

        $this->assertSame(1, DocumentAttachment::where('document_id', $document->id)->count());
        $this->assertSame('second.pdf', $document->fresh()->attachments()->first()?->file_name);

        Sanctum::actingAs($faculty);
        $list = $this->getJson('/api/v1/faculty/requirements')->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $template->id);
        $this->assertNotNull($row);
        $this->assertSame('Student, Test', $row['targets'][0]['label'] ?? null);
        $sub = collect($row['submissions'])->firstWhere('student_id', $student->id);
        $this->assertSame('pending', $sub['status']);
        $this->assertCount(1, $sub['attachments']);
    }

    public function test_coe_student_does_not_inherit_bsit_program_fallback(): void
    {
        $department = Department::create([
            'name' => 'College of Engineering',
            'code' => 'COE',
            'is_active' => true,
        ]);
        $program = Program::create([
            'department_id' => $department->id,
            'name' => 'Bachelor of Science in Civil Engineering',
            'code' => 'BSCE',
            'is_active' => true,
        ]);

        $coordinator = $this->makeUser('coordinator');
        $faculty = $this->makeUser('faculty');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeUser('student', '2300611');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $student->student_number,
            'first_name' => 'Civil',
            'last_name' => 'Intern',
            'department_id' => $department->id,
            'program_id' => $program->id,
            'section' => '4BSCE-A',
            'school_year' => '2024-2025',
            'semester' => 2,
        ]);
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        $bsit = OjtRequirementTemplate::create([
            'name' => 'CCS Resume',
            'is_active' => true,
            'created_by' => $faculty->id,
            'sort_order' => 1,
        ]);
        RequirementTarget::create([
            'requirement_template_id' => $bsit->id,
            'target_type' => 'program',
            'target_id' => 'Bachelor of Science in Information Technology',
        ]);

        Sanctum::actingAs($student->fresh('studentProfile.program'));
        $names = collect($this->getJson('/api/v1/student/documents')->assertOk()->json('data'))
            ->pluck('document_type')
            ->all();

        $this->assertNotContains('CCS Resume', $names);
    }
}
