<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\JournalEntry;
use App\Models\Document;
use App\Models\Announcement;
use App\Services\AbsorptionService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    private function internship(Request $request)
    {
        $user = $request->user();
        $internship = $user->activeInternship()
            ->with(['company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile'])
            ->first();

        if (!$internship) {
            $profile = $user->studentProfile;
            $ay = $profile?->academic_year ?: '2025-2026';
            $sem = (int) ($profile?->semester ?: 1);
            $internship = $user->internshipsAsStudent()->create([
                'status' => 'pending_placement',
                'academic_year' => $ay,
                'semester' => $sem,
                'term' => "AY {$ay}, Sem {$sem}",
                'program' => $profile?->program ?: $profile?->course_name,
                'target_hours' => 360,
                'total_hours_rendered' => 0
            ]);
            $internship->load(['company', 'supervisor.supervisorProfile', 'faculty.facultyProfile', 'coordinator.facultyProfile']);
        } elseif (empty($internship->program)) {
            // Backfill denormalized program from student profile when missing.
            $profile = $user->studentProfile;
            $program = $profile?->program ?: $profile?->course_name;
            if ($program) {
                $internship->forceFill(['program' => $program])->save();
            }
        }

        return $internship;
    }

    /** Build the compact student summary used by the dashboard hero banner. */
    private function studentSummary($profile): ?array
    {
        if (!$profile) return null;

        $middleInitial = $profile->middle_name ? substr($profile->middle_name, 0, 1) . '.' : null;
        $name = trim(implode(' ', array_filter([$profile->first_name, $middleInitial, $profile->last_name])));

        return [
            'name'           => $name,
            'student_number' => $profile->student_number,
            'section'        => $profile->section,
            'course_name'    => $profile->course_name,
            'year_level'     => $profile->year_level,
        ];
    }

    /** GET /api/v1/student/dashboard */
    public function dashboard(Request $request)
    {
        $user       = $request->user()->load('studentProfile');
        $internship = $this->internship($request)->load('company');

        // Attendance stats
        $daysPresent     = $internship->attendance()->where('status', 'validated')->count();
        $hoursRendered   = (float) $internship->attendance()->where('status', 'validated')->sum('hours_rendered');
        $targetHours     = $internship->target_hours;
        $progressPercent = $targetHours > 0 ? round(($hoursRendered / $targetHours) * 100, 1) : 0;

        // Journal stats
        $journalCount = $internship->journals()->whereIn('status', ['submitted', 'approved'])->count();

        // Document stats
        $docsTotal     = 13;
        $docsSubmitted = $internship->documents()->whereIn('status', ['pending_review', 'under_review', 'pending_faculty', 'approved', 'resubmitted'])->count();
        $docCompliance = round(($docsSubmitted / $docsTotal) * 100);

        // Evaluation score (average of both evaluations)
        $evalAvg = $internship->evaluations()->avg('average_score');

        // Weekly chart — last 8 weeks
        $weeks  = collect();
        $labels = [];
        $hours  = [];
        for ($i = 7; $i >= 0; $i--) {
            $weekStart = now()->startOfWeek()->subWeeks($i);
            $weekEnd   = (clone $weekStart)->endOfWeek();
            $label     = 'Wk ' . (8 - $i);
            $wkHours   = (float) $internship->attendance()
                ->where('status', 'validated')
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->sum('hours_rendered');
            $labels[] = $label;
            $hours[]  = round($wkHours, 1);
        }

        // Announcements for student role
        $announcements = Announcement::where(function ($q) {
                $q->where('target_role', 'all')->orWhere('target_role', 'student');
            })
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->latest()
            ->take(5)
            ->get(['id', 'title', 'content', 'created_at', 'is_pinned']);

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
                'evaluation_score' => $evalAvg ? round($evalAvg * 20, 1) : null,
            ],
            'weekly_chart'  => ['labels' => $labels, 'hours' => $hours, 'target' => array_fill(0, 8, 20)],
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
        $logs = $internship->attendance()
            ->orderByDesc('date')
            ->paginate(20);

        // Check if already clocked in today
        $today       = now()->toDateString();
        $todayRecord = $internship->attendance()->whereDate('date', $today)->first();

        return response()->json([
            'attendance'     => $logs,
            'today_status'   => $todayRecord
                ? ($todayRecord->clock_out ? 'clocked_out' : 'clocked_in')
                : 'not_clocked_in',
            'today_record'   => $todayRecord,
        ]);
    }

    /** POST /api/v1/student/attendance/clock-in */
    public function clockIn(Request $request)
    {
        $internship = $this->internship($request);
        $today      = now()->toDateString();

        if ($internship->attendance()->whereDate('date', $today)->exists()) {
            return response()->json(['message' => 'You have already clocked in today.'], 422);
        }

        $log = $internship->attendance()->create([
            'date'               => $today,
            'clock_in'           => now()->toTimeString(),
            'status'             => 'pending',
            'clock_in_location'  => $request->location ?? null,
        ]);

        audit_log($request->user()->id, 'clock_in', ['date' => $today]);

        return response()->json(['message' => 'Clocked in successfully.', 'record' => $log], 201);
    }

    /** POST /api/v1/student/attendance/clock-out */
    public function clockOut(Request $request)
    {
        $internship = $this->internship($request);
        $today      = now()->toDateString();
        $log        = $internship->attendance()->whereDate('date', $today)->whereNull('clock_out')->firstOrFail();

        $clockIn      = \Carbon\Carbon::parse($log->clock_in);
        $clockOut     = now();
        $hoursRendered = round($clockIn->diffInMinutes($clockOut) / 60, 2);

        $log->update([
            'clock_out'          => $clockOut->toTimeString(),
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
            'week_number'    => 'required|integer|min:1|max:52',
            'hours_declared' => 'required|numeric|min:1|max:60',
            'file'           => 'required|file|max:10240', // 10MB max, any file type
            'notes'          => 'nullable|string',
        ]);

        $internship = $this->internship($request);
        $journal = $internship->journals()->where('week_number', $request->week_number)->first();

        if ($journal && in_array($journal->status, ['approved', 'submitted'])) {
            return response()->json(['message' => 'Journal for this week is already submitted or approved.'], 422);
        }

        $path = $request->file('file')->store('journals/' . $internship->id, 'public');

        if ($journal) {
            $journal->update([
                'hours_declared' => $request->hours_declared,
                'file_path'      => $path,
                'notes'          => $request->notes,
                'status'         => 'submitted'
            ]);
        } else {
            $journal = $internship->journals()->create([
                'entry_number'   => $request->week_number, // Fallback for legacy DB column
                'week_number'    => $request->week_number,
                'hours_declared' => $request->hours_declared,
                'file_path'      => $path,
                'notes'          => $request->notes,
                'status'         => 'submitted',
            ]);
        }

        audit_log($request->user()->id, 'submit_journal', ['week_number' => $request->week_number]);

        return response()->json(['message' => 'Weekly journal submitted successfully.', 'journal' => $journal], 201);
    }

    /** GET /api/v1/student/documents */
    public function documents(Request $request)
    {
        $internship = $this->internship($request);

        // All required document types per UC Internship Manual
        $required = [
            'Curriculum Vitae (PNC:AA-FO-27)',
            'Medical Clearance',
            'Psychological Assessment Certificate',
            'Notarized Student Internship Consent Form (PNC:AA-FO-28)',
            'Student Internship Acceptance Form (PNC:AA-FO-29)',
            'Application Letter',
            'Recommendation Letter',
            'MOA / LOA / TOR',
            'Company Profile',
            'Training Plan',
            'Midterm Evaluation',
            'Final Report',
            'Certificate of Completion'
        ];

        $submitted = $internship->documents()->get()->keyBy('document_type');

        $docs = collect($required)->map(function ($type) use ($submitted) {
            $doc = $submitted->get($type);
            return [
                'document_type' => $type,
                'status'        => $doc?->status ?? 'not_submitted',
                'current_stage' => $doc?->current_stage ?? null,
                'file_name'     => $doc?->file_name ?? null,
                'submitted_at'  => $doc?->submitted_at?->toDateString() ?? null,
                'remarks'       => $doc?->remarks ?? null,
                'id'            => $doc?->id ?? null,
                'file_url'      => $doc ? asset('storage/' . $doc->file_path) : null,
            ];
        });

        return ApiResponse::list($docs);
    }

    /** POST /api/v1/student/documents/upload */
    public function uploadDocument(Request $request)
    {
        $request->validate([
            'document_type' => 'required|string',
            'file'          => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $internship = $this->internship($request);
        $file       = $request->file('file');
        $path       = $file->store("internships/{$internship->id}/documents", 'public');

        // Update or create
        $existing = $internship->documents()->where('document_type', $request->document_type)->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->update([
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'status'        => $existing->status === 'rejected' ? 'resubmitted' : 'pending_review',
                'current_stage' => 'coordinator',
                'submitted_at'  => now(),
                'remarks'       => null,
            ]);
            $doc = $existing;
        } else {
            $doc = $internship->documents()->create([
                'document_type' => $request->document_type,
                'file_path'     => $path,
                'file_name'     => $file->getClientOriginalName(),
                'file_size'     => $file->getSize(),
                'mime_type'     => $file->getMimeType(),
                'status'        => 'pending_review',
                'current_stage' => 'coordinator',
                'submitted_at'  => now(),
            ]);
        }

        audit_log($request->user()->id, 'upload_document', ['type' => $request->document_type]);

        return response()->json(['message' => 'Document uploaded successfully.', 'document' => $doc], 201);
    }

    /** GET /api/v1/student/evaluations */
    public function evaluations(Request $request)
    {
        $internship  = $this->internship($request);
        $evaluations = $internship->evaluations()->with('evaluator')->get();
        return ApiResponse::list($evaluations);
    }

    /** GET /api/v1/student/records */
    public function records(Request $request)
    {
        $user    = $request->user()->load('studentProfile');
        $history = $request->user()->internshipsAsStudent()
            ->with('company')
            ->withCount(['attendance as validated_days' => fn($q) => $q->where('status', 'validated')])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'profile' => $user->studentProfile,
        ] + ApiResponse::list($history)->getData(true));
    }

    /**
     * POST /api/v1/student/absorption/declare
     * Optional: student reports they were hired — stays pending until supervisor/coord confirms.
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
            'message' => 'Your hire declaration was submitted. It stays pending until your supervisor or coordinator confirms.',
            'internship' => $updated,
        ]);
    }

    /** GET /api/v1/student/announcements */
    public function announcements(Request $request)
    {
        $items = Announcement::where(function ($q) {
                $q->where('target_role', 'all')->orWhere('target_role', 'student');
            })
            ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::list($items);
    }
}
