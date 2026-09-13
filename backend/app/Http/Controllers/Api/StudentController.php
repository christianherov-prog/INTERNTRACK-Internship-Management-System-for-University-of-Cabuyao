<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Company;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\HteRequest;
use App\Models\Internship;
use App\Models\InternshipApplication;
use App\Models\JournalEntry;
use App\Models\Notification;
use App\Services\AbsorptionService;
use App\Services\CertificateEligibilityService;
use App\Services\DocumentComplianceService;
use App\Services\DtrWorkflowService;
use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipProgressService;
use App\Services\JournalPeriodValidator;
use App\Services\ProgramRequirementService;
use App\Services\SupervisorFeedbackService;
use App\Support\ApiResponse;
use App\Support\InternshipProvisioning;
use App\Support\ManilaTime;
use App\Support\InternshipStatuses;
use App\Support\PlacementMoa;
use App\Support\UniqueWrite;
use App\Support\UploadLimits;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(private DtrWorkflowService $dtr) {}

    private function internship(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('studentProfile.program');

        $query = $user->internshipsAsStudent()
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);

        $requestedId = $request->header('X-Internship-Id') ?: $request->input('internship_id');
        if ($requestedId) {
            $internship = $query->where('id', $requestedId)->first();
        } else {
            $internship = InternshipProvisioning::openForStudent($user->id);
            if ($internship) {
                $internship->load(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);
            } else {
                $internship = $user->activeInternship()
                    ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile'])
                    ->first();
            }
        }

        if (! $internship) {
            $profile = $user->studentProfile;
            $ay = $profile?->school_year ?: '2025-2026';
            $sem = $profile?->semester ?: '2nd Semester';
            $facultyId = app(FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;
            $targetHours = ProgramRequirementService::targetHoursForProfile($profile);

            $internship = InternshipProvisioning::createPendingIfNone($user, [
                'status' => 'pending_placement',
                'school_year' => $ay,
                'semester' => $sem,
                'term' => "AY {$ay}, {$sem}",
                'program' => $profile?->program?->name,
                'faculty_id' => $facultyId,
                'target_hours' => $targetHours,
                'total_hours_rendered' => 0,
            ]);
            $internship->load(['student.studentProfile.program', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);
        }

        $with = [
            'student.studentProfile.program',
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
            'coordinator.facultyProfile',
        ];
        if (SchemaCache::hasTable('internship_placements')) {
            $with[] = 'placements';
            $with[] = 'currentPlacement';
        }

        return $internship->fresh($with);
    }

    /** Build the compact student summary used by the dashboard hero banner. */
    private function studentSummary($profile): ?array
    {
        if (! $profile) {
            return null;
        }

        $middleInitial = $profile->middle_name ? substr($profile->middle_name, 0, 1).'.' : null;
        $name = trim($profile->last_name.', '.implode(' ', array_filter([$profile->first_name, $middleInitial])));

        return [
            'name' => $name,
            'student_number' => $profile->student_number,
            'section' => $profile->section,
            'course_name' => $profile->program?->name,
            'year_level' => $profile->year_level,
        ];
    }

    /** GET /api/v1/student/dashboard */
    public function myInternships(Request $request)
    {
        $internships = $request->user()->internshipsAsStudent()
            ->with(['company', 'supervisor.supervisorProfile'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['internships' => $internships]);
    }

    public function startNewInternship(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('studentProfile.program');

        try {
            $internship = UniqueWrite::retry(fn () => DB::transaction(function () use ($user) {
                $open = InternshipProvisioning::openQuery($user->id)
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($open) {
                    throw new \RuntimeException('You still have an ongoing internship. Finish it first.');
                }

                $profile = $user->studentProfile;
                $ay = $profile?->school_year ?: '2025-2026';
                $sem = $profile?->semester ?: '2nd Semester';
                $facultyId = app(FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;
                $targetHours = ProgramRequirementService::targetHoursForProfile($profile);

                $created = $user->internshipsAsStudent()->create([
                    'status' => 'pending_placement',
                    'school_year' => $ay,
                    'semester' => $sem,
                    'term' => "AY {$ay}, {$sem}",
                    'program' => $profile?->program?->name,
                    'faculty_id' => $facultyId,
                    'target_hours' => $targetHours,
                    'total_hours_rendered' => 0,
                ]);

                InternshipProgressService::synchronize($created);

                return $created;
            }));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'New practicum deployment started.',
            'internship' => $internship,
        ]);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user()->load('studentProfile.program');
        $internship = $this->internship($request);
        $profile = $user->studentProfile;
        $progress = InternshipProgressService::snapshot($internship);
        $load = ['company'];
        if (SchemaCache::hasTable('internship_placements')) {
            $load = array_merge($load, ['placements.company', 'placements.supervisor.supervisorProfile', 'currentPlacement']);
        }
        $internship->load($load);

        // Attendance stats
        $daysPresent = $internship->attendance()->where('status', 'validated')->count();
        $hoursRendered = $progress['hours_rendered'];
        $targetHours = $progress['target_hours'];
        $progressPercent = $progress['progress_pct'];

        // Journal stats
        $journalCount = $internship->journals()->whereIn('status', ['submitted', 'approved'])->count();

        // Document compliance — same authoritative resolver as Faculty/Coordinator reports.
        $compliance = app(\App\Services\DocumentComplianceService::class)
            ->summaryForStudent($user, $internship);
        $docsTotal = $compliance['total'];
        $docsSubmitted = $compliance['approved'];
        $docCompliance = $compliance['pct'];
        $requiredDocTypes = collect($compliance['details'])->pluck('name')->values()->all();

        // Evaluation score (average of both evaluations, scaled to 100%)
        $evalAvg = $internship->evaluations()->avg('average_score');
        $evaluationScore = $evalAvg ? min(100.0, max(0.0, round($evalAvg * 20, 1))) : null;

        // Weekly chart — last 8 weeks (single query)
        $weekStart8 = now()->startOfWeek()->subWeeks(7);
        $weekEnd0 = now()->endOfWeek();

        $weeklyData = $internship->attendance()
            ->where('status', 'validated')
            ->whereBetween('date', [$weekStart8->toDateString(), $weekEnd0->toDateString()])
            ->selectRaw('YEARWEEK(date, 1) as yw, SUM(hours_rendered) as total')
            ->groupBy('yw')
            ->pluck('total', 'yw');

        $labels = [];
        $hours = [];
        for ($i = 7; $i >= 0; $i--) {
            $ws = now()->startOfWeek()->subWeeks($i);
            $yw = $ws->format('oW'); // ISO year + week number, matching YEARWEEK(..., 1)
            $labels[] = 'Wk '.(8 - $i);
            $hours[] = round((float) ($weeklyData[$yw] ?? 0), 1);
        }

        // Announcements for student role
        $announcements = Announcement::where(function ($q) {
            $q->where('target_role', 'all')->orWhere('target_role', 'student');
        })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Announcement $a) => $a->toClientArray())
            ->values();

        return response()->json([
            'student' => $this->studentSummary($user->studentProfile),
            'stats' => [
                'hours_rendered' => $hoursRendered,
                'target_hours' => $targetHours,
                'days_present' => $daysPresent,
                'journal_count' => $journalCount,
                'docs_submitted' => $docsSubmitted,
                'docs_approved' => $compliance['approved'],
                'docs_total' => $docsTotal,
                'progress_percent' => $progressPercent,
                'doc_compliance' => $docCompliance,
                'evaluation_score' => $evaluationScore,
                'status' => InternshipStatuses::normalize($internship->status),
            ],
            'weekly_chart' => ['labels' => $labels, 'hours' => $hours],
            'announcements' => $announcements,
            'incomplete_dtr_days' => $internship->supervisor_id ? $this->dtr->incompleteDays($internship) : [],
            'internship' => [
                'id' => $internship->id,
                'term' => $internship->term,
                'status' => InternshipStatuses::normalize($internship->status),
                'status_label' => InternshipStatuses::label($internship->status),
                'status_reason' => $internship->status_reason,
                'company_name' => $internship->company?->company_name ?? '—',
                'supervisor_name' => $internship->supervisor?->supervisorProfile?->full_name
                    ?: ($internship->supervisor?->username),
                'supervisor_faculty_number' => $internship->supervisor?->faculty_number,
                'start_date' => $internship->start_date?->toDateString(),
                'placements' => SchemaCache::hasTable('internship_placements')
                    ? $internship->placements->map(fn ($p) => [
                        'id' => $p->id,
                        'sequence_order' => $p->sequence_order,
                        'label' => $p->label,
                        'required_hours' => (float) $p->required_hours,
                        'accumulated_hours' => (float) $p->accumulated_hours,
                        'progress_percent' => $p->progress_percent,
                        'status' => $p->status,
                        'company_name' => $p->company?->company_name ?? null,
                        'supervisor_name' => $p->supervisor?->supervisorProfile?->full_name
                            ?: ($p->supervisor?->username),
                        'is_current' => $p->id === $internship->current_placement_id,
                    ])
                    : [],
                'current_placement' => $internship->currentPlacement ? [
                    'id' => $internship->currentPlacement->id,
                    'label' => $internship->currentPlacement->label,
                    'required_hours' => (float) $internship->currentPlacement->required_hours,
                    'accumulated_hours' => (float) $internship->currentPlacement->accumulated_hours,
                    'progress_percent' => $internship->currentPlacement->progress_percent,
                ] : null,
            ],
        ]);
    }

    /** GET /api/v1/student/attendance */
    public function attendance(Request $request)
    {
        $internship = $this->internship($request);
        if ($reason = $internship->attendanceLockReason()) {
            return response()->json(['message' => $reason], 403);
        }
        $logs = $internship->attendance()
            ->with('placement')
            ->orderByDesc('date')
            ->paginate(20);

        $this->dtr->decorateLogs(collect($logs->items()));

        $manilaToday = $this->dtr->manilaToday();
        $todayState = $this->dtr->todayState($internship, $manilaToday);
        $todayRecord = $todayState['today_record'];
        $canUndo = $todayRecord ? $this->dtr->canUndoClockOut($todayRecord) : false;
        $undoExpires = null;
        if ($todayRecord?->clock_out) {
            $undoExpires = $this->dtr
                ->combineDateAndTime($todayRecord->date, $todayRecord->clock_out)
                ->addMinutes(DtrWorkflowService::GRACE_MINUTES)
                ->toIso8601String();
        }

        $overtimePrompt = $todayRecord ? $this->dtr->overtimePromptFor($todayRecord->loadMissing('internship')) : null;
        $manilaNow = ManilaTime::now();

        return response()->json([
            'attendance' => $logs,
            'today_record' => $todayRecord,
            'today_status' => $todayState['today_status'],
            'today_date' => $manilaToday,
            'can_undo_clock_out' => $canUndo,
            'undo_expires_at' => $canUndo ? $undoExpires : null,
            'overtime_prompt' => $overtimePrompt,
            'active_schedule' => $this->dtr->serializeSchedule($this->dtr->activeScheduleFor($internship, $manilaToday)),
            'pending_schedule' => $this->dtr->serializeSchedule($this->dtr->pendingScheduleFor($internship)),
            'schedule_history' => $this->dtr->scheduleHistory($internship)->map(fn ($s) => $this->dtr->serializeSchedule($s))->values(),
            'incomplete_dtr_days' => $this->dtr->incompleteDays($internship),
            'server_now' => $manilaNow->toIso8601String(),
            'server_now_display' => ManilaTime::clockDisplay($manilaNow),
            'server_timezone' => ManilaTime::TZ,
        ]);
    }

    /** POST /api/v1/student/attendance/clock-in */
    public function clockIn(Request $request)
    {
        $internship = $this->internship($request);
        if ($reason = $internship->attendanceLockReason()) {
            return response()->json(['message' => $reason], 403);
        }

        try {
            $log = UniqueWrite::retry(fn () => DB::transaction(function () use ($request, $internship) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $log = $this->dtr->clockIn($internship, $request->location ?? null);
                audit_log($request->user()->id, 'clock_in', ['date' => $this->dtr->manilaToday()]);

                return $log;
            }));
        } catch (QueryException $e) {
            if (UniqueWrite::isDuplicate($e)) {
                return response()->json(['message' => 'You have already clocked in today.'], 422);
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Clocked in successfully.', 'record' => $log], 201);
    }

    /** POST /api/v1/student/attendance/clock-out */
    public function clockOut(Request $request)
    {
        $request->validate([
            'action' => 'nullable|in:end_day',
            'location' => 'nullable|string|max:255',
        ]);

        $internship = $this->internship($request);
        if ($reason = $internship->attendanceLockReason()) {
            return response()->json(['message' => $reason], 403);
        }
        $today = $this->dtr->manilaToday();
        try {
            $result = UniqueWrite::retry(fn () => DB::transaction(function () use ($internship, $today, $request) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $open = $this->dtr->openSessionFor($internship, $today);
                if (! $open || ! $open->clock_in) {
                    throw new \RuntimeException('No open clock-in was found for today.');
                }
                if ($open->clock_out) {
                    throw new \RuntimeException('You have already clocked out today.');
                }

                $open->setRelation('internship', $internship);

                return $this->dtr->finalizeClockOut($open, now(), $request->location ?? null);
            }));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        audit_log($request->user()->id, 'clock_out', [
            'date' => $today,
            'hours' => $result['record']->hours_rendered,
            'overtime_detected' => $result['overtime_detected'],
            'action' => $request->input('action', 'end_day'),
        ]);

        return response()->json([
            'message' => 'Clocked out successfully.',
            'record' => $result['record'],
            'overtime_detected' => $result['overtime_detected'],
            'excess_minutes' => $result['excess_minutes'],
            'undo_expires_at' => $result['undo_expires_at'],
            'can_undo_clock_out' => $result['can_undo_clock_out'],
            'clocked_out_at' => $result['record']->clock_out,
            'server_now_display' => ManilaTime::clockDisplay(),
            'server_timezone' => ManilaTime::TZ,
        ]);
    }

    /** POST /api/v1/student/attendance/break-start */
    public function breakStart(Request $request)
    {
        $internship = $this->internship($request);
        if ($reason = $internship->attendanceLockReason()) {
            return response()->json(['message' => $reason], 403);
        }
        $today = $this->dtr->manilaToday();

        try {
            $log = UniqueWrite::retry(fn () => DB::transaction(function () use ($internship, $today) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $open = $this->dtr->openSessionFor($internship, $today);
                if (! $open || ! $open->clock_in) {
                    throw new \RuntimeException('No open clock-in was found for today.');
                }
                if ($open->clock_out) {
                    throw new \RuntimeException('You have already clocked out today.');
                }

                return $this->dtr->startBreak($open);
            }));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Could not start break.',
                'errors' => $e->errors(),
            ], 422);
        }

        audit_log($request->user()->id, 'break_start', ['date' => $today, 'log_id' => $log->id]);

        return response()->json([
            'message' => 'Break started. Resume when you return.',
            'record' => $log,
        ]);
    }

    /** POST /api/v1/student/attendance/break-end */
    public function breakEnd(Request $request)
    {
        $internship = $this->internship($request);
        if ($reason = $internship->attendanceLockReason()) {
            return response()->json(['message' => $reason], 403);
        }
        $today = $this->dtr->manilaToday();

        try {
            $log = UniqueWrite::retry(fn () => DB::transaction(function () use ($internship, $today) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $open = $this->dtr->openSessionFor($internship, $today);
                if (! $open || ! $open->clock_in) {
                    throw new \RuntimeException('No open clock-in was found for today.');
                }
                if ($open->clock_out) {
                    throw new \RuntimeException('You have already clocked out today.');
                }
                if (! $open->on_break) {
                    throw new \RuntimeException('You are not currently on break.');
                }

                return $this->dtr->endBreak($open);
            }));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Could not end break.',
                'errors' => $e->errors(),
            ], 422);
        }

        audit_log($request->user()->id, 'break_end', ['date' => $today, 'log_id' => $log->id]);

        return response()->json([
            'message' => 'Break ended. Attendance resumed.',
            'record' => $log,
        ]);
    }

    /** GET /api/v1/student/logbook */
    public function logbook(Request $request)
    {
        $internship = $this->internship($request);
        $internship->loadMissing('company', 'student.studentProfile.program');
        $journals = $internship->journals()->academic()->orderByDesc('week_number')->paginate(20);
        $student = $request->user()->loadMissing('studentProfile.program');
        $profile = $student->studentProfile;
        $companyLogoPath = app(\App\Services\PortfolioDataService::class)->companyLogoPath($internship);
        $studentSignaturePath = \App\Support\SignatureCapture::profilePath($student);
        $period = app(JournalPeriodValidator::class)->bounds($internship);
        $journals->getCollection()->transform(function (JournalEntry $journal) use ($profile, $student, $internship, $companyLogoPath, $studentSignaturePath) {
            $journal->setAttribute('date', $journal->date?->toDateString());
            $journal->setAttribute('end_date', $journal->end_date?->toDateString());
            $journal->setAttribute('editable', ! in_array($journal->status, ['approved'], true));
            $journal->setAttribute('lock_reason', $journal->status === 'approved'
                ? 'Approved journals cannot be edited.'
                : null);
            $journal->setAttribute('student_name', $profile
                ? trim(($profile->last_name ?? '').', '.($profile->first_name ?? ''))
                : $student->username);
            $journal->setAttribute('program', $profile?->program?->name ?: $internship->program);
            $journal->setAttribute('company_name', $internship->company?->company_name);
            $journal->setAttribute('internship_id', $internship->id);
            $journal->setAttribute('company_logo_path', $companyLogoPath);
            $journal->setAttribute('student_signature_path', $studentSignaturePath);

            return $journal;
        });

        $payload = ApiResponse::list($journals)->getData(true);
        $payload['intern_feedback'] = app(SupervisorFeedbackService::class)->serialize(
            app(SupervisorFeedbackService::class)->noteForInternship($internship)
        );
        $payload['journal_period'] = $period;

        return response()->json($payload);
    }

    /** GET /api/v1/student/supervisor-feedback */
    public function supervisorFeedback(Request $request)
    {
        $internship = $this->internship($request);
        $internship->loadMissing('student.studentProfile', 'company', 'supervisor.supervisorProfile');
        $service = app(SupervisorFeedbackService::class);
        $note = $service->noteForInternship($internship);

        return response()->json([
            'data' => $note ? [$service->serialize($note)] : [],
            'intern_feedback' => $service->serialize($note),
        ]);
    }

    /** POST /api/v1/student/logbook */
    public function submitJournal(Request $request)
    {
        $request->validate([
            'journal_id' => 'nullable|integer',
            'week_number' => 'nullable|integer|min:1',
            'date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:date',
            'activities_summary' => 'required_without_all:challenges,learnings|string|nullable',
            'challenges' => 'required_without_all:activities_summary,learnings|string|nullable',
            'learnings' => 'required_without_all:activities_summary,challenges|string|nullable',
            'notes' => 'nullable|string',
        ]);

        $internship = $this->internship($request);
        $validator = app(JournalPeriodValidator::class);

        $existingWeek = null;
        if ($request->filled('journal_id')) {
            $existingWeek = $validator->findEditableJournal(
                $internship,
                (int) $request->journal_id,
                null
            );
            if (! $existingWeek) {
                return response()->json(['message' => 'Journal entry not found for this internship.'], 404);
            }
        }

        try {
            $resolved = $validator->validateAndResolveWeek(
                $internship,
                $request->date,
                $request->end_date,
                $existingWeek?->id
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?: 'Invalid journal period.',
                'errors' => $e->errors(),
            ], 422);
        }

        $weekNumber = $resolved['week_number'];
        if (! $existingWeek && ! empty($resolved['upsert_journal_id'])) {
            $existingWeek = $internship->journals()->academic()->whereKey($resolved['upsert_journal_id'])->first();
        }

        try {
            $journal = UniqueWrite::retry(fn () => DB::transaction(function () use ($request, $internship, $existingWeek, $weekNumber, $resolved) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();

                $journal = $existingWeek;
                if (! $journal) {
                    $journal = $internship->journals()
                        ->withTrashed()
                        ->where('week_number', $weekNumber)
                        ->first();
                }

                if ($journal?->trashed()) {
                    // Soft-deleted approved rows must not block recreating the same chronological week.
                    if ($journal->status === 'approved') {
                        $journal->forceDelete();
                        $journal = null;
                    } else {
                        $journal->restore();
                    }
                }

                if ($journal && $journal->status === 'approved') {
                    throw new \RuntimeException('Approved journals cannot be edited.');
                }

                // Re-check week-slot uniqueness inside the lock when moving an existing row.
                if ($journal && (int) $journal->week_number !== (int) $weekNumber) {
                    $slotTaken = $internship->journals()
                        ->academic()
                        ->where('week_number', $weekNumber)
                        ->where('id', '!=', $journal->id)
                        ->exists();
                    if ($slotTaken) {
                        throw new \RuntimeException('A journal already exists for internship week '.$weekNumber.'. Edit that entry instead.');
                    }
                }

                $data = [
                    'entry_number' => $weekNumber,
                    'week_number' => $weekNumber,
                    'date' => $resolved['start'],
                    'end_date' => $resolved['end'],
                    'activities_summary' => $request->activities_summary,
                    'challenges' => $request->challenges,
                    'learnings' => $request->learnings,
                    'notes' => $request->notes,
                    'status' => 'submitted',
                ];

                if ($journal) {
                    $data['faculty_reviewed_at'] = null;
                    $data['faculty_reviewed_by'] = null;
                    $data['score'] = null;
                    $journal->update($data);
                } else {
                    $journal = $internship->journals()->create($data);
                }

                audit_log($request->user()->id, 'submit_journal', ['week_number' => $weekNumber]);

                return $journal->fresh();
            }));
        } catch (QueryException $e) {
            if (UniqueWrite::isDuplicate($e)) {
                $journal = $internship->journals()->where('week_number', $weekNumber)->first();

                return response()->json(['message' => 'Weekly journal submitted successfully.', 'journal' => $journal], 201);
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($internship->faculty_id) {
            Notification::notify(
                (int) $internship->faculty_id,
                'journal_submitted',
                'Weekly journal submitted',
                'An assigned intern submitted Week '.($journal->week_number ?? $journal->entry_number ?? '—').' for faculty review.',
                '/faculty/journals',
                ['journal_id' => $journal->id, 'internship_id' => $internship->id]
            );
        }

        return response()->json(['message' => 'Weekly journal submitted successfully.', 'journal' => $journal], 201);
    }

    /** GET /api/v1/student/documents */
    public function documents(Request $request)
    {
        $internship = $this->internship($request);
        $internship->loadMissing('faculty.facultyProfile', 'coordinator.facultyProfile');
        $user = $request->user();
        $compliance = app(DocumentComplianceService::class);

        $templates = $compliance->applicableTemplatesForStudent($user)
            ->load(['creator.facultyProfile', 'creator.supervisorProfile', 'creator.studentProfile', 'attachments']);

        $submitted = $internship->documents()->with('attachments')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $evaluation = $compliance->evaluateStudent($user, $internship);
        $detailByTemplate = collect($evaluation['details'])->keyBy('template_id');

        $docs = $templates->map(function ($template) use ($submitted, $internship, $compliance, $detailByTemplate) {
            $type = $template->name;
            $detail = $detailByTemplate->get($template->id) ?? [];
            $state = $compliance->resolveTemplateState($template, $internship, $submitted);
            $doc = $compliance->bestMatchingDocument($template, $submitted);

            $canonical = $detail['status'] ?? $state['status'] ?? 'missing';
            $source = $detail['source'] ?? $state['source'] ?? 'upload';

            $status = match ($canonical) {
                'approved' => $source === 'upload' ? 'approved' : 'completed',
                'pending' => 'pending',
                'rejected' => 'rejected',
                default => 'not_submitted',
            };

            $isMissed = false;
            if ($template->deadline && now()->greaterThan($template->deadline)) {
                if (in_array($status, ['not_submitted', 'rejected'], true)) {
                    $status = 'no_submission';
                    $isMissed = true;
                }
            }

            $creator = $template->creator;
            $reviewStage = $creator?->role === 'faculty' ? 'faculty' : 'coordinator';
            $responsible = $reviewStage === 'faculty'
                ? ($internship->faculty ?: $creator)
                : ($creator ?: $internship->coordinator);
            $senderName = $responsible?->profile_name ?: ($creator?->profile_name ?: 'System');
            $senderRole = $responsible ? ucfirst($responsible->role) : ($creator ? ucfirst($creator->role) : 'Admin');

            return [
                'template_id' => $template->id,
                'system_code' => $template->system_code,
                'source' => $source,
                'has_template' => $template->attachments->isNotEmpty(),
                'template_attachments' => $template->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_name' => $a->file_name,
                        'file_path' => $a->file_path,
                        'file_url' => url('/api/v1/files/download?path='.urlencode($a->file_path)),
                    ];
                })->toArray(),
                'template_link' => $template->drive_link,
                'description' => $template->description,
                'document_type' => $type,
                'sender' => [
                    'name' => $senderName,
                    'role' => $senderRole,
                ],
                'status' => $status,
                'canonical_status' => $canonical,
                'deadline' => $template->deadline?->toIso8601String(),
                'is_missed' => $isMissed,
                'submitted_at' => $doc?->submitted_at?->toIso8601String() ?? null,
                'remarks' => $doc?->remarks ?? null,
                'id' => $doc?->id ?? null,
                'attachments' => $doc ? $doc->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_name' => $a->file_name,
                        'file_path' => $a->file_path,
                        'file_url' => url('/api/v1/files/download?path='.urlencode($a->file_path)),
                    ];
                })->toArray() : [],
                'drive_link' => $doc?->drive_link ?? null,
                'uploadable' => $source === 'upload' || $canonical === 'missing',
            ];
        });

        $docsTotal = $evaluation['required_count'];
        $requiredTypes = $evaluation['required_types'];

        $payload = ApiResponse::list($docs)->getData(true);
        $payload['meta']['docs_total'] = $docsTotal;
        $payload['meta']['docs_approved'] = $evaluation['satisfied_count'];
        $payload['meta']['docs_pending'] = $evaluation['pending_count'];
        $payload['meta']['docs_missing'] = $evaluation['missing_count'];
        $payload['meta']['docs_rejected'] = $evaluation['rejected_count'];
        $payload['meta']['compliance_pct'] = $evaluation['compliance_pct'];
        $payload['meta']['all_requirements_approved'] = $docsTotal > 0
            && $evaluation['satisfied_count'] === $docsTotal;
        $payload['meta']['internship_id'] = $internship->id;
        $payload['required_types'] = $requiredTypes;

        return response()->json($payload);
    }

    /** POST /api/v1/student/documents/upload */
    public function uploadDocument(Request $request)
    {
        $user = $request->user();
        $compliance = app(DocumentComplianceService::class);

        $templates = $compliance->applicableTemplatesForStudent($user)->load('creator');
        $templatesByName = $templates->keyBy('name');

        $validTypes = $templates->pluck('name')->values()->all();

        $maxFiles = UploadLimits::maxFiles();
        $request->validate([
            'document_type' => ['required', 'string', Rule::in($validTypes)],
            'files' => "nullable|array|max:{$maxFiles}",
            'files.*' => 'nullable|'.UploadLimits::fileRule('pdf,jpg,jpeg,png,doc,docx'),
            'drive_link' => 'nullable|url|max:2048',
        ], UploadLimits::maxMessages('files'));

        if (! $request->hasFile('files') && empty($request->drive_link)) {
            return response()->json(['message' => 'Please provide either a file or a Google Drive link.'], 422);
        }

        $template = $templatesByName->get($request->document_type);

        if ($template && $template->deadline && now()->greaterThan($template->deadline)) {
            return response()->json(['message' => 'The deadline for this requirement has expired.'], 403);
        }

        $internship = $this->internship($request);
        $reviewStage = $template?->creator?->role === 'faculty' ? 'faculty' : 'coordinator';
        $documentType = $template?->name ?: $request->document_type;

        try {
            $doc = UniqueWrite::retry(fn () => DB::transaction(function () use ($request, $internship, $reviewStage, $template, $documentType, $compliance) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $all = $internship->documents()->withTrashed()->get();
                $existing = $template
                    ? $compliance->bestMatchingDocument($template, $all)
                    : $all->first(fn ($d) => $d->document_type === $documentType);

                if ($existing?->trashed()) {
                    $existing->restore();
                }

                $payload = [
                    'document_type' => $documentType,
                    'status' => 'pending',
                    'current_stage' => $reviewStage,
                    'submitted_at' => now(),
                    'remarks' => null,
                ];
                if ($request->has('drive_link')) {
                    $payload['drive_link'] = $request->drive_link;
                }

                if ($existing) {
                    $existing->update($payload);

                    return $existing->fresh();
                }

                return $internship->documents()->create(array_merge($payload, [
                    'drive_link' => $request->drive_link,
                ]));
            }));
        } catch (QueryException $e) {
            if (! UniqueWrite::isDuplicate($e)) {
                throw $e;
            }
            $all = $internship->documents()->get();
            $doc = ($template
                ? $compliance->bestMatchingDocument($template, $all)
                : $internship->documents()->where('document_type', $documentType)->first())
                ?? $internship->documents()->where('document_type', $documentType)->firstOrFail();
            $doc->update([
                'document_type' => $documentType,
                'status' => 'pending',
                'current_stage' => $reviewStage,
                'submitted_at' => now(),
                'remarks' => null,
                'drive_link' => $request->has('drive_link') ? $request->drive_link : $doc->drive_link,
            ]);
        }

        if ($request->hasFile('files')) {
            $doc->load('attachments');
            foreach ($doc->attachments as $old) {
                if ($old->file_path) {
                    Storage::disk('local')->delete($old->file_path);
                }
                $old->delete();
            }

            foreach ($request->file('files') as $file) {
                $doc->attachments()->create([
                    'file_path' => $file->store("internships/{$internship->id}/documents", 'local'),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        audit_log($request->user()->id, 'upload_document', ['type' => $request->document_type]);

        if ($template?->created_by && (int) $template->created_by !== (int) $user->id) {
            $creator = $template->creator;
            $reviewPath = $creator?->isCoordinator()
                ? '/coordinator/requirements'
                : '/faculty/requirements';
            $studentName = $user->profile_name ?: ($user->student_number ?? 'A student');

            Notification::notify(
                (int) $template->created_by,
                'document_pending_faculty',
                'New document submitted',
                "{$studentName} submitted \"{$request->document_type}\".",
                $reviewPath,
                [
                    'document_id' => $doc->id,
                    'document_type' => $request->document_type,
                    'student_id' => $user->id,
                ]
            );
        }

        return response()->json(['message' => 'Document uploaded successfully.', 'document' => $doc->load('attachments')], 201);
    }

    /** GET /api/v1/student/evaluations */
    public function evaluations(Request $request)
    {
        $internship = $this->internship($request);
        $evaluations = $internship->evaluations()->with('evaluator')->get();

        $payload = ApiResponse::list($evaluations)->getData(true);
        $payload['evaluation_period_status'] = $internship->evaluation_period_status ?: 'pending';
        $payload['evaluation_period_approved'] = $internship->evaluationPeriodIsApproved();

        return response()->json($payload);
    }

    /** POST /api/v1/student/evaluations */
    public function submitEvaluation(Request $request)
    {
        $request->validate([
            'evaluation_period' => 'required|string',
            'form_type' => 'required|in:FO-22,FO-23',
            'responses' => 'required|array',
            'general_comments' => 'nullable|string',
        ]);

        $internship = $this->internship($request);
        $internship->abortUnlessEvaluationPeriodApproved();
        $period = $request->input('evaluation_period');

        $eval = Evaluation::updateOrCreate(
            [
                'internship_id' => $internship->id,
                'evaluator_type' => 'student',
                'evaluation_period' => $period,
                'form_type' => $request->input('form_type'),
            ],
            [
                'responses' => $request->input('responses'),
                'general_comments' => $request->input('general_comments'),
                'evaluated_by' => $request->user()->id,
                'submitted_at' => now(),
            ]
        );
        $eval->computeScores();
        $eval->save();

        audit_log($request->user()->id, 'submit_evaluation_student', [
            'internship_id' => $internship->id,
            'form_type' => $request->input('form_type'),
        ]);

        return response()->json(['message' => 'Evaluation submitted successfully.', 'evaluation' => $eval], 201);
    }

    /** GET /api/v1/student/records */
    public function records(Request $request)
    {
        $user = $request->user()->load('studentProfile.program');
        $feedbackService = app(SupervisorFeedbackService::class);
        $history = $user->internshipsAsStudent()->with([
            'company',
            'supervisor.supervisorProfile',
            'faculty.facultyProfile',
            'student.studentProfile.program',
            'journals' => fn ($q) => $q->where('status', SupervisorFeedbackService::NOTE_STATUS)->whereNotNull('supervisor_feedback'),
        ])->orderBy('school_year', 'desc')
            ->withCount(['attendance as validated_days' => fn ($q) => $q->where('status', 'validated')])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($internship) use ($feedbackService) {
                $snapshot = InternshipProgressService::snapshot($internship);
                $internship->total_hours_rendered = $snapshot['hours_rendered'];
                $internship->target_hours = $snapshot['target_hours'];
                $internship->setAttribute('hours_rendered', $snapshot['hours_rendered']);
                $internship->setAttribute('progress_pct', $snapshot['progress_pct']);
                $internship->setAttribute('remaining_hours', $snapshot['remaining_hours']);
                $internship->setAttribute('hte_count', $snapshot['hte_count']);
                $internship->setAttribute('hte_completed', $snapshot['hte_completed']);
                $internship->setAttribute('company_name', $snapshot['company_name']);
                $internship->setAttribute('status_label', $snapshot['status_label']);
                $note = $internship->journals->first();
                $internship->setAttribute('supervisor_feedback', $feedbackService->serialize($note));

                return $internship;
            });

        return response()->json([
            'profile' => $user->studentProfile,
        ] + ApiResponse::list($history)->getData(true));
    }

    /**
     * POST /api/v1/student/absorption/declare
     * Optional: student reports they were hired — stays pending until the PALD Director finalizes.
     */
    public function declareAbsorption(Request $request)
    {
        $request->validate([
            'internship_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $query = $request->user()->internshipsAsStudent()->where('status', 'completed');
        if ($request->internship_id) {
            $query->where('id', $request->internship_id);
        }
        $internship = $query->latest('id')->firstOrFail();

        $updated = AbsorptionService::studentDeclare($internship, $request->notes);

        audit_log($request->user()->id, 'student_declare_hired', ['internship_id' => $internship->id]);

        return response()->json([
            'message' => 'Your hire declaration was submitted. It stays pending until the PALD Director finalizes Absorbed / Not Hired.',
            'internship' => $updated,
        ]);
    }

    /**
     * GET /api/v1/student/certificate/eligibility
     * Returns whether the student is eligible for an OJT Completion Certificate,
     * along with a checklist of individual requirements.
     */
    public function certificateEligibility(Request $request)
    {
        $internship = $this->internship($request);
        $internship->load(['documents', 'evaluations']);

        $eligible = CertificateEligibilityService::isEligible($internship);
        $checklist = CertificateEligibilityService::checklist($internship);

        // If newly eligible and never issued, mark the flag
        if ($eligible && ! $internship->certificate_eligible) {
            $internship->update(['certificate_eligible' => true]);
        }

        return response()->json([
            'eligible' => $eligible,
            'issued_at' => $internship->certificate_issued_at,
            'checklist' => $checklist,
        ]);
    }

    public function companies()
    {
        $companies = Company::where('moa_status', 'active')->get();

        return response()->json(['companies' => $companies]);
    }

    public function applications(Request $request)
    {
        $applications = InternshipApplication::query()
            ->where('student_id', $request->user()->id)
            ->with('company')
            ->latest()
            ->get()
            ->map(fn (InternshipApplication $application) => $this->serializeApplication($application));

        if ($applications->isEmpty()) {
            $applications = $request->user()->internshipsAsStudent()
                ->whereNotNull('company_id')
                ->with('company')
                ->get()
                ->map(fn ($internship) => [
                    'id' => $internship->id,
                    'status' => $internship->status,
                    'company_id' => $internship->company_id,
                    'company' => $internship->company,
                    'company_name' => $internship->company?->company_name,
                    'coordinator_remarks' => null,
                    'has_moa' => false,
                    'moa_path' => null,
                    'created_at' => $internship->created_at?->toDateTimeString(),
                ]);
        }

        return response()->json(['applications' => $applications]);
    }

    public function applyCompany(Request $request)
    {
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'moa' => PlacementMoa::rule(),
        ]);
        $internship = $this->internship($request);

        $existing = InternshipApplication::where('student_id', $request->user()->id)
            ->where('company_id', $data['company_id'])
            ->first();

        if ($existing && $existing->status === 'approved') {
            return response()->json([
                'message' => 'This application was already approved.',
                'application' => $this->serializeApplication($existing->load('company')),
            ]);
        }

        if ($existing && $existing->status === 'pending' && ! $request->hasFile('moa')) {
            return response()->json([
                'message' => 'Application submitted. Please await coordinator approval.',
                'application' => $this->serializeApplication($existing->load('company')),
            ]);
        }

        try {
            $application = DB::transaction(function () use ($request, $internship, $data, $existing) {
                Internship::whereKey($internship->id)->lockForUpdate()->firstOrFail();
                $internship->update(['company_id' => $data['company_id']]);

                $payload = [
                    'student_id' => $request->user()->id,
                    'company_id' => $data['company_id'],
                    'status' => 'pending',
                    'coordinator_remarks' => null,
                ];

                if ($request->hasFile('moa')) {
                    if ($existing?->moa_path) {
                        PlacementMoa::delete($existing->moa_path);
                    }
                    $payload['moa_path'] = PlacementMoa::store(
                        $request->file('moa'),
                        'applications',
                        $request->user()->id
                    );
                    $payload['moa_original_name'] = $request->file('moa')->getClientOriginalName();
                }

                $application = InternshipApplication::updateOrCreate(
                    [
                        'student_id' => $request->user()->id,
                        'company_id' => $data['company_id'],
                    ],
                    $payload
                )->load('company');

                return $application;
            });
        } catch (QueryException $e) {
            if (! UniqueWrite::isDuplicate($e)) {
                throw $e;
            }
            $application = InternshipApplication::where('student_id', $request->user()->id)
                ->where('company_id', $data['company_id'])
                ->firstOrFail()
                ->load('company');
        }

        $student = $request->user()->loadMissing('studentProfile');
        PlacementMoa::notifyDepartmentCoordinators(
            $student,
            'placement_application',
            'New company application',
            ($student->profile_name ?: $student->username).' applied to '.$application->company?->company_name.'.',
            '/coordinator/internship-management'
        );

        return response()->json([
            'message' => 'Application submitted. Please await coordinator approval.',
            'application' => $this->serializeApplication($application),
        ]);
    }

    public function hteRequests(Request $request)
    {
        $requests = HteRequest::where('student_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(fn ($req) => $this->serializeHteRequest($req));

        return response()->json(['requests' => $requests]);
    }

    public function submitHteRequest(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'organization_type' => \App\Support\OrganizationTypes::validationRule(false),
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_number' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s()]{7,50}$/'],
            'remarks' => 'nullable|string|max:2000',
            'moa' => PlacementMoa::rule(),
        ]);

        $resolvedType = \App\Support\OrganizationTypes::resolveForStorage($data['organization_type'] ?? null);
        if (! $resolvedType['ok']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'organization_type' => [$resolvedType['message']],
            ]);
        }
        $data['organization_type'] = $resolvedType['value'];

        $accredited = Company::query()
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($data['company_name'])])
            ->exists();

        if ($accredited) {
            return response()->json([
                'message' => 'This HTE already exists in the accredited company list.',
            ], 422);
        }

        $duplicate = HteRequest::where('student_id', $request->user()->id)
            ->where('status', 'pending')
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($data['company_name'])])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'You already have a pending HTE request for this company.',
            ], 422);
        }

        $data['student_id'] = $request->user()->id;
        $data['status'] = 'pending';
        $data['coordinator_remarks'] = null;

        if ($request->hasFile('moa')) {
            $data['moa_path'] = PlacementMoa::store(
                $request->file('moa'),
                'hte',
                $request->user()->id
            );
            $data['moa_original_name'] = $request->file('moa')->getClientOriginalName();
        }

        $req = HteRequest::create($data);

        $student = $request->user()->loadMissing('studentProfile');
        PlacementMoa::notifyDepartmentCoordinators(
            $student,
            'hte_request',
            'New HTE request',
            ($student->profile_name ?: $student->username).' requested a new HTE: '.$req->company_name.'.',
            '/coordinator/internship-management'
        );

        return response()->json(['message' => 'HTE Request submitted.', 'request' => $this->serializeHteRequest($req)]);
    }

    private function serializeApplication(InternshipApplication $application): array
    {
        return [
            'id' => $application->id,
            'status' => $application->status,
            'company_id' => $application->company_id,
            'company' => $application->company,
            'company_name' => $application->company?->company_name,
            'coordinator_remarks' => $application->coordinator_remarks,
            'has_moa' => (bool) $application->moa_path,
            'moa_path' => $application->moa_path,
            'moa_original_name' => $application->moa_original_name,
            'created_at' => $application->created_at?->toDateTimeString(),
        ];
    }

    private function serializeHteRequest(HteRequest $req): array
    {
        return [
            'id' => $req->id,
            'company_name' => $req->company_name,
            'address' => $req->address,
            'organization_type' => $req->organization_type,
            'organization_type_label' => $req->organization_type_label,
            'contact_person' => $req->contact_person,
            'contact_email' => $req->contact_email,
            'contact_number' => $req->contact_number,
            'status' => $req->status,
            'remarks' => $req->remarks,
            'coordinator_remarks' => $req->coordinator_remarks,
            'has_moa' => (bool) $req->moa_path,
            'moa_path' => $req->moa_path,
            'moa_original_name' => $req->moa_original_name,
            'created_at' => $req->created_at?->toDateTimeString(),
        ];
    }
}
