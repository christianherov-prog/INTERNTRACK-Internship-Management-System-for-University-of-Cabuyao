<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use App\Models\Document;
use App\Models\Announcement;
use App\Models\Notification;
use App\Services\AbsorptionService;
use App\Services\CertificateEligibilityService;
use App\Support\ApiResponse;
use App\Support\RequiredDocuments;
use App\Support\RequirementAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private function internship(Request $request)
    {
        $user = $request->user();
        
        $query = $user->internshipsAsStudent()
            ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);
            
        $requestedId = $request->header('X-Internship-Id') ?: $request->input('internship_id');
        if ($requestedId) {
            $internship = $query->where('id', $requestedId)->first();
        } else {
            // Default to the latest active/current internship
            $internship = $user->activeInternship()
                ->with(['student.studentProfile', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile'])
                ->first();
        }

        if (!$internship) {
            $profile = $user->studentProfile;
            $ay = $profile?->school_year ?: '2025-2026';
            $sem = $profile?->semester ?: '2nd Semester';
            $facultyId = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;
            $targetHours = 500;
            if (in_array($profile?->program?->name, ['Bachelor of Secondary Education', 'Bachelor of Elementary Education'])) {
                $targetHours = 360;
            }

            $internship = $user->internshipsAsStudent()->create([
                'status' => 'pending_placement',
                'school_year' => $ay,
                'semester' => $sem,
                'term' => "AY {$ay}, {$sem}",
                'program' => $profile?->program?->name,
                'faculty_id' => $facultyId,
                'target_hours' => $targetHours,
                'total_hours_rendered' => 0
            ]);
            if (!$internship->relationLoaded('student')) {
                $internship->load(['student.studentProfile.program', 'company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);
            }
        } else {
            $dirty = false;
            if (empty($internship->program)) {
                $profile = $user->studentProfile;
                $program = $profile?->program?->name;
                if ($program) {
                    $internship->program = $program;
                    $dirty = true;
                }
            }
            if (empty($internship->faculty_id)) {
                $facultyId = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($user->studentProfile)?->id;
                if ($facultyId) {
                    $internship->faculty_id = $facultyId;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $internship->save();
            }
        }

        return $internship;
    }

    /** Build the compact student summary used by the dashboard hero banner. */
    private function studentSummary($profile): ?array
    {
        if (!$profile) return null;

        $middleInitial = $profile->middle_name ? substr($profile->middle_name, 0, 1) . '.' : null;
        $name = trim($profile->last_name . ', ' . implode(' ', array_filter([$profile->first_name, $middleInitial])));

        return [
            'name'           => $name,
            'student_number' => $profile->student_number,
            'section'        => $profile->section,
            'course_name'    => $profile->program?->name,
            'year_level'     => $profile->year_level,
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
        
        // Ensure they don't already have an active/ongoing one that isn't completed
        $active = $user->activeInternship()->first();
        if ($active && $active->status !== 'completed') {
            return response()->json(['message' => 'You still have an ongoing internship. Finish it first.'], 422);
        }

        $profile = $user->studentProfile;
        $ay = $profile?->school_year ?: '2025-2026';
        $sem = $profile?->semester ?: '2nd Semester';
        $facultyId = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;
        
        $targetHours = 500;
        if (in_array($profile?->program?->name, ['Bachelor of Secondary Education', 'Bachelor of Elementary Education'])) {
            $targetHours = 360;
        }

        $internship = $user->internshipsAsStudent()->create([
            'status' => 'pending_placement',
            'school_year' => $ay,
            'semester' => $sem,
            'term' => "AY {$ay}, {$sem}",
            'program' => $profile?->program?->name,
            'faculty_id' => $facultyId,
            'target_hours' => $targetHours,
            'total_hours_rendered' => 0
        ]);

        return response()->json([
            'message' => 'New practicum deployment started.',
            'internship' => $internship
        ]);
    }

    public function dashboard(Request $request)
    {
        $user       = $request->user()->load('studentProfile.program');
        $internship = $this->internship($request)->load('company');
        $profile    = $user->studentProfile;

        // Attendance stats
        $daysPresent     = $internship->attendance()->where('status', 'validated')->count();
        $hoursRendered   = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
        $targetHours     = $internship->target_hours ?: config('interntrack.target_hours', 500);
        $progressPercent = $targetHours > 0 ? (float) min(100, max(0, round(($hoursRendered / $targetHours) * 100, 1))) : 0.0;

        // Journal stats
        $journalCount = $internship->journals()->whereIn('status', ['submitted', 'approved'])->count();

        // Document compliance stats (properly scoped to logged-in student)
        $studentTargets = [
            ['type' => 'student', 'id' => (string) $user->id]
        ];
        if ($profile) {
            if ($profile->section) {
                $studentTargets[] = ['type' => 'section', 'id' => $profile->section];
            }
            $program = $profile->program?->name ?: 'Bachelor of Science in Information Technology';
            if ($program) {
                $studentTargets[] = ['type' => 'program', 'id' => $program];
            }
        }

        $matchingTemplates = \App\Models\OjtRequirementTemplate::where('is_active', true)
            ->where(function($query) use ($studentTargets) {
                $query->whereHas('targets', function($q) use ($studentTargets) {
                    $q->where(function($subQ) use ($studentTargets) {
                        foreach($studentTargets as $target) {
                            $subQ->orWhere(function($targetQ) use ($target) {
                                $targetQ->where('target_type', $target['type'])
                                        ->where('target_id', $target['id']);
                            });
                        }
                    });
                })->orDoesntHave('targets');
            })
            ->orderBy('sort_order')
            ->get();

        $requiredDocTypes = $matchingTemplates->pluck('name')->unique()->values()->toArray();

        $docsTotal = count($requiredDocTypes);
        // Avoid division by zero
        $docsTotalForCalc = max(1, $docsTotal);

        $validDocStatuses = ['pending_review', 'under_review', 'pending_faculty', 'approved', 'resubmitted', 'completed'];
        $docsSubmitted = $internship->documents()
            ->whereIn('document_type', $requiredDocTypes)
            ->whereIn('status', $validDocStatuses)
            ->distinct('document_type')
            ->count('document_type');

        $docCompliance = (int) min(100, max(0, round(($docsSubmitted / $docsTotalForCalc) * 100)));

        // Evaluation score (average of both evaluations, scaled to 100%)
        $evalAvg = $internship->evaluations()->avg('average_score');
        $evaluationScore = $evalAvg ? min(100.0, max(0.0, round($evalAvg * 20, 1))) : null;

        // Weekly chart — last 8 weeks (single query)
        $weekStart8 = now()->startOfWeek()->subWeeks(7);
        $weekEnd0   = now()->endOfWeek();

        $weeklyData = $internship->attendance()
            ->where('status', 'validated')
            ->whereBetween('date', [$weekStart8->toDateString(), $weekEnd0->toDateString()])
            ->selectRaw('YEARWEEK(date, 1) as yw, SUM(hours_rendered) as total')
            ->groupBy('yw')
            ->pluck('total', 'yw');

        $labels = [];
        $hours  = [];
        for ($i = 7; $i >= 0; $i--) {
            $ws = now()->startOfWeek()->subWeeks($i);
            $yw = $ws->format('oW'); // ISO year + week number, matching YEARWEEK(..., 1)
            $labels[] = 'Wk ' . (8 - $i);
            $hours[]  = round((float) ($weeklyData[$yw] ?? 0), 1);
        }

        // Announcements for student role
        $announcements = Announcement::where(function ($q) {
                $q->where('target_role', 'all')->orWhere('target_role', 'student');
            })
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->orderByDesc('is_pinned')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (Announcement $a) => $a->toClientArray())
            ->values();

        return response()->json([
            'student' => $this->studentSummary($user->studentProfile),
            'stats' => [
                'hours_rendered'   => $hoursRendered,
                'target_hours'     => $targetHours,
                'days_present'     => $daysPresent,
                'journal_count'    => $journalCount,
                'docs_submitted'   => $docsSubmitted,
                'docs_total'       => $docsTotal,
                'progress_percent' => $progressPercent,
                'doc_compliance'   => $docCompliance,
                'evaluation_score' => $evaluationScore,
            ],
            'weekly_chart'  => ['labels' => $labels, 'hours' => $hours],
            'announcements' => $announcements,
            'internship'    => [
                'id'            => $internship->id,
                'term'          => $internship->term,
                'status'        => \App\Support\InternshipStatuses::normalize($internship->status),
                'status_label'  => \App\Support\InternshipStatuses::label($internship->status),
                'status_reason' => $internship->status_reason,
                'company_name'  => $internship->company?->company_name ?? '—',
                'start_date'    => $internship->start_date?->toDateString(),
            ],
        ]);
    }

    /** GET /api/v1/student/attendance */
    public function attendance(Request $request)
    {
        $internship = $this->internship($request);
        if (!$internship->supervisor_id) {
            return response()->json(['message' => 'Attendance tracking is locked until your HTE Supervisor is approved.'], 403);
        }
        $logs = $internship->attendance()
            ->orderByDesc('date')
            ->paginate(20);

        $today       = now()->toDateString();
        $todayRecord = $internship->attendance()->whereDate('date', $today)->first();

        return response()->json([
            'attendance'   => $logs,
            'today_record' => $todayRecord,
            'today_status' => $todayRecord
                ? ($todayRecord->clock_out ? 'clocked_out' : 'clocked_in')
                : 'not_clocked_in',
        ]);
    }

    /** POST /api/v1/student/attendance/clock-in */
    public function clockIn(Request $request)
    {
        $internship = $this->internship($request);
        if (!$internship->supervisor_id) {
            return response()->json(['message' => 'Attendance tracking is locked until your HTE Supervisor is approved.'], 403);
        }
        $today      = now()->toDateString();

        if ($internship->attendance()->whereDate('date', $today)->exists()) {
            return response()->json(['message' => 'You have already clocked in today.'], 422);
        }

        $clockIn = now()->toTimeString();

        $log = $internship->attendance()->create([
            'date'              => $today,
            'clock_in'          => $clockIn,
            'am_time_in'        => $clockIn,
            'status'            => 'pending',
            'clock_in_location' => $request->location ?? null,
        ]);

        audit_log($request->user()->id, 'clock_in', ['date' => $today]);

        return response()->json(['message' => 'Clocked in successfully.', 'record' => $log], 201);
    }

    /** POST /api/v1/student/attendance/clock-out */
    public function clockOut(Request $request)
    {
        $internship = $this->internship($request);
        if (!$internship->supervisor_id) {
            return response()->json(['message' => 'Attendance tracking is locked until your HTE Supervisor is approved.'], 403);
        }
        $today      = now()->toDateString();
        $log        = $internship->attendance()->whereDate('date', $today)->whereNull('clock_out')->firstOrFail();

        $clockIn       = \Carbon\Carbon::parse($log->clock_in);
        $clockOut      = now();
        $hoursRendered = round($clockIn->diffInMinutes($clockOut) / 60, 2);
        $clockOutTime  = $clockOut->toTimeString();

        $log->update([
            'clock_out'          => $clockOutTime,
            'am_time_out'        => $clockOutTime,
            'hours_rendered'     => $hoursRendered,
            'clock_out_location' => $request->location ?? null,
        ]);

        audit_log($request->user()->id, 'clock_out', ['date' => $today, 'hours' => $hoursRendered]);

        return response()->json(['message' => 'Clocked out successfully.', 'record' => $log]);
    }

    /** GET /api/v1/student/logbook */
    public function logbook(Request $request)
    {
        $internship = $this->internship($request);
        $journals   = $internship->journals()->orderByDesc('week_number')->paginate(20);
        return ApiResponse::list($journals);
    }

    /** POST /api/v1/student/logbook */
    public function submitJournal(Request $request)
    {
        $request->validate([
            'week_number'        => 'required|integer|min:1|max:52',
            'date'               => 'required|date',
            'end_date'           => 'required|date|after_or_equal:date',
            'activities_summary' => 'required_without_all:challenges,learnings|string|nullable',
            'challenges'         => 'required_without_all:activities_summary,learnings|string|nullable',
            'learnings'          => 'required_without_all:activities_summary,challenges|string|nullable',
            'notes'              => 'nullable|string',
        ]);

        $internship = $this->internship($request);
        $journal = $internship->journals()->where('week_number', $request->week_number)->first();

        if ($journal && in_array($journal->status, ['approved', 'submitted'])) {
            return response()->json(['message' => 'Journal for this week is already submitted or approved.'], 422);
        }

        $data = [
            'entry_number'       => $request->week_number,
            'week_number'        => $request->week_number,
            'date'               => $request->date,
            'end_date'           => $request->end_date,
            'activities_summary' => $request->activities_summary,
            'challenges'         => $request->challenges,
            'learnings'          => $request->learnings,
            'notes'              => $request->notes,
            'status'             => 'submitted',
        ];

        if ($journal) {
            $journal->update($data);
        } else {
            $journal = $internship->journals()->create($data);
        }

        audit_log($request->user()->id, 'submit_journal', ['week_number' => $request->week_number]);

        return response()->json(['message' => 'Weekly journal submitted successfully.', 'journal' => $journal], 201);
    }

    /** GET /api/v1/student/documents */
    public function documents(Request $request)
    {
        $internship = $this->internship($request);
        $user = $request->user();

        $templates = RequirementAudience::scopeTemplatesForStudent(
            \App\Models\OjtRequirementTemplate::where('is_active', true)
                ->with(['creator.facultyProfile', 'creator.supervisorProfile', 'creator.studentProfile', 'attachments']),
            $user
        )
            ->orderBy('sort_order')
            ->get();

        $submitted = $internship->documents()->with('attachments')->get()->keyBy('document_type');

        $docs = $templates->map(function ($template) use ($submitted) {
            $type = $template->name;
            $doc = $submitted->get($type);
            
            $status = $doc?->status ?? 'not_submitted';
            $isMissed = false;

            if ($template->deadline && now()->greaterThan($template->deadline)) {
                if (!$doc || $status === 'rejected' || $status === 'not_submitted') {
                    $status = 'no_submission';
                    $isMissed = true;
                }
            }

            $creator = $template->creator;
            $senderName = $creator ? $creator->profile_name : 'System';
            $senderRole = $creator ? ucfirst($creator->role) : 'Admin';

            return [
                'template_id'   => $template->id,
                'has_template'  => $template->attachments->isNotEmpty(),
                'template_attachments' => $template->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_name' => $a->file_name,
                        'file_path' => $a->file_path,
                        'file_url' => url('/api/v1/files/download?path=' . urlencode($a->file_path)),
                    ];
                })->toArray(),
                'template_link' => $template->drive_link,
                'description'   => $template->description,
                'document_type' => $type,
                'sender'        => [
                    'name' => $senderName,
                    'role' => $senderRole,
                ],
                'status'        => $status,
                'deadline'      => $template->deadline?->toIso8601String(),
                'is_missed'     => $isMissed,
                'submitted_at'  => $doc?->submitted_at?->toIso8601String() ?? null,
                'remarks'       => $doc?->remarks ?? null,
                'id'            => $doc?->id ?? null,
                'attachments'   => $doc ? $doc->attachments->map(function ($a) {
                    return [
                        'id' => $a->id,
                        'file_name' => $a->file_name,
                        'file_path' => $a->file_path,
                        'file_url' => url('/api/v1/files/download?path=' . urlencode($a->file_path)),
                    ];
                })->toArray() : [],
                'drive_link'    => $doc?->drive_link ?? null,
            ];
        });

        $docsTotal = count($templates);
        $requiredTypes = $templates->pluck('name');

        $payload = ApiResponse::list($docs)->getData(true);
        $payload['meta']['docs_total'] = $docsTotal;
        $payload['meta']['internship_id'] = $internship->id;
        $payload['required_types'] = $requiredTypes;

        return response()->json($payload);
    }

    /** POST /api/v1/student/documents/upload */
    public function uploadDocument(Request $request)
    {
        $user = $request->user();

        $templates = RequirementAudience::scopeTemplatesForStudent(
            \App\Models\OjtRequirementTemplate::where('is_active', true)->with('creator'),
            $user
        )->get()->keyBy('name');

        $validTypes = $templates->keys()->toArray();

        $request->validate([
            'document_type' => ['required', 'string', Rule::in($validTypes)],
            'files.*'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240',
            'drive_link'    => 'nullable|url|max:2048',
        ]);

        if (!$request->hasFile('files') && empty($request->drive_link)) {
            return response()->json(['message' => 'Please provide either a file or a Google Drive link.'], 422);
        }

        $template = $templates->get($request->document_type);

        if ($template && $template->deadline && now()->greaterThan($template->deadline)) {
            return response()->json(['message' => 'The deadline for this requirement has expired.'], 403);
        }

        $internship = $this->internship($request);
        $reviewStage = $template?->creator?->role === 'faculty' ? 'faculty' : 'coordinator';

        $existing = $internship->documents()->where('document_type', $request->document_type)->first();

        if ($existing) {
            $updateData = [
                'status'        => 'pending',
                'current_stage' => $reviewStage,
                'submitted_at'  => now(),
                'remarks'       => null,
            ];

            if ($request->has('drive_link')) {
                $updateData['drive_link'] = $request->drive_link;
            }

            $existing->update($updateData);
            $doc = $existing;
        } else {
            $doc = $internship->documents()->create([
                'document_type' => $request->document_type,
                'drive_link'    => $request->drive_link,
                'status'        => 'pending',
                'current_stage' => $reviewStage,
                'submitted_at'  => now(),
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
        $internship  = $this->internship($request);
        $evaluations = $internship->evaluations()->with('evaluator')->get();
        return ApiResponse::list($evaluations);
    }

    /** POST /api/v1/student/evaluations */
    public function submitEvaluation(Request $request)
    {
        $request->validate([
            'evaluation_period' => 'required|string',
            'form_type'         => 'required|in:FO-22,FO-23',
            'responses'         => 'required|array',
            'general_comments'  => 'nullable|string',
        ]);

        $internship = $this->internship($request);
        $period = $request->input('evaluation_period');

        $eval = \App\Models\Evaluation::updateOrCreate(
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
        $user    = $request->user()->load('studentProfile.program');
        $history = $user->internshipsAsStudent()->with(['company', 'supervisor.supervisorProfile', 'faculty.facultyProfile'])->orderBy('school_year', 'desc')
            ->withCount(['attendance as validated_days' => fn($q) => $q->where('status', 'validated')])
            ->orderByDesc('created_at')
            ->get();

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
            'notes'         => 'nullable|string|max:1000',
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

        $eligible  = CertificateEligibilityService::isEligible($internship);
        $checklist = CertificateEligibilityService::checklist($internship);

        // If newly eligible and never issued, mark the flag
        if ($eligible && !$internship->certificate_eligible) {
            $internship->update(['certificate_eligible' => true]);
        }

        return response()->json([
            'eligible'  => $eligible,
            'issued_at' => $internship->certificate_issued_at,
            'checklist' => $checklist,
        ]);
    }

    public function companies()
    {
        $companies = \App\Models\Company::where('moa_status', 'active')->get();
        return response()->json(['companies' => $companies]);
    }

    public function applications(Request $request)
    {
        $applications = $request->user()->internshipsAsStudent()
            ->where('status', 'pending_placement')
            ->with('company')
            ->get();
        return response()->json(['applications' => $applications]);
    }

    public function applyCompany(Request $request)
    {
        $request->validate(['company_id' => 'required|exists:companies,id']);
        return response()->json(['message' => 'Application submitted. Please await coordinator approval.']);
    }

    public function hteRequests(Request $request)
    {
        $requests = \App\Models\HteRequest::where('student_id', $request->user()->id)->get();
        return response()->json(['requests' => $requests]);
    }

    public function submitHteRequest(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string',
            'address' => 'required|string',
            'contact_person' => 'required|string',
            'contact_email' => 'required|email',
            'contact_number' => 'required|string',
            'remarks' => 'nullable|string',
        ]);
        $data['student_id'] = $request->user()->id;
        $data['status'] = 'pending';
        $req = \App\Models\HteRequest::create($data);
        return response()->json(['message' => 'HTE Request submitted.', 'request' => $req]);
    }
}
