<?php

namespace Tests\Feature;

use App\Mail\InternTrackNotificationMail;
use App\Models\Document;
use App\Models\Notification;
use App\Models\OjtRequirementTemplate;
use App\Models\RequirementTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class FacultyRequirementSubmissionReviewTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_faculty_sees_submitted_file_and_can_approve_or_reject(): void
    {
        Storage::fake('local');
        Mail::fake();

        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection('4BSCE-A');
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $this->mapFacultyForSection($faculty, '4BSCE-A');

        $template = OjtRequirementTemplate::create([
            'name' => 'MOA',
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
        $file = UploadedFile::fake()->create('moa.pdf', 40, 'application/pdf');
        $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'MOA',
            'files' => [$file],
        ])->assertCreated();

        Sanctum::actingAs($faculty);
        $list = $this->getJson('/api/v1/faculty/requirements')->assertOk();
        $sub = collect($list->json('data.0.submissions'))->firstWhere('student_id', $student->id);
        $this->assertNotNull($sub);
        $this->assertSame('pending', $sub['status']);
        $this->assertNotEmpty($sub['document_id']);
        $this->assertNotEmpty($sub['attachments']);

        $this->postJson('/api/v1/faculty/documents/'.$sub['document_id'].'/review', [
            'action' => 'approve',
            'remarks' => 'Looks good',
        ])->assertOk();

        $this->assertSame('approved', Document::find($sub['document_id'])?->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'document_approved',
        ]);
        Mail::assertSent(InternTrackNotificationMail::class, function (InternTrackNotificationMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->notifTitle === 'Document approved';
        });

        $this->postJson('/api/v1/faculty/documents/'.$sub['document_id'].'/review', [
            'action' => 'reject',
            'remarks' => 'Please resubmit a clearer scan',
        ])->assertOk();

        $this->assertSame('rejected', Document::find($sub['document_id'])?->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => 'document_rejected',
        ]);
        Mail::assertSent(InternTrackNotificationMail::class, function (InternTrackNotificationMail $mail) use ($student) {
            return $mail->hasTo($student->email)
                && $mail->notifTitle === 'Document rejected';
        });
    }

    public function test_document_decision_notifies_student_even_when_email_reminders_are_off(): void
    {
        Storage::fake('local');
        Mail::fake();

        $faculty = $this->makeUser('faculty');
        $coordinator = $this->makeUser('coordinator');
        $supervisor = $this->makeUser('supervisor');
        $student = $this->makeStudentWithSection('4BSCE-A');
        $student->update(['notification_preferences' => [
            'emailReminders' => false,
            'attendanceAlerts' => false,
            'evaluationReminders' => false,
        ]]);
        $company = $this->makeEligibleCompany();
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);
        $this->mapFacultyForSection($faculty, '4BSCE-A');

        $template = OjtRequirementTemplate::create([
            'name' => 'MOA',
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
        $file = UploadedFile::fake()->create('moa.pdf', 40, 'application/pdf');
        $this->post('/api/v1/student/documents/upload', [
            'document_type' => 'MOA',
            'files' => [$file],
        ])->assertCreated();

        $document = Document::where('document_type', 'MOA')->first();
        $this->assertNotNull($document);

        Sanctum::actingAs($faculty);
        $this->postJson('/api/v1/faculty/documents/'.$document->id.'/review', [
            'action' => 'approve',
            'remarks' => 'Approved',
        ])->assertOk();

        $this->assertNotNull(Notification::query()
            ->where('user_id', $student->id)
            ->where('type', 'document_approved')
            ->first());
        Mail::assertSent(InternTrackNotificationMail::class, 1);
    }
}
