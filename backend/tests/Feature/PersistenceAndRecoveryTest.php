<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use App\Services\SupervisorFeedbackService;
use App\Support\InternTrackBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesInternshipFixtures;
use Tests\TestCase;

class PersistenceAndRecoveryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesInternshipFixtures;

    public function test_attendance_and_feedback_persist_after_refetch(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $this->postJson('/api/v1/student/attendance/clock-in')->assertCreated();
        $this->postJson('/api/v1/student/attendance/clock-out')->assertOk();

        $logId = AttendanceLog::where('internship_id', $internship->id)->value('id');
        $this->assertNotNull($logId);

        $this->getJson('/api/v1/student/attendance')->assertOk()
            ->assertJsonPath('today_status', 'clocked_out');

        Sanctum::actingAs($supervisor);
        $this->postJson('/api/v1/supervisor/feedback/'.$internship->id, [
            'feedback' => 'Server-side intern feedback that must survive a new HTTP request.',
        ])->assertOk();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/student/supervisor-feedback')
            ->assertOk()
            ->assertJsonPath('intern_feedback.internship_id', $internship->id);

        Sanctum::actingAs($faculty);
        $this->getJson('/api/v1/faculty/supervisor-feedback')->assertOk()
            ->assertJsonPath('data.0.internship_id', $internship->id);

        $this->assertDatabaseHas('journal_entries', [
            'internship_id' => $internship->id,
            'status' => SupervisorFeedbackService::NOTE_STATUS,
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'id' => $logId,
            'internship_id' => $internship->id,
        ]);
    }

    public function test_portfolio_file_persists_on_configured_disk(): void
    {
        Storage::fake('local');
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        Sanctum::actingAs($student);
        $path = $this->post('/api/v1/student/portfolio/photos', [
            'type' => 'company_logo',
            'file' => UploadedFile::fake()->image('logo.png', 40, 40),
        ], ['Accept' => 'application/json'])->assertCreated()->json('document.file_path');

        Storage::disk('local')->assertExists($path);
        $this->getJson('/api/v1/student/portfolio')->assertOk();
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_feedback_transaction_rolls_back_when_write_fails(): void
    {
        $faculty = $this->makeUser('faculty');
        $this->mapFacultyForSection($faculty);
        $student = $this->makeStudentWithSection();
        $company = $this->makeEligibleCompany();
        $supervisor = $this->makeUser('supervisor');
        $coordinator = $this->makeUser('coordinator');
        $internship = $this->makeActiveInternship($student, $company, $supervisor, $faculty, $coordinator);

        try {
            DB::transaction(function () use ($internship, $supervisor) {
                JournalEntry::create([
                    'internship_id' => $internship->id,
                    'week_number' => 0,
                    'entry_number' => 0,
                    'date' => now()->toDateString(),
                    'status' => SupervisorFeedbackService::NOTE_STATUS,
                    'supervisor_feedback' => 'Should roll back with the transaction.',
                    'supervisor_reviewed_by' => $supervisor->id,
                    'supervisor_reviewed_at' => now(),
                ]);
                throw new \RuntimeException('forced failure');
            });
        } catch (\Throwable $e) {
            $this->assertSame('forced failure', $e->getMessage());
        }

        $this->assertSame(0, JournalEntry::query()->where('internship_id', $internship->id)->count());
    }

    public function test_backup_command_refuses_restore_without_force(): void
    {
        $root = storage_path('framework/testing/interntrack-backup-'.uniqid());
        File::ensureDirectoryExists($root.'/storage-private');
        File::put($root.'/storage-private/marker.txt', 'ok');

        $this->artisan('interntrack:restore', [
            'backup' => $root,
            '--files-only' => true,
        ])->assertFailed();

        File::deleteDirectory($root);
    }

    public function test_backup_service_copies_files_to_destination(): void
    {
        $source = storage_path('framework/testing/interntrack-src-'.uniqid());
        $root = storage_path('framework/testing/interntrack-backup-unit-'.uniqid());
        File::ensureDirectoryExists($source.'/backup-probe');
        File::put($source.'/backup-probe/marker.txt', 'durable');

        $service = new InternTrackBackup();
        $dir = $service->backupDirectory($root);
        $copied = $service->copyPrivateStorage($dir, $source);
        $this->assertGreaterThan(0, $copied);
        $this->assertFileExists($dir.'/storage-private/backup-probe/marker.txt');

        $restoreTo = storage_path('framework/testing/interntrack-restore-'.uniqid());
        $service->restorePrivateStorage($dir, $restoreTo);
        $this->assertFileExists($restoreTo.'/backup-probe/marker.txt');
        $this->assertSame('durable', File::get($restoreTo.'/backup-probe/marker.txt'));

        File::deleteDirectory($source);
        File::deleteDirectory($root);
        File::deleteDirectory($restoreTo);
    }
}
