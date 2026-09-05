<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\Internship;
use App\Models\OvertimeEntry;
use App\Models\WorkSchedule;
use App\Services\DtrWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DtrWorkflowController extends Controller
{
    public function __construct(private DtrWorkflowService $dtr)
    {
    }

    public function undoClockOut(Request $request)
    {
        $internship = $this->studentInternship($request);
        $today = now()->toDateString();
        $log = $internship->attendance()->whereDate('date', $today)->whereNotNull('clock_out')->firstOrFail();

        $record = $this->dtr->undoClockOut($log, $request->user());

        return response()->json([
            'message' => 'Clock-out undone. You are clocked in again.',
            'record' => $record,
            'today_status' => 'clocked_in',
        ]);
    }

    public function decideOvertime(Request $request)
    {
        $request->validate([
            'attendance_log_id' => 'required|integer',
            'accept' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $internship = $this->studentInternship($request);
        $log = $internship->attendance()->with('internship')->findOrFail($request->integer('attendance_log_id'));

        $entry = $this->dtr->decideOvertime($log, $request->user(), (bool) $request->boolean('accept'), $request->input('reason'));

        return response()->json([
            'message' => $request->boolean('accept')
                ? 'Overtime submitted for supervisor approval.'
                : 'Excess time discarded. It will not be added to your DTR.',
            'overtime_entry' => $entry,
        ]);
    }

    public function studentSchedules(Request $request)
    {
        $internship = $this->studentInternship($request);

        return response()->json([
            'active_schedule' => $this->dtr->serializeSchedule($this->dtr->activeScheduleFor($internship, now())),
            'pending_schedule' => $this->dtr->serializeSchedule($this->dtr->pendingScheduleFor($internship)),
            'history' => $this->dtr->scheduleHistory($internship)->map(fn (WorkSchedule $s) => $this->dtr->serializeSchedule($s))->values(),
        ]);
    }

    public function proposeSchedule(Request $request)
    {
        $request->validate([
            'start_time' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'end_time' => ['required', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
        ]);

        $internship = $this->studentInternship($request);
        $schedule = $this->dtr->proposeSchedule(
            $internship,
            $request->user(),
            $request->input('start_time'),
            $request->input('end_time')
        );

        return response()->json([
            'message' => 'Schedule proposal submitted. Your supervisor must approve it before it becomes active.',
            'schedule' => $this->dtr->serializeSchedule($schedule),
        ], 201);
    }

    public function studentCorrections(Request $request)
    {
        $internship = $this->studentInternship($request);
        $items = AttendanceCorrectionRequest::query()
            ->where('internship_id', $internship->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AttendanceCorrectionRequest $r) => $this->serializeCorrection($r));

        return response()->json(['data' => $items]);
    }

    public function submitCorrection(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'requested_clock_in' => ['nullable', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'requested_clock_out' => ['nullable', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'reason' => 'nullable|string|max:1000',
        ]);

        $internship = $this->studentInternship($request);
        $correction = $this->dtr->submitCorrection(
            $internship,
            $request->user(),
            $request->input('date'),
            $request->input('requested_clock_in'),
            $request->input('requested_clock_out'),
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Correction request submitted. It will be reviewed by your supervisor first, then faculty.',
            'correction' => $this->serializeCorrection($correction),
        ], 201);
    }

    public function supervisorSchedules(Request $request)
    {
        $ids = $this->supervisorInternshipIds($request);
        $pending = WorkSchedule::query()
            ->with(['internship.student.studentProfile'])
            ->whereIn('internship_id', $ids)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::list($pending);
    }

    public function reviewSchedule(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $schedule = WorkSchedule::with('internship')->findOrFail($id);
        $updated = $this->dtr->reviewSchedule($schedule, $request->user(), $request->input('action'), $request->input('remarks'));

        return response()->json([
            'message' => 'Schedule proposal '.$request->input('action').'.',
            'schedule' => $this->dtr->serializeSchedule($updated),
        ]);
    }

    public function supervisorOvertime(Request $request)
    {
        $ids = $this->supervisorInternshipIds($request);
        $pending = OvertimeEntry::query()
            ->with(['internship.student.studentProfile', 'attendanceLog'])
            ->whereIn('internship_id', $ids)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::list($pending);
    }

    public function reviewOvertime(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $entry = OvertimeEntry::with(['internship', 'attendanceLog'])->findOrFail($id);
        $updated = $this->dtr->reviewOvertime($entry, $request->user(), $request->input('action'), $request->input('remarks'));

        return response()->json([
            'message' => 'Overtime entry '.$request->input('action').'.',
            'overtime_entry' => $updated,
        ]);
    }

    public function supervisorCorrections(Request $request)
    {
        $ids = $this->supervisorInternshipIds($request);
        $pending = AttendanceCorrectionRequest::query()
            ->with(['internship.student.studentProfile'])
            ->whereIn('internship_id', $ids)
            ->where('status', AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AttendanceCorrectionRequest $r) => $this->serializeCorrection($r, true));

        return ApiResponse::list($pending);
    }

    public function reviewCorrectionAsSupervisor(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $correction = AttendanceCorrectionRequest::with('internship')->findOrFail($id);
        $updated = $this->dtr->reviewCorrectionAsSupervisor(
            $correction,
            $request->user(),
            $request->input('action'),
            $request->input('remarks')
        );

        return response()->json([
            'message' => $request->input('action') === 'approved'
                ? 'Correction approved. It now awaits faculty review.'
                : 'Correction request rejected. The official DTR was not changed.',
            'correction' => $this->serializeCorrection($updated),
        ]);
    }

    public function facultyCorrections(Request $request)
    {
        $ids = $this->facultyInternshipIds($request);
        $pending = AttendanceCorrectionRequest::query()
            ->with(['internship.student.studentProfile'])
            ->whereIn('internship_id', $ids)
            ->where('status', AttendanceCorrectionRequest::STATUS_PENDING_FACULTY)
            ->orderByDesc('id')
            ->get()
            ->map(fn (AttendanceCorrectionRequest $r) => $this->serializeCorrection($r, true));

        return ApiResponse::list($pending);
    }

    public function reviewCorrectionAsFaculty(Request $request, int $id)
    {
        $request->validate([
            'action' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string|max:500',
        ]);

        $correction = AttendanceCorrectionRequest::with('internship')->findOrFail($id);
        $updated = $this->dtr->reviewCorrectionAsFaculty(
            $correction,
            $request->user(),
            $request->input('action'),
            $request->input('remarks')
        );

        return response()->json([
            'message' => $request->input('action') === 'approved'
                ? 'Correction approved and applied to the official DTR.'
                : 'Correction request rejected. The official DTR was not changed.',
            'correction' => $this->serializeCorrection($updated),
        ]);
    }

    public function supervisorHistory(Request $request)
    {
        return $this->historyResponse($this->supervisorInternshipIds($request), $request);
    }

    public function facultyHistory(Request $request)
    {
        return $this->historyResponse($this->facultyInternshipIds($request), $request);
    }

    public function supervisorAudits(Request $request)
    {
        return $this->auditsResponse($request, $this->supervisorInternshipIds($request));
    }

    public function facultyAudits(Request $request)
    {
        return $this->auditsResponse($request, $this->facultyInternshipIds($request));
    }

    public function studentAudits(Request $request)
    {
        $internship = $this->studentInternship($request);

        return $this->auditsResponse($request, collect([$internship->id]));
    }

    private function historyResponse($internshipIds, Request $request)
    {
        $query = AttendanceLog::query()
            ->with(['internship.student.studentProfile', 'internship.company', 'placement'])
            ->whereIn('internship_id', $internshipIds)
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('internship_id')) {
            $internshipId = (int) $request->internship_id;
            if (! $internshipIds->contains($internshipId)) {
                return response()->json(['message' => 'Internship is not assigned to you.'], 403);
            }
            $query->where('internship_id', $internshipId);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $page = $query->paginate(25);
        $this->dtr->decorateLogs(collect($page->items()));

        return ApiResponse::list($page);
    }

    private function auditsResponse(Request $request, $internshipIds)
    {
        $request->validate([
            'type' => 'required|in:overtime,correction',
            'id' => 'required|integer',
        ]);

        $audits = $this->dtr->auditsFor($request->input('type'), $request->integer('id'));
        $first = $audits->first();
        if ($first && ! $internshipIds->contains((int) $first->internship_id)) {
            abort(403, 'You may only view audit trails for your assigned students.');
        }

        if ($audits->isEmpty()) {
            if ($request->input('type') === 'overtime') {
                $entry = OvertimeEntry::findOrFail($request->integer('id'));
                if (! $internshipIds->contains((int) $entry->internship_id)) {
                    abort(403, 'You may only view audit trails for your assigned students.');
                }
            } else {
                $entry = AttendanceCorrectionRequest::findOrFail($request->integer('id'));
                if (! $internshipIds->contains((int) $entry->internship_id)) {
                    abort(403, 'You may only view audit trails for your assigned students.');
                }
            }
        }

        return ApiResponse::list($audits);
    }

    private function serializeCorrection(AttendanceCorrectionRequest $r, bool $withStudent = false): array
    {
        $payload = [
            'id' => $r->id,
            'internship_id' => $r->internship_id,
            'attendance_log_id' => $r->attendance_log_id,
            'date' => $r->date?->toDateString(),
            'original_clock_in' => $this->dtr->timeString($r->original_clock_in),
            'original_clock_out' => $this->dtr->timeString($r->original_clock_out),
            'original_hours_rendered' => $r->original_hours_rendered,
            'requested_clock_in' => $this->dtr->timeString($r->requested_clock_in),
            'requested_clock_out' => $this->dtr->timeString($r->requested_clock_out),
            'reason' => $r->reason,
            'status' => $r->status,
            'status_label' => $r->statusLabel(),
            'supervisor_decision' => $r->supervisor_decision,
            'supervisor_reviewed_at' => optional($r->supervisor_reviewed_at)?->toIso8601String(),
            'supervisor_remarks' => $r->supervisor_remarks,
            'faculty_decision' => $r->faculty_decision,
            'faculty_reviewed_at' => optional($r->faculty_reviewed_at)?->toIso8601String(),
            'faculty_remarks' => $r->faculty_remarks,
            'rejected_by_role' => $r->rejected_by_role,
            'applied_clock_in' => $this->dtr->timeString($r->applied_clock_in),
            'applied_clock_out' => $this->dtr->timeString($r->applied_clock_out),
            'applied_hours_rendered' => $r->applied_hours_rendered,
            'applied_at' => optional($r->applied_at)?->toIso8601String(),
        ];

        if ($withStudent || $r->relationLoaded('internship')) {
            $profile = $r->internship?->student?->studentProfile;
            $payload['student_name'] = $profile
                ? trim(($profile->last_name ?? '').', '.($profile->first_name ?? ''))
                : ($r->internship?->student?->username ?? null);
            $payload['internship'] = $r->internship;
        }

        return $payload;
    }

    private function studentInternship(Request $request): Internship
    {
        $user = $request->user();
        $requestedId = $request->header('X-Internship-Id') ?: $request->input('internship_id');
        $query = $user->internshipsAsStudent();
        $internship = $requestedId
            ? $query->where('id', $requestedId)->first()
            : ($user->activeInternship()->first() ?: $query->latest('id')->first());

        if (! $internship) {
            abort(404, 'No internship found.');
        }
        if (! $internship->supervisor_id) {
            abort(403, 'Attendance tracking is locked until your HTE Supervisor is approved.');
        }

        return $internship;
    }

    private function supervisorInternshipIds(Request $request)
    {
        return Internship::where('supervisor_id', $request->user()->id)->pluck('id');
    }

    private function facultyInternshipIds(Request $request)
    {
        return Internship::inDepartment()->where('faculty_id', $request->user()->id)->pluck('id');
    }
}
