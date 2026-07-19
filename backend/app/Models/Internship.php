<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Internship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id', 'company_id', 'supervisor_id', 'faculty_id', 'coordinator_id',
        'academic_year', 'semester', 'term', 'program',
        'target_hours', 'total_hours_rendered', 'status', 'status_reason',
        'start_date', 'end_date', 'expected_end_date',
        'termination_reason', 'final_grade', 'final_remarks',
    ];

    protected $casts = [
        'start_date'         => 'date',
        'end_date'           => 'date',
        'expected_end_date'  => 'date',
        'total_hours_rendered' => 'decimal:2',
        'final_grade'        => 'decimal:2',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────
    public function student()    { return $this->belongsTo(User::class, 'student_id'); }
    public function company()    { return $this->belongsTo(Company::class); }
    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_id'); }
    public function faculty()    { return $this->belongsTo(User::class, 'faculty_id'); }
    public function coordinator(){ return $this->belongsTo(User::class, 'coordinator_id'); }

    public function attendance()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function journals()
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function portfolio()
    {
        return $this->hasOne(Portfolio::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(InternshipStatusHistory::class);
    }

    // ─── Computed Helpers ──────────────────────────────────────────────────────
    public function getProgressPercentAttribute(): float
    {
        if ($this->target_hours <= 0) return 0;
        return min(100, round(($this->total_hours_rendered / $this->target_hours) * 100, 1));
    }

    public function refreshTotalHours(): void
    {
        $total = $this->attendance()
            ->where('status', 'validated')
            ->sum('hours_rendered');
        $this->update(['total_hours_rendered' => $total]);
    }
}
