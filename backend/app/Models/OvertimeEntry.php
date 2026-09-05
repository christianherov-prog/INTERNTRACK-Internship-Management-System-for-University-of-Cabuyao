<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OvertimeEntry extends Model
{
    protected $fillable = [
        'internship_id', 'student_id', 'attendance_log_id',
        'excess_minutes', 'status',
        'original_hours_rendered', 'original_overtime_hours',
        'detected_clock_out', 'reason',
        'reviewed_by', 'reviewed_at', 'review_remarks',
        'applied_hours_rendered', 'applied_overtime_hours',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'original_hours_rendered' => 'decimal:2',
        'original_overtime_hours' => 'decimal:2',
        'applied_hours_rendered' => 'decimal:2',
        'applied_overtime_hours' => 'decimal:2',
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

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function audits()
    {
        return $this->morphMany(DtrRequestAudit::class, 'auditable');
    }
}
