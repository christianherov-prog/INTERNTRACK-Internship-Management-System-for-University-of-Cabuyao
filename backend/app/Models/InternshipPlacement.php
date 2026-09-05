<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternshipPlacement extends Model
{
    protected $fillable = [
        'internship_id',
        'program_hte_requirement_id',
        'company_id',
        'supervisor_id',
        'sequence_order',
        'label',
        'required_hours',
        'accumulated_hours',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'required_hours' => 'decimal:2',
        'accumulated_hours' => 'decimal:2',
        'sequence_order' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function requirement()
    {
        return $this->belongsTo(ProgramHteRequirement::class, 'program_hte_requirement_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'placement_id');
    }

    // ─── Computed Helpers ──────────────────────────────────────────────────────

    public function getProgressPercentAttribute(): float
    {
        if ($this->required_hours <= 0) {
            return 0;
        }

        return min(100, round(($this->accumulated_hours / $this->required_hours) * 100, 1));
    }

    public function isComplete(): bool
    {
        return $this->accumulated_hours >= $this->required_hours;
    }

    public function refreshAccumulatedHours(): void
    {
        $total = $this->attendanceLogs()
            ->where('status', 'validated')
            ->sum('hours_rendered');

        $this->update(['accumulated_hours' => $total]);
    }
}
