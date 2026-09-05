<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    public const STATUS_PENDING_SUPERVISOR = 'pending_supervisor';
    public const STATUS_PENDING_FACULTY = 'pending_faculty';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'internship_id', 'student_id', 'attendance_log_id', 'date',
        'original_clock_in', 'original_clock_out', 'original_hours_rendered',
        'requested_clock_in', 'requested_clock_out', 'reason', 'status',
        'supervisor_reviewed_by', 'supervisor_reviewed_at', 'supervisor_remarks', 'supervisor_decision',
        'faculty_reviewed_by', 'faculty_reviewed_at', 'faculty_remarks', 'faculty_decision',
        'rejected_by_role', 'rejected_at',
        'applied_clock_in', 'applied_clock_out', 'applied_hours_rendered', 'applied_at',
    ];

    protected $casts = [
        'date' => 'date',
        'original_hours_rendered' => 'decimal:2',
        'applied_hours_rendered' => 'decimal:2',
        'supervisor_reviewed_at' => 'datetime',
        'faculty_reviewed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class);
    }

    public function supervisorReviewer()
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function facultyReviewer()
    {
        return $this->belongsTo(User::class, 'faculty_reviewed_by');
    }

    public function audits()
    {
        return $this->morphMany(DtrRequestAudit::class, 'auditable');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_SUPERVISOR => 'Pending Supervisor Review',
            self::STATUS_PENDING_FACULTY => 'Pending Faculty Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => $this->status,
        };
    }
}
