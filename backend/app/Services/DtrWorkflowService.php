<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLog;
use App\Models\DtrRequestAudit;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\OvertimeEntry;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DtrWorkflowService
{
    public const GRACE_MINUTES = 5;
    public const CORRECTION_MAX_DAYS = 3;

    public function activeScheduleFor(Internship $internship, Carbon|string $date): ?WorkSchedule
    {
        $day = Carbon::parse($date)->toDateString();

        return WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'approved')
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($q) use ($day) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $day);
            })
            ->orderByDesc('id')
            ->first();
    }

    public function pendingScheduleFor(Internship $internship): ?WorkSchedule
    {
        return WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    public function scheduleHistory(Internship $internship): Collection
    {
        return WorkSchedule::query()
            ->where('internship_id', $internship->id)
            ->whereIn('status', ['approved', 'superseded'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get();
    }

    public function proposeSchedule(Internship $internship, User $student, string $startTime, string $endTime): WorkSchedule
    {
        if (! $internship->supervisor_id) {
            throw ValidationException::withMessages([
                'schedule' => 'A supervisor must be assigned before you can propose a working hours schedule.',
            ]);
        }

        $start = $this->normalizeTime($startTime);
        $end = $this->normalizeTime($endTime);
        if ($start === $end) {
            throw ValidationException::withMessages([
                'end_time' => 'End time must be different from start time.',
            ]);
        }

        return DB::transaction(function () use ($internship, $student, $start, $end) {
            $existing = WorkSchedule::query()
                ->where('internship_id', $internship->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'schedule' => 'You already have a pending schedule proposal. Wait for your supervisor to review it, or it must be rejected before you submit a new one.',
                ]);
            }

            $schedule = WorkSchedule::create([
                'internship_id' => $internship->id,
                'proposed_by' => $student->id,
                'start_time' => $start,
                'end_time' => $end,
                'status' => 'pending',
            ]);

            if ($internship->supervisor_id) {
                Notification::notify(
                    (int) $internship->supervisor_id,
                    'attendance_schedule_proposed',
                    'Working hours schedule proposed',
                    $this->studentName($internship).' proposed a working hours schedule ('.$this->formatTimeRange($start, $end).').',
                    '/supervisor/attendance-validation',
                    ['schedule_id' => $schedule->id, 'internship_id' => $internship->id]
                );
            }

            audit_log($student->id, 'dtr_schedule_proposed', [
                'schedule_id' => $schedule->id,
                'start_time' => $start,
                'end_time' => $end,
            ]);

            return $schedule;
        });
    }

    public function reviewSchedule(WorkSchedule $schedule, User $supervisor, string $action, ?string $remarks = null): WorkSchedule
    {
        if ($schedule->status !== 'pending') {
            throw ValidationException::withMessages([
                'schedule' => 'Only pending schedule proposals can be reviewed.',
            ]);
        }

        $internship = $schedule->internship;
        if ((int) $internship->supervisor_id !== (int) $supervisor->id) {
            abort(403, 'You may only review schedules for your assigned interns.');
        }

        if ($action === 'rejected') {
            $schedule->update([
                'status' => 'rejected',
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            Notification::notify(
                (int) $internship->student_id,
                'attendance_schedule_rejected',
                'Working hours schedule rejected',
                'Your supervisor rejected your working hours proposal. Please submit a new schedule.'.($remarks ? ' Reason: '.$remarks : ''),
                '/student/attendance',
                ['schedule_id' => $schedule->id]
            );

            audit_log($supervisor->id, 'dtr_schedule_rejected', [
                'schedule_id' => $schedule->id,
                'remarks' => $remarks,
            ]);

            return $schedule->fresh();
        }

        $today = now()->toDateString();

        DB::transaction(function () use ($schedule, $supervisor, $internship, $remarks, $today) {
            WorkSchedule::query()
                ->where('internship_id', $internship->id)
                ->where('status', 'approved')
                ->whereNull('effective_to')
                ->get()
                ->each(function (WorkSchedule $current) use ($today) {
                    $effectiveTo = $current->effective_from && $current->effective_from->toDateString() >= $today
                        ? $current->effective_from->toDateString()
                        : Carbon::parse($today)->subDay()->toDateString();

                    $current->update([
                        'status' => 'superseded',
                        'effective_to' => $effectiveTo,
                    ]);
                });

            $schedule->update([
                'status' => 'approved',
                'effective_from' => $today,
                'effective_to' => null,
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);
        });

        Notification::notify(
            (int) $internship->student_id,
            'attendance_schedule_approved',
            'Working hours schedule approved',
            'Your supervisor approved your working hours schedule ('.$this->formatTimeRange($schedule->start_time, $schedule->end_time).'). It is now your Active Schedule.',
            '/student/attendance',
            ['schedule_id' => $schedule->id]
        );

        audit_log($supervisor->id, 'dtr_schedule_approved', ['schedule_id' => $schedule->id]);

        return $schedule->fresh();
    }

    public function clockOutAt(): Carbon
    {
        return now();
    }

    /**
     * @return array{record: AttendanceLog, overtime_detected: bool, excess_minutes: int, undo_expires_at: string, can_undo_clock_out: bool}
     */
    public function finalizeClockOut(AttendanceLog $log, Carbon $clockOut, ?string $location = null): array
    {
        $clockOutTime = $clockOut->format('H:i:s');
        $clockIn = $this->combineDateAndTime($log->date, $log->clock_in);
        $schedule = $this->activeScheduleFor($log->internship, $log->date);

        $fullMinutes = $this->minutesBetween($clockIn, $clockOut);
        $baseMinutes = $fullMinutes;
        $excessMinutes = 0;

        if ($schedule) {
            $schedStart = $this->combineDateAndTime($log->date, $schedule->start_time);
            $schedEnd = $this->combineDateAndTime($log->date, $schedule->end_time);
            $overlapStart = $clockIn->greaterThan($schedStart) ? $clockIn->copy() : $schedStart;
            $overlapEnd = $clockOut->lessThan($schedEnd) ? $clockOut->copy() : $schedEnd;
            $baseMinutes = $overlapEnd->greaterThan($overlapStart)
                ? $this->minutesBetween($overlapStart, $overlapEnd)
                : 0;
            if ($clockOut->greaterThan($schedEnd)) {
                $excessMinutes = $this->minutesBetween($schedEnd, $clockOut);
            }
        }

        $hoursRendered = round($baseMinutes / 60, 2);

        $log->update([
            'clock_out' => $clockOutTime,
            'am_time_out' => $clockOutTime,
            'hours_rendered' => $hoursRendered,
            'overtime_hours' => $log->overtime_hours ?: 0,
            'clock_out_location' => $location,
        ]);

        $undoExpires = $clockOut->copy()->addMinutes(self::GRACE_MINUTES);

        return [
            'record' => $log->fresh(),
            'overtime_detected' => $excessMinutes > 0,
            'excess_minutes' => $excessMinutes,
            'undo_expires_at' => $undoExpires->toIso8601String(),
            'can_undo_clock_out' => true,
        ];
    }

    public function canUndoClockOut(AttendanceLog $log, ?Carbon $now = null): bool
    {
        $now ??= now();
        if (! $log->clock_out || $log->status !== 'pending') {
            return false;
        }

        $clockOutAt = $this->combineDateAndTime($log->date, $log->clock_out);

        return $now->lt($clockOutAt->copy()->addMinutes(self::GRACE_MINUTES));
    }

    public function undoClockOut(AttendanceLog $log, User $student): AttendanceLog
    {
        if ((int) $log->internship->student_id !== (int) $student->id) {
            abort(403, 'You may only undo your own clock-out.');
        }

        if ($log->status !== 'pending') {
            throw ValidationException::withMessages([
                'clock_out' => 'Clock-out can no longer be undone after supervisor validation.',
            ]);
        }

        if (! $this->canUndoClockOut($log)) {
            throw ValidationException::withMessages([
                'clock_out' => 'The undo grace window has expired.',
            ]);
        }

        OvertimeEntry::query()
            ->where('attendance_log_id', $log->id)
            ->whereIn('status', ['pending', 'declined'])
            ->get()
            ->each(function (OvertimeEntry $entry) use ($student) {
                $this->writeAudit($entry, 'cancelled_on_undo', $student, $entry->status === 'pending' ? 'cancelled' : 'declined', [
                    'original_state' => $this->overtimeOriginalState($entry),
                ]);
                $entry->delete();
            });

        $log->update([
            'clock_out' => null,
            'am_time_out' => null,
            'hours_rendered' => null,
            'clock_out_location' => null,
        ]);

        audit_log($student->id, 'dtr_undo_clock_out', [
            'log_id' => $log->id,
            'date' => optional($log->date)->toDateString(),
        ]);

        return $log->fresh();
    }

    public function overtimePromptFor(AttendanceLog $log): ?array
    {
        if (! $log->clock_out || $log->status === 'rejected') {
            return null;
        }

        $existing = OvertimeEntry::query()
            ->where('attendance_log_id', $log->id)
            ->whereIn('status', ['pending', 'approved', 'rejected', 'declined'])
            ->latest('id')
            ->first();

        if ($existing) {
            return null;
        }

        $excess = $this->detectExcessMinutes($log);
        if ($excess <= 0) {
            return null;
        }

        return [
            'attendance_log_id' => $log->id,
            'excess_minutes' => $excess,
        ];
    }

    public function decideOvertime(AttendanceLog $log, User $student, bool $accept, ?string $reason = null): ?OvertimeEntry
    {
        if ((int) $log->internship->student_id !== (int) $student->id) {
            abort(403, 'You may only decide overtime for your own attendance.');
        }

        $existing = OvertimeEntry::query()
            ->where('attendance_log_id', $log->id)
            ->whereIn('status', ['pending', 'approved', 'rejected', 'declined'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'overtime' => 'An overtime decision has already been recorded for this day.',
            ]);
        }

        $excess = $this->detectExcessMinutes($log);
        if ($excess <= 0) {
            throw ValidationException::withMessages([
                'overtime' => 'No excess time was detected against your Active Schedule.',
            ]);
        }

        $originalState = [
            'clock_in' => $this->timeString($log->clock_in),
            'clock_out' => $this->timeString($log->clock_out),
            'hours_rendered' => $log->hours_rendered,
            'overtime_hours' => $log->overtime_hours,
        ];

        $entry = OvertimeEntry::create([
            'internship_id' => $log->internship_id,
            'student_id' => $student->id,
            'attendance_log_id' => $log->id,
            'excess_minutes' => $excess,
            'status' => $accept ? 'pending' : 'declined',
            'original_hours_rendered' => $log->hours_rendered,
            'original_overtime_hours' => $log->overtime_hours ?: 0,
            'detected_clock_out' => $this->timeString($log->clock_out),
            'reason' => $reason,
        ]);

        $this->writeAudit($entry, $accept ? 'opted_in' : 'opted_out', $student, $accept ? null : 'declined', [
            'original_state' => $originalState,
            'requested_values' => [
                'excess_minutes' => $excess,
                'detected_clock_out' => $this->timeString($log->clock_out),
            ],
        ]);

        if ($accept && $log->internship->supervisor_id) {
            Notification::notify(
                (int) $log->internship->supervisor_id,
                'attendance_overtime_pending',
                'Overtime entry pending approval',
                $this->studentName($log->internship).' requested overtime ('.$this->formatMinutes($excess).') for '.$log->date->toDateString().'.',
                '/supervisor/attendance-validation',
                ['overtime_entry_id' => $entry->id]
            );
        }

        audit_log($student->id, $accept ? 'dtr_overtime_opt_in' : 'dtr_overtime_opt_out', [
            'log_id' => $log->id,
            'entry_id' => $entry->id,
            'excess_minutes' => $excess,
        ]);

        return $entry;
    }

    public function reviewOvertime(OvertimeEntry $entry, User $supervisor, string $action, ?string $remarks = null): OvertimeEntry
    {
        if ($entry->status !== 'pending') {
            throw ValidationException::withMessages([
                'overtime' => 'Only pending overtime entries can be reviewed.',
            ]);
        }

        $internship = $entry->internship;
        if ((int) $internship->supervisor_id !== (int) $supervisor->id) {
            abort(403, 'You may only review overtime for your assigned interns.');
        }

        $log = $entry->attendanceLog;
        $originalState = $this->overtimeOriginalState($entry);

        if ($action === 'rejected') {
            $entry->update([
                'status' => 'rejected',
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
                'review_remarks' => $remarks,
            ]);

            $this->writeAudit($entry, 'supervisor_rejected', $supervisor, 'rejected', [
                'original_state' => $originalState,
                'requested_values' => ['excess_minutes' => $entry->excess_minutes],
                'remarks' => $remarks,
                'final_applied_value' => [
                    'hours_rendered' => $log?->hours_rendered,
                    'overtime_hours' => $log?->overtime_hours,
                ],
            ]);

            Notification::notify(
                (int) $internship->student_id,
                'attendance_overtime_rejected',
                'Overtime request rejected',
                'Your overtime request for '.$log?->date?->toDateString().' was rejected.'.($remarks ? ' Reason: '.$remarks : ''),
                '/student/attendance',
                ['overtime_entry_id' => $entry->id]
            );

            audit_log($supervisor->id, 'dtr_overtime_rejected', ['entry_id' => $entry->id]);

            return $entry->fresh();
        }

        $extraHours = round($entry->excess_minutes / 60, 2);
        $newHours = round(((float) ($log->hours_rendered ?? 0)) + $extraHours, 2);
        $newOt = round(((float) ($log->overtime_hours ?? 0)) + $extraHours, 2);

        $log->update([
            'hours_rendered' => $newHours,
            'overtime_hours' => $newOt,
        ]);

        if ($log->status === 'validated') {
            if ($log->placement_id) {
                $log->placement?->refreshAccumulatedHours();
            }
            $internship->refreshTotalHours();
        }

        $entry->update([
            'status' => 'approved',
            'reviewed_by' => $supervisor->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
            'applied_hours_rendered' => $newHours,
            'applied_overtime_hours' => $newOt,
        ]);

        $this->writeAudit($entry, 'supervisor_approved', $supervisor, 'approved', [
            'original_state' => $originalState,
            'requested_values' => ['excess_minutes' => $entry->excess_minutes],
            'remarks' => $remarks,
            'final_applied_value' => [
                'hours_rendered' => $newHours,
                'overtime_hours' => $newOt,
            ],
        ]);

        Notification::notify(
            (int) $internship->student_id,
            'attendance_overtime_approved',
            'Overtime request approved',
            'Your overtime ('.$this->formatMinutes($entry->excess_minutes).') for '.$log->date->toDateString().' was approved and added to that day’s DTR total.',
            '/student/attendance',
            ['overtime_entry_id' => $entry->id]
        );

        audit_log($supervisor->id, 'dtr_overtime_approved', ['entry_id' => $entry->id]);

        return $entry->fresh(['attendanceLog']);
    }

    public function submitCorrection(
        Internship $internship,
        User $student,
        string $date,
        ?string $requestedClockIn,
        ?string $requestedClockOut,
        ?string $reason
    ): AttendanceCorrectionRequest {
        $day = Carbon::parse($date)->startOfDay();
        $today = now()->startOfDay();
        $minDate = $today->copy()->subDays(self::CORRECTION_MAX_DAYS);

        if ($day->gte($today)) {
            throw ValidationException::withMessages([
                'date' => 'Correction requests can only be filed for past days.',
            ]);
        }

        if ($day->lt($minDate)) {
            throw ValidationException::withMessages([
                'date' => 'Correction requests must be filed within '.self::CORRECTION_MAX_DAYS.' days.',
            ]);
        }

        if ($internship->start_date && $day->lt(Carbon::parse($internship->start_date)->startOfDay())) {
            throw ValidationException::withMessages([
                'date' => 'That date is before your internship start date.',
            ]);
        }

        $in = $requestedClockIn ? $this->normalizeTime($requestedClockIn) : null;
        $out = $requestedClockOut ? $this->normalizeTime($requestedClockOut) : null;

        if (! $in && ! $out) {
            throw ValidationException::withMessages([
                'requested_clock_in' => 'Enter the clock-in and/or clock-out time you believe is correct.',
            ]);
        }

        $log = $internship->attendance()->whereDate('date', $day->toDateString())->first();

        if ($log && $log->clock_in && $log->clock_out) {
            throw ValidationException::withMessages([
                'date' => 'That day already has a complete clock-in and clock-out. Correction requests are only for missing or incomplete entries.',
            ]);
        }

        if (! $log && (! $in || ! $out)) {
            throw ValidationException::withMessages([
                'requested_clock_out' => 'Missing days require both a clock-in and a clock-out time.',
            ]);
        }

        $open = AttendanceCorrectionRequest::query()
            ->where('internship_id', $internship->id)
            ->whereDate('date', $day->toDateString())
            ->whereIn('status', [
                AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR,
                AttendanceCorrectionRequest::STATUS_PENDING_FACULTY,
            ])
            ->exists();

        if ($open) {
            throw ValidationException::withMessages([
                'date' => 'A correction request for this date is already pending review.',
            ]);
        }

        $originalIn = $log ? $this->timeString($log->clock_in) : null;
        $originalOut = $log ? $this->timeString($log->clock_out) : null;

        $request = AttendanceCorrectionRequest::create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'attendance_log_id' => $log?->id,
            'date' => $day->toDateString(),
            'original_clock_in' => $originalIn,
            'original_clock_out' => $originalOut,
            'original_hours_rendered' => $log?->hours_rendered,
            'requested_clock_in' => $in ?: $originalIn,
            'requested_clock_out' => $out ?: $originalOut,
            'reason' => $reason,
            'status' => AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR,
        ]);

        $this->writeAudit($request, 'submitted', $student, null, [
            'original_state' => [
                'clock_in' => $originalIn,
                'clock_out' => $originalOut,
                'hours_rendered' => $log?->hours_rendered,
            ],
            'requested_values' => [
                'clock_in' => $request->requested_clock_in,
                'clock_out' => $request->requested_clock_out,
                'reason' => $reason,
            ],
        ]);

        if ($internship->supervisor_id) {
            Notification::notify(
                (int) $internship->supervisor_id,
                'attendance_correction_pending',
                'DTR correction request pending',
                $this->studentName($internship).' submitted a correction request for '.$day->toDateString().'.',
                '/supervisor/attendance-validation',
                ['correction_request_id' => $request->id]
            );
        }

        audit_log($student->id, 'dtr_correction_submitted', [
            'request_id' => $request->id,
            'date' => $day->toDateString(),
        ]);

        return $request;
    }

    public function reviewCorrectionAsSupervisor(AttendanceCorrectionRequest $request, User $supervisor, string $action, ?string $remarks = null): AttendanceCorrectionRequest
    {
        $internship = $request->internship;
        if ((int) $internship->supervisor_id !== (int) $supervisor->id) {
            abort(403, 'You may only review corrections for your assigned interns.');
        }

        if ($request->status !== AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR) {
            throw ValidationException::withMessages([
                'correction' => 'This request is not awaiting supervisor review.',
            ]);
        }

        if ($action === 'rejected') {
            return $this->rejectCorrection($request, $supervisor, 'supervisor', $remarks);
        }

        if (! $internship->faculty_id) {
            throw ValidationException::withMessages([
                'correction' => 'No faculty is assigned to this intern, so the request cannot proceed.',
            ]);
        }

        $request->update([
            'status' => AttendanceCorrectionRequest::STATUS_PENDING_FACULTY,
            'supervisor_reviewed_by' => $supervisor->id,
            'supervisor_reviewed_at' => now(),
            'supervisor_remarks' => $remarks,
            'supervisor_decision' => 'approved',
        ]);

        $this->writeAudit($request, 'supervisor_approved', $supervisor, 'approved', [
            'original_state' => $this->correctionOriginalState($request),
            'requested_values' => $this->correctionRequestedValues($request),
            'remarks' => $remarks,
        ]);

        Notification::notify(
            (int) $internship->faculty_id,
            'attendance_correction_faculty',
            'DTR correction awaiting faculty review',
            $this->studentName($internship).' — correction for '.$request->date->toDateString().' was approved by the supervisor and needs faculty review.',
            '/faculty/assigned-students',
            ['correction_request_id' => $request->id]
        );

        Notification::notify(
            (int) $internship->student_id,
            'attendance_correction_supervisor_approved',
            'Correction pending faculty review',
            'Your supervisor approved your DTR correction for '.$request->date->toDateString().'. It is now awaiting faculty review.',
            '/student/attendance',
            ['correction_request_id' => $request->id]
        );

        audit_log($supervisor->id, 'dtr_correction_supervisor_approved', ['request_id' => $request->id]);

        return $request->fresh();
    }

    public function reviewCorrectionAsFaculty(AttendanceCorrectionRequest $request, User $faculty, string $action, ?string $remarks = null): AttendanceCorrectionRequest
    {
        $internship = $request->internship;
        \App\Support\DepartmentScope::abortUnlessInternshipInDepartment($faculty, $internship);

        if ((int) $internship->faculty_id !== (int) $faculty->id) {
            abort(403, 'You may only review corrections for your assigned students.');
        }

        if ($request->status === AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR) {
            throw ValidationException::withMessages([
                'correction' => 'Faculty cannot review a correction request before the supervisor has approved it.',
            ]);
        }

        if ($request->status !== AttendanceCorrectionRequest::STATUS_PENDING_FACULTY) {
            throw ValidationException::withMessages([
                'correction' => 'This request is not awaiting faculty review.',
            ]);
        }

        if ($action === 'rejected') {
            return $this->rejectCorrection($request, $faculty, 'faculty', $remarks);
        }

        $applied = $this->applyCorrection($request);

        $request->update([
            'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
            'faculty_reviewed_by' => $faculty->id,
            'faculty_reviewed_at' => now(),
            'faculty_remarks' => $remarks,
            'faculty_decision' => 'approved',
            'attendance_log_id' => $applied->id,
            'applied_clock_in' => $this->timeString($applied->clock_in),
            'applied_clock_out' => $this->timeString($applied->clock_out),
            'applied_hours_rendered' => $applied->hours_rendered,
            'applied_at' => now(),
        ]);

        $this->writeAudit($request, 'faculty_approved_applied', $faculty, 'approved', [
            'original_state' => $this->correctionOriginalState($request),
            'requested_values' => $this->correctionRequestedValues($request),
            'remarks' => $remarks,
            'final_applied_value' => [
                'clock_in' => $this->timeString($applied->clock_in),
                'clock_out' => $this->timeString($applied->clock_out),
                'hours_rendered' => $applied->hours_rendered,
                'attendance_log_id' => $applied->id,
            ],
        ]);

        Notification::notify(
            (int) $internship->student_id,
            'attendance_correction_approved',
            'DTR correction approved',
            'Your DTR correction for '.$request->date->toDateString().' was approved by faculty and applied to the official record.',
            '/student/attendance',
            ['correction_request_id' => $request->id]
        );

        audit_log($faculty->id, 'dtr_correction_approved', ['request_id' => $request->id]);

        return $request->fresh();
    }

    public function incompleteDays(Internship $internship): array
    {
        $today = now()->startOfDay();
        $from = $today->copy()->subDays(self::CORRECTION_MAX_DAYS);
        $days = [];

        $openByDate = AttendanceCorrectionRequest::query()
            ->where('internship_id', $internship->id)
            ->whereIn('status', [
                AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR,
                AttendanceCorrectionRequest::STATUS_PENDING_FACULTY,
            ])
            ->get()
            ->keyBy(fn (AttendanceCorrectionRequest $r) => $r->date->toDateString());

        $approvedByDate = AttendanceCorrectionRequest::query()
            ->where('internship_id', $internship->id)
            ->where('status', AttendanceCorrectionRequest::STATUS_APPROVED)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        for ($d = $from->copy(); $d->lt($today); $d->addDay()) {
            $date = $d->toDateString();
            if ($internship->start_date && $d->lt(Carbon::parse($internship->start_date)->startOfDay())) {
                continue;
            }

            $log = $internship->attendance()->whereDate('date', $date)->first();
            $open = $openByDate->get($date);
            $reason = null;

            if ($log && ! $log->clock_out) {
                $reason = 'incomplete';
            } elseif (! $log && ! in_array($date, $approvedByDate, true)) {
                $reason = 'missing';
            }

            if (! $reason && ! $open) {
                continue;
            }

            $days[] = [
                'date' => $date,
                'reason' => $reason ?: 'pending_correction',
                'attendance_log_id' => $log?->id,
                'correction_status' => $open?->status,
                'correction_status_label' => $open?->statusLabel(),
            ];
        }

        return $days;
    }

    public function decorateLogs(Collection $logs): Collection
    {
        if ($logs->isEmpty()) {
            return $logs;
        }

        $internshipIds = $logs->pluck('internship_id')->unique()->values();
        $logIds = $logs->pluck('id');
        $dates = $logs->map(fn (AttendanceLog $log) => Carbon::parse($log->date)->toDateString())->unique()->values();

        $overtimes = OvertimeEntry::query()
            ->whereIn('attendance_log_id', $logIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('attendance_log_id');

        $corrections = AttendanceCorrectionRequest::query()
            ->whereIn('internship_id', $internshipIds)
            ->whereIn('date', $dates)
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AttendanceCorrectionRequest $r) => $r->internship_id.'|'.$r->date->toDateString());

        $schedules = WorkSchedule::query()
            ->whereIn('internship_id', $internshipIds)
            ->whereIn('status', ['approved', 'superseded'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('internship_id');

        return $logs->map(function (AttendanceLog $log) use ($overtimes, $corrections, $schedules) {
            $date = Carbon::parse($log->date)->toDateString();
            $schedule = $this->pickScheduleFrom($schedules->get($log->internship_id, collect()), $date);
            $ot = $overtimes->get($log->id, collect())->first(fn (OvertimeEntry $e) => $e->status !== 'declined');
            $correction = $corrections->get($log->internship_id.'|'.$date, collect())->first();

            $scheduledHours = $schedule
                ? round($this->minutesBetween(
                    $this->combineDateAndTime($date, $schedule->start_time),
                    $this->combineDateAndTime($date, $schedule->end_time)
                ) / 60, 2)
                : null;

            $actualHours = ($log->clock_in && $log->clock_out)
                ? round($this->minutesBetween(
                    $this->combineDateAndTime($date, $log->clock_in),
                    $this->combineDateAndTime($date, $log->clock_out)
                ) / 60, 2)
                : null;

            $otStatus = match ($ot?->status) {
                'pending' => 'pending',
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'none',
            };

            $corrStatus = $correction?->status;
            $corrLabel = $correction?->statusLabel();

            $log->setAttribute('scheduled_hours', $scheduledHours);
            $log->setAttribute('actual_hours', $actualHours);
            $log->setAttribute('overtime_status', $otStatus);
            $log->setAttribute('overtime_minutes', $ot && $otStatus !== 'none' ? $ot->excess_minutes : null);
            $log->setAttribute('correction_status', $corrStatus);
            $log->setAttribute('correction_status_label', $corrLabel);
            $log->setAttribute('dtr_entry_kind', $this->entryKind($otStatus, $corrStatus));
            $log->setAttribute('active_schedule', $schedule ? [
                'start_time' => $this->timeString($schedule->start_time),
                'end_time' => $this->timeString($schedule->end_time),
            ] : null);

            return $log;
        });
    }

    public function serializeSchedule(?WorkSchedule $schedule): ?array
    {
        if (! $schedule) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'start_time' => $this->timeString($schedule->start_time),
            'end_time' => $this->timeString($schedule->end_time),
            'status' => $schedule->status,
            'effective_from' => optional($schedule->effective_from)?->toDateString(),
            'effective_to' => optional($schedule->effective_to)?->toDateString(),
            'review_remarks' => $schedule->review_remarks,
            'reviewed_at' => optional($schedule->reviewed_at)?->toIso8601String(),
        ];
    }

    public function auditsFor(string $type, int $id): Collection
    {
        $class = match ($type) {
            'overtime', OvertimeEntry::class => OvertimeEntry::class,
            'correction', AttendanceCorrectionRequest::class => AttendanceCorrectionRequest::class,
            default => $type,
        };

        return DtrRequestAudit::query()
            ->with('actor')
            ->where('auditable_type', $class)
            ->where('auditable_id', $id)
            ->orderBy('id')
            ->get();
    }

    private function rejectCorrection(AttendanceCorrectionRequest $request, User $actor, string $role, ?string $remarks): AttendanceCorrectionRequest
    {
        $payload = [
            'status' => AttendanceCorrectionRequest::STATUS_REJECTED,
            'rejected_by_role' => $role,
            'rejected_at' => now(),
        ];

        if ($role === 'supervisor') {
            $payload['supervisor_reviewed_by'] = $actor->id;
            $payload['supervisor_reviewed_at'] = now();
            $payload['supervisor_remarks'] = $remarks;
            $payload['supervisor_decision'] = 'rejected';
        } else {
            $payload['faculty_reviewed_by'] = $actor->id;
            $payload['faculty_reviewed_at'] = now();
            $payload['faculty_remarks'] = $remarks;
            $payload['faculty_decision'] = 'rejected';
        }

        $request->update($payload);

        $this->writeAudit($request, $role.'_rejected', $actor, 'rejected', [
            'original_state' => $this->correctionOriginalState($request),
            'requested_values' => $this->correctionRequestedValues($request),
            'remarks' => $remarks,
            'final_applied_value' => [
                'clock_in' => $request->original_clock_in,
                'clock_out' => $request->original_clock_out,
                'hours_rendered' => $request->original_hours_rendered,
                'applied' => false,
            ],
        ]);

        $who = $role === 'supervisor' ? 'supervisor' : 'faculty';
        Notification::notify(
            (int) $request->internship->student_id,
            'attendance_correction_rejected',
            'DTR correction rejected',
            'Your DTR correction for '.$request->date->toDateString().' was rejected by your '.$who.'. The official record was not changed.'.($remarks ? ' Reason: '.$remarks : ''),
            '/student/attendance',
            ['correction_request_id' => $request->id]
        );

        audit_log($actor->id, 'dtr_correction_rejected', [
            'request_id' => $request->id,
            'role' => $role,
        ]);

        return $request->fresh();
    }

    private function applyCorrection(AttendanceCorrectionRequest $request): AttendanceLog
    {
        $internship = $request->internship;
        $date = $request->date->toDateString();

        $log = $request->attendance_log_id
            ? AttendanceLog::withTrashed()->find($request->attendance_log_id)
            : AttendanceLog::withTrashed()
                ->where('internship_id', $internship->id)
                ->whereDate('date', $date)
                ->first();

        if ($log?->trashed()) {
            $log->restore();
        }

        $clockIn = $request->requested_clock_in;
        $clockOut = $request->requested_clock_out;
        $hours = $this->hoursForTimes($internship, $date, $clockIn, $clockOut);

        if (! $log) {
            $log = $internship->attendance()->create([
                'date' => $date,
                'placement_id' => $internship->current_placement_id,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'am_time_in' => $clockIn,
                'am_time_out' => $clockOut,
                'hours_rendered' => $hours,
                'overtime_hours' => 0,
                'status' => 'pending',
            ]);
        } else {
            $log->update([
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
                'am_time_in' => $clockIn,
                'am_time_out' => $clockOut,
                'hours_rendered' => $hours,
            ]);
        }

        return $log->fresh();
    }

    private function hoursForTimes(Internship $internship, string $date, ?string $clockIn, ?string $clockOut): ?float
    {
        if (! $clockIn || ! $clockOut) {
            return null;
        }

        $in = $this->combineDateAndTime($date, $clockIn);
        $out = $this->combineDateAndTime($date, $clockOut);
        $schedule = $this->activeScheduleFor($internship, $date);

        if (! $schedule) {
            return round($this->minutesBetween($in, $out) / 60, 2);
        }

        $schedStart = $this->combineDateAndTime($date, $schedule->start_time);
        $schedEnd = $this->combineDateAndTime($date, $schedule->end_time);
        $overlapStart = $in->greaterThan($schedStart) ? $in : $schedStart;
        $overlapEnd = $out->lessThan($schedEnd) ? $out : $schedEnd;
        $minutes = $overlapEnd->greaterThan($overlapStart)
            ? $this->minutesBetween($overlapStart, $overlapEnd)
            : 0;

        return round($minutes / 60, 2);
    }

    private function detectExcessMinutes(AttendanceLog $log): int
    {
        if (! $log->clock_in || ! $log->clock_out) {
            return 0;
        }

        $schedule = $this->activeScheduleFor($log->internship, $log->date);
        if (! $schedule) {
            return 0;
        }

        $clockOut = $this->combineDateAndTime($log->date, $log->clock_out);
        $schedEnd = $this->combineDateAndTime($log->date, $schedule->end_time);

        if (! $clockOut->greaterThan($schedEnd)) {
            return 0;
        }

        return $this->minutesBetween($schedEnd, $clockOut);
    }

    private function pickScheduleFrom(Collection $schedules, string $date): ?WorkSchedule
    {
        return $schedules->first(function (WorkSchedule $schedule) use ($date) {
            if (! $schedule->effective_from) {
                return false;
            }
            if ($schedule->effective_from->toDateString() > $date) {
                return false;
            }
            if ($schedule->effective_to && $schedule->effective_to->toDateString() < $date) {
                return false;
            }

            return in_array($schedule->status, ['approved', 'superseded'], true);
        });
    }

    private function entryKind(string $otStatus, ?string $corrStatus): string
    {
        if ($corrStatus === AttendanceCorrectionRequest::STATUS_PENDING_SUPERVISOR
            || $corrStatus === AttendanceCorrectionRequest::STATUS_PENDING_FACULTY) {
            return 'pending_correction';
        }
        if ($corrStatus === AttendanceCorrectionRequest::STATUS_APPROVED) {
            return 'correction_approved';
        }
        if ($corrStatus === AttendanceCorrectionRequest::STATUS_REJECTED) {
            return 'correction_rejected';
        }
        if ($otStatus === 'pending') {
            return 'overtime_pending';
        }
        if ($otStatus === 'approved') {
            return 'overtime_approved';
        }
        if ($otStatus === 'rejected') {
            return 'overtime_rejected';
        }

        return 'normal';
    }

    private function writeAudit(
        OvertimeEntry|AttendanceCorrectionRequest $auditable,
        string $event,
        ?User $actor,
        ?string $decision,
        array $payload
    ): DtrRequestAudit {
        $internshipId = (int) $auditable->internship_id;
        $studentId = (int) ($auditable->student_id ?? $auditable->internship?->student_id);

        $audit = DtrRequestAudit::create([
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'internship_id' => $internshipId,
            'student_id' => $studentId,
            'event' => $event,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'original_state' => $payload['original_state'] ?? null,
            'requested_values' => $payload['requested_values'] ?? null,
            'decision' => $decision,
            'remarks' => $payload['remarks'] ?? null,
            'final_applied_value' => $payload['final_applied_value'] ?? null,
            'created_at' => now(),
        ]);

        audit_log($actor?->id, 'dtr_audit_'.$event, [
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'decision' => $decision,
        ]);

        return $audit;
    }

    private function overtimeOriginalState(OvertimeEntry $entry): array
    {
        return [
            'hours_rendered' => $entry->original_hours_rendered,
            'overtime_hours' => $entry->original_overtime_hours,
            'detected_clock_out' => $this->timeString($entry->detected_clock_out),
            'excess_minutes' => $entry->excess_minutes,
        ];
    }

    private function correctionOriginalState(AttendanceCorrectionRequest $request): array
    {
        return [
            'clock_in' => $this->timeString($request->original_clock_in),
            'clock_out' => $this->timeString($request->original_clock_out),
            'hours_rendered' => $request->original_hours_rendered,
        ];
    }

    private function correctionRequestedValues(AttendanceCorrectionRequest $request): array
    {
        return [
            'clock_in' => $this->timeString($request->requested_clock_in),
            'clock_out' => $this->timeString($request->requested_clock_out),
            'reason' => $request->reason,
        ];
    }

    public function combineDateAndTime(Carbon|string $date, mixed $time): Carbon
    {
        $dateStr = Carbon::parse($date)->toDateString();
        $timeStr = $this->normalizeTime((string) $time);

        return Carbon::parse($dateStr.' '.$timeStr);
    }

    public function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return Carbon::parse($time)->format('H:i:s');
    }

    public function timeString(mixed $time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        return $this->normalizeTime((string) $time);
    }

    public function minutesBetween(Carbon $from, Carbon $to): int
    {
        return (int) max(0, round($from->diffInMinutes($to, false)));
    }

    private function formatTimeRange(mixed $start, mixed $end): string
    {
        return substr($this->timeString($start) ?? '', 0, 5).'–'.substr($this->timeString($end) ?? '', 0, 5);
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($hours > 0 && $mins > 0) {
            return $hours.'h '.$mins.'m';
        }
        if ($hours > 0) {
            return $hours.'h';
        }

        return $mins.'m';
    }

    private function studentName(Internship $internship): string
    {
        $internship->loadMissing('student.studentProfile');
        $profile = $internship->student?->studentProfile;
        if ($profile) {
            $name = trim(($profile->first_name ?? '').' '.($profile->last_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return $internship->student?->username ?? 'A student';
    }
}
