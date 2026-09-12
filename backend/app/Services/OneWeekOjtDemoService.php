<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\Internship;
use App\Models\InternshipPlacement;
use App\Models\JournalEntry;
use App\Models\Message;
use App\Models\Notification;
use App\Models\StudentPortfolio;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\InternshipProvisioning;
use App\Support\InternshipStatuses;
use App\Support\ManilaAttendanceClock;
use App\Support\NameParts;
use App\Support\SignatureCapture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OneWeekOjtDemoService
{
    public const STUDENT_NUMBER = '2300592';

    public const WEEK_DATES = [
        ['date' => '2026-08-24', 'in' => '08:00', 'out' => '17:00'],
        ['date' => '2026-08-25', 'in' => '08:03', 'out' => '17:03'],
        ['date' => '2026-08-26', 'in' => '07:58', 'out' => '16:58'],
        ['date' => '2026-08-27', 'in' => '08:02', 'out' => '17:02'],
        ['date' => '2026-08-28', 'in' => '08:00', 'out' => '17:00'],
        // Controlled second week (Aug 31–Sep 4). Sep 7 is intentionally omitted
        // so FO-30 derives full-day Absent after the scheduled shift ends.
        ['date' => '2026-08-31', 'in' => '08:00', 'out' => '17:00'],
        ['date' => '2026-09-01', 'in' => '08:00', 'out' => '17:00'],
        ['date' => '2026-09-02', 'in' => '08:00', 'out' => '17:00'],
        ['date' => '2026-09-03', 'in' => '08:00', 'out' => '17:00'],
        ['date' => '2026-09-04', 'in' => '08:00', 'out' => '17:00'],
    ];

    /** Dates that must remain without attendance punches (derived Absent). */
    public const ABSENT_DATES = [
        '2026-09-07',
    ];

    public const LUNCH_OUT = '12:00';

    public const LUNCH_IN = '13:00';

    public const ACCOMPLISHMENT = 'During my first week of internship, I became familiar with the company\'s work environment, policies, communication practices, and daily workflow. I attended orientation activities, reviewed workplace procedures, and completed introductory tasks assigned to me. I also practiced organizing work-related information and following established instructions while coordinating with the people around me.';

    public const DIFFICULTIES = 'My main challenge during the first week was adjusting to a new professional environment and becoming familiar with workplace procedures and expectations. Some tasks and processes were initially unfamiliar, so I needed to review instructions carefully and ask appropriate questions before proceeding.';

    public const INSIGHTS = 'I learned the importance of professional communication, proper documentation, time management, teamwork, and following organizational procedures. I also learned that confirming instructions before performing a task helps prevent errors and improves the quality and consistency of work.';

    public const FEEDBACK = 'Clarence demonstrated a positive attitude during his first week of internship. He followed instructions, communicated respectfully, and showed willingness to learn. He should continue improving his familiarity with the work process and develop greater confidence in completing assigned tasks independently.';

    public const CHAPTER3 = [
        'assessment_ethical' => 'My internship experience helped me understand that an IT professional must combine technical competence with responsibility, confidentiality, ethical conduct, and respect for organizational policies. Proper handling of information, accountability for assigned tasks, and professional communication are important responsibilities in a workplace environment.',
        'assessment_learnings' => 'I learned that technical knowledge is only one part of professional work. Communication, documentation, teamwork, adaptability, and time management are also essential when completing tasks and working with other people.',
        'assessment_experience' => 'My first week gave me the opportunity to interact with people in a professional environment. I learned to listen carefully, communicate respectfully, ask questions when needed, and cooperate with others when completing assigned activities.',
        'assessment_standards' => 'I observed the importance of following documented procedures, checking the accuracy of work, protecting information, communicating changes properly, and maintaining organized records.',
        'assessment_recommendations' => 'I recommend maintaining clear communication among students, Faculty, Coordinators, and Industry Supervisors throughout the internship. Providing clear requirements and regular monitoring can help students complete their internship responsibilities more effectively.',
        'assessment_advice' => 'Future interns should be willing to learn, communicate professionally, manage their time responsibly, and remain open to feedback. They should also document their activities regularly and ask appropriate questions whenever instructions are unclear.',
    ];

    public const WEEK2_ACCOMPLISHMENT = 'During the second week of the internship, I continued performing assigned tasks while becoming more familiar with the company\'s workflow and procedures. I applied the instructions introduced during the first week, organized assigned work more independently, maintained proper documentation, and coordinated with the appropriate personnel when clarification was required.';

    public const WEEK2_DIFFICULTIES = 'Some tasks required additional familiarity with the company\'s internal processes and expected work procedures. I addressed these challenges by reviewing previous instructions, verifying task requirements, and asking for clarification when necessary before proceeding.';

    public const WEEK2_INSIGHTS = 'The second week strengthened my understanding of professional communication, task organization, documentation, time management, and the importance of following established procedures. I also became more confident in handling assigned responsibilities while maintaining accuracy and coordination with other personnel.';

    public const PORTFOLIO_IMAGE_TYPES = [
        'company_logo',
        'org_chart',
        'vision_mission',
        'ojt_photo',
        'registration_form',
        'medical_result',
        'psychological_result',
        'application_letter',
        'student_cv',
        'recommendation_request',
        'acceptance_form',
        'consent_form',
        'training_plan',
        'moa_document',
        'visitation_form',
        'completion_certificate',
        'training_certificate',
        'training_test_result',
        'training_documentation',
        'exam_certificate',
        'exam_test_result',
        'exam_documentation',
    ];

    /**
     * @return array<string, mixed>
     */
    public function reconcileStudent(string $studentNumber = self::STUDENT_NUMBER): array
    {
        $student = User::with(['studentProfile.program', 'studentProfile.department'])
            ->where('student_number', $studentNumber)
            ->first();

        if (! $student) {
            throw new \RuntimeException("Student {$studentNumber} was not found. Do not create a new Clarence account.");
        }

        SignatureCapture::ensureDemoProfileSignature($student);

        $internship = InternshipProvisioning::openForStudent($student->id)
            ?? $student->internshipsAsStudent()->latest('id')->first();

        if (! $internship) {
            throw new \RuntimeException('Clarence has no internship to reconcile. Do not create another internship without an existing record.');
        }

        $internship->load([
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
            'coordinator.facultyProfile',
            'currentPlacement',
            'student.studentProfile.program',
            'student.studentProfile.department',
        ]);

        $supervisor = $internship->supervisor;
        $adrian = User::with('supervisorProfile')
            ->where('role', 'supervisor')
            ->where('faculty_number', 'SUP-0002')
            ->first();

        if (! $adrian) {
            throw new \RuntimeException('Adrian Reyes (SUP-0002) was not found. Do not create a duplicate supervisor.');
        }

        // Authoritative FK repair: internship/placement must use SUP-0002 by ID, never by first-name match.
        if (! $supervisor || (int) $supervisor->id !== (int) $adrian->id) {
            $internship->update(['supervisor_id' => $adrian->id]);
            $internship->setRelation('supervisor', $adrian);
            $supervisor = $adrian;
        }

        $supervisorName = NameParts::fromProfile($supervisor->supervisorProfile);
        $isAdrian = strcasecmp((string) $supervisor->faculty_number, 'SUP-0002') === 0;
        if (! $isAdrian) {
            throw new \RuntimeException("Refusing to continue: current supervisor is [{$supervisorName}] (user #{$supervisor->id}), not Adrian Reyes / SUP-0002.");
        }

        if (Schema::hasTable('internship_placements')) {
            InternshipPlacementService::ensurePlacements($internship);
            $internship->refresh();
            if ($internship->current_placement_id) {
                InternshipPlacement::whereKey($internship->current_placement_id)
                    ->where(function ($q) use ($supervisor) {
                        $q->whereNull('supervisor_id')->orWhere('supervisor_id', '!=', $supervisor->id);
                    })
                    ->update(['supervisor_id' => $supervisor->id]);
            }
        }

        $live = InternshipStatuses::liveMonitoring();
        $status = $internship->status;
        if ($status === 'completed' || ! in_array($status, $live, true)) {
            $status = 'ongoing';
        }

        $target = InternshipProgressService::targetHoursForInternship($internship, $student->studentProfile);
        if ($target <= 0) {
            $target = 500;
        }

        $internship->update([
            'supervisor_id' => $supervisor->id,
            'company_id' => $internship->company_id,
            'target_hours' => $target,
            'start_date' => '2026-08-24',
            'end_date' => null,
            'status' => $status === 'completed' ? 'ongoing' : $status,
        ]);

        $this->syncApprovedSchedule($internship, $student, $supervisor);
        $attendance = $this->syncAttendance($internship, $supervisor);
        $journal = $this->syncWeek1Journal($internship, $student);
        $week2Journal = $this->syncWeek2Journal($internship, $student);
        $feedback = $this->syncFeedback($internship, $supervisor);
        $portfolio = $this->syncPortfolioText($internship, $student);
        $uploads = $this->syncPortfolioImages($internship);
        $message = $this->syncMessage($internship, $student, $supervisor);

        InternshipProgressService::synchronize($internship);
        $internship->refresh();
        $progress = InternshipProgressService::snapshot($internship);
        $eligibility = InternshipProgressService::evaluationEligibility($internship);

        return [
            'student' => $student,
            'internship' => $internship->fresh([
                'company',
                'supervisor.supervisorProfile',
                'faculty.facultyProfile',
                'coordinator.facultyProfile',
                'student.studentProfile.program',
                'student.studentProfile.department',
            ]),
            'supervisor' => $supervisor,
            'supervisor_name' => $supervisorName,
            'attendance' => $attendance,
            'journal' => $journal,
            'week2_journal' => $week2Journal,
            'feedback' => $feedback,
            'portfolio' => $portfolio,
            'uploads' => $uploads,
            'message' => $message,
            'progress' => $progress,
            'evaluation_eligibility' => $eligibility,
        ];
    }

    /**
     * @return list<AttendanceLog>
     */
    public function syncAttendance(Internship $internship, User $supervisor): array
    {
        $keep = collect(self::WEEK_DATES)->pluck('date')->all();
        $htePath = SignatureCapture::profilePath($supervisor);
        $hteName = NameParts::fromProfile($supervisor->supervisorProfile);

        AttendanceLog::withTrashed()
            ->where('internship_id', $internship->id)
            ->whereNotIn('date', $keep)
            ->get()
            ->each(function (AttendanceLog $log) {
                $log->forceDelete();
            });

        if (Schema::hasTable('attendance_correction_requests')) {
            AttendanceCorrectionRequest::query()
                ->where('internship_id', $internship->id)
                ->whereNotIn('date', $keep)
                ->get()
                ->each
                ->delete();
        }

        $rows = [];
        foreach (self::WEEK_DATES as $day) {
            $hours = ManilaAttendanceClock::creditedHours(
                $day['in'],
                self::LUNCH_OUT,
                self::LUNCH_IN,
                $day['out']
            );
            $payload = [
                'internship_id' => $internship->id,
                'placement_id' => $internship->current_placement_id,
                'date' => $day['date'],
                'clock_in' => ManilaAttendanceClock::storedTime($day['in']),
                'clock_out' => ManilaAttendanceClock::storedTime($day['out']),
                'break_start' => Carbon::parse($day['date'].' '.ManilaAttendanceClock::storedTime(self::LUNCH_OUT), config('app.timezone', 'UTC')),
                'break_end' => Carbon::parse($day['date'].' '.ManilaAttendanceClock::storedTime(self::LUNCH_IN), config('app.timezone', 'UTC')),
                'on_break' => false,
                'am_time_in' => ManilaAttendanceClock::storedTime($day['in']),
                'am_time_out' => ManilaAttendanceClock::storedTime(self::LUNCH_OUT),
                'pm_time_in' => ManilaAttendanceClock::storedTime(self::LUNCH_IN),
                'pm_time_out' => ManilaAttendanceClock::storedTime($day['out']),
                'hours_rendered' => $hours,
                'status' => 'validated',
                'validated_by' => $supervisor->id,
                'validated_at' => now(),
                'hte_signature_path' => $htePath,
                'hte_signed_name' => $hteName ?: null,
                'hte_signed_at' => $htePath ? now() : null,
            ];

            $log = AttendanceLog::withTrashed()
                ->where('internship_id', $internship->id)
                ->whereDate('date', $day['date'])
                ->first();

            if ($log) {
                if ($log->trashed()) {
                    $log->restore();
                }
                $log->update($payload);
            } else {
                $log = AttendanceLog::create($payload);
            }

            $rows[] = $log->fresh();
        }

        return $rows;
    }

    /**
     * Ensure Clarence (and demo reconcile) has an approved 08:00–17:00 Manila schedule
     * covering the controlled attendance window so Sep 7 can resolve as Absent.
     */
    public function syncApprovedSchedule(Internship $internship, User $student, User $supervisor): WorkSchedule
    {
        WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'approved')
            ->update([
                'status' => 'superseded',
                'effective_to' => '2026-08-23',
            ]);

        $existing = WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'approved')
            ->whereDate('effective_from', '2026-08-24')
            ->first();

        $payload = [
            'internship_id' => $internship->id,
            'proposed_by' => $student->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'status' => 'approved',
            'effective_from' => '2026-08-24',
            'effective_to' => null,
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
            'review_remarks' => 'Controlled demo schedule (Asia/Manila 8:00 AM–5:00 PM).',
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->fresh();
        }

        return WorkSchedule::create($payload);
    }

    public function syncWeek1Journal(Internship $internship, User $student): JournalEntry
    {
        $payload = [
            'entry_number' => 1,
            'week_number' => 1,
            'date' => '2026-08-24',
            'end_date' => '2026-08-28',
            'activities_summary' => self::ACCOMPLISHMENT,
            'challenges' => self::DIFFICULTIES,
            'learnings' => self::INSIGHTS,
            'status' => 'approved',
            'faculty_reviewed_by' => $internship->faculty_id,
            'faculty_reviewed_at' => now(),
            'score' => 92,
            'faculty_feedback' => 'Week 1 journal is complete and reflects the first OJT week.',
        ];

        $journal = JournalEntry::withTrashed()
            ->where('internship_id', $internship->id)
            ->where('week_number', 1)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', SupervisorFeedbackService::NOTE_STATUS);
            })
            ->first();

        if ($journal) {
            if ($journal->trashed()) {
                $journal->restore();
            }
            $journal->update($payload);
        } else {
            $journal = $internship->journals()->create($payload);
        }

        if ($internship->faculty_id) {
            Notification::notify(
                (int) $student->id,
                'journal_reviewed',
                'Journal Approved by Faculty',
                'Your Week 1 journal was approved by your faculty supervisor.',
                '/student/logbook',
                ['journal_id' => $journal->id, 'week_number' => 1, 'action' => 'approved']
            );
        }

        return $journal->fresh();
    }

    public function syncWeek2Journal(Internship $internship, User $student): JournalEntry
    {
        $payload = [
            'entry_number' => 2,
            'week_number' => 2,
            'date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'activities_summary' => self::WEEK2_ACCOMPLISHMENT,
            'challenges' => self::WEEK2_DIFFICULTIES,
            'learnings' => self::WEEK2_INSIGHTS,
            'status' => 'approved',
            'faculty_reviewed_by' => $internship->faculty_id,
            'faculty_reviewed_at' => now(),
            'score' => 94,
            'faculty_feedback' => 'Week 2 journal is complete and reflects continued internship progress.',
        ];

        $journal = JournalEntry::withTrashed()
            ->where('internship_id', $internship->id)
            ->where('week_number', 2)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', SupervisorFeedbackService::NOTE_STATUS);
            })
            ->first();

        if ($journal) {
            if ($journal->trashed()) {
                $journal->restore();
            }
            $journal->update($payload);
        } else {
            $journal = $internship->journals()->create($payload);
        }

        // Keep only controlled Week 1–2 academic journals for this demo account.
        $internship->journals()
            ->academic()
            ->whereNotIn('week_number', [1, 2])
            ->get()
            ->each
            ->delete();

        if ($internship->faculty_id) {
            Notification::notify(
                (int) $student->id,
                'journal_reviewed',
                'Journal Approved by Faculty',
                'Your Week 2 journal was approved by your faculty supervisor.',
                '/student/logbook',
                ['journal_id' => $journal->id, 'week_number' => 2, 'action' => 'approved']
            );
        }

        return $journal->fresh();
    }

    public function syncFeedback(Internship $internship, User $supervisor): JournalEntry
    {
        return app(SupervisorFeedbackService::class)->upsert($internship, $supervisor, self::FEEDBACK);
    }

    public function syncPortfolioText(Internship $internship, User $student): StudentPortfolio
    {
        $existing = StudentPortfolio::where('internship_id', $internship->id)->first();
        $company = $internship->company;

        return StudentPortfolio::updateOrCreate(
            ['internship_id' => $internship->id],
            array_merge(self::CHAPTER3, [
                'user_id' => $student->id,
                'company_name' => $existing?->company_name ?: ($company?->company_name ?? null),
                'company_address' => $existing?->company_address ?: ($company?->address ?? null),
                'company_vision' => $existing?->company_vision,
                'company_mission' => $existing?->company_mission,
                'company_history' => $existing?->company_history,
            ])
        );
    }

    /**
     * @return list<array{type: string, path: string, replaced: bool}>
     */
    public function syncPortfolioImages(Internship $internship): array
    {
        $png = $this->tinyPng();
        $results = [];

        foreach (self::PORTFOLIO_IMAGE_TYPES as $type) {
            $document = Document::withTrashed()
                ->where('internship_id', $internship->id)
                ->where('document_type', $type)
                ->first();

            $replaced = false;
            if ($document) {
                if ($document->trashed()) {
                    $document->restore();
                }
                $document->load('attachments');
                foreach ($document->attachments as $old) {
                    if ($old->file_path) {
                        foreach (['local', 'public'] as $disk) {
                            if (Storage::disk($disk)->exists($old->file_path)) {
                                Storage::disk($disk)->delete($old->file_path);
                            }
                        }
                    }
                    $old->delete();
                    $replaced = true;
                }
                $document->update([
                    'week_number' => $type === 'ojt_photo' ? 1 : null,
                    'status' => 'approved',
                    'current_stage' => 'completed',
                    'remarks' => $type === 'ojt_photo' ? 'Week 1 orientation' : $type,
                    'submitted_at' => now(),
                ]);
            } else {
                $document = $internship->documents()->create([
                    'document_type' => $type,
                    'week_number' => $type === 'ojt_photo' ? 1 : null,
                    'status' => 'approved',
                    'current_stage' => 'completed',
                    'remarks' => $type === 'ojt_photo' ? 'Week 1 orientation' : $type,
                    'submitted_at' => now(),
                ]);
            }

            $name = $type.'_demo.png';
            $path = "internships/{$internship->id}/portfolio/".Str::uuid().'_'.$name;
            Storage::disk('local')->put($path, $png);
            $document->attachments()->create([
                'file_path' => $path,
                'file_name' => $name,
                'file_size' => strlen($png),
                'mime_type' => 'image/png',
            ]);

            $results[] = [
                'type' => $type,
                'path' => $path,
                'replaced' => $replaced,
            ];
        }

        return $results;
    }

    public function syncMessage(Internship $internship, User $student, User $supervisor): Message
    {
        $existing = Message::query()
            ->where('internship_id', $internship->id)
            ->where(function ($q) use ($student, $supervisor) {
                $q->where(function ($inner) use ($student, $supervisor) {
                    $inner->where('sender_id', $supervisor->id)->where('recipient_id', $student->id);
                })->orWhere(function ($inner) use ($student, $supervisor) {
                    $inner->where('sender_id', $student->id)->where('recipient_id', $supervisor->id);
                });
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        $message = Message::create([
            'internship_id' => $internship->id,
            'sender_id' => $supervisor->id,
            'sender_role' => 'supervisor',
            'recipient_id' => $student->id,
            'recipient_role' => 'student',
            'body' => 'Good work this first week, Clarence. Please keep documenting your daily tasks and ask if any instruction is unclear.',
        ]);

        Notification::notify(
            (int) $student->id,
            'message',
            'New message from Industry Supervisor',
            'Adrian Reyes sent you a message about your first OJT week.',
            '/student/messages',
            ['message_id' => $message->id, 'internship_id' => $internship->id]
        );

        return $message;
    }

    public function tinyPng(): string
    {
        $im = imagecreatetruecolor(48, 32);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, 47, 31, $transparent);
        $ink = imagecolorallocate($im, 20, 90, 50);
        imageline($im, 4, 16, 44, 16, $ink);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return $png;
    }
}
