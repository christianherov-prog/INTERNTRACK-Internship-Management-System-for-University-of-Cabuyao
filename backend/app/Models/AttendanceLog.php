<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'internship_id', 'placement_id', 'date',
        'clock_in', 'clock_out',
        'am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out',
        'hours_rendered', 'overtime_hours', 'status', 'remarks',
        'validated_by', 'validated_at',
        'clock_in_location', 'clock_out_location',
        'student_signature_path', 'student_signed_name', 'student_signed_at',
        'student_privacy_accepted_at',
        'hte_signature_path', 'hte_signed_name', 'hte_signed_at',
    ];

    protected $casts = [
        'date' => 'date',
        'validated_at' => 'datetime',
        'student_signed_at' => 'datetime',
        'student_privacy_accepted_at' => 'datetime',
        'hte_signed_at' => 'datetime',
        'hours_rendered' => 'decimal:2',
    ];

    protected $appends = ['student_prepared', 'hte_verified'];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function placement()
    {
        return $this->belongsTo(InternshipPlacement::class, 'placement_id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function getStudentPreparedAttribute(): bool
    {
        return (bool) ($this->student_signature_path && $this->student_signed_name && $this->student_privacy_accepted_at);
    }

    public function getHteVerifiedAttribute(): bool
    {
        return (bool) ($this->hte_signature_path && $this->hte_signed_name && $this->hte_signed_at);
    }
}
