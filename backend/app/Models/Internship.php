<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Internship extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'student_id', 'company_id', 'supervisor_id', 'current_placement_id', 'faculty_id', 'coordinator_id',
        'school_year', 'semester', 'term', 'program',
        'target_hours', 'total_hours_rendered', 'status', 'status_reason',
        'start_date', 'end_date', 'expected_end_date',
        'termination_reason', 'final_grade', 'final_remarks',
        'absorption_status', 'absorbed_at', 'job_title', 'absorption_notes',
        'absorption_recorded_by', 'absorption_recorded_at', 'absorption_recorded_by_role',
        'student_declared_hired', 'student_declared_at', 'student_declaration_notes',
        'certificate_eligible', 'certificate_issued_at',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'end_date'               => 'date',
        'expected_end_date'      => 'date',
        'absorbed_at'            => 'date',
        'absorption_recorded_at' => 'datetime',
        'student_declared_at'    => 'datetime',
        'student_declared_hired'  => 'boolean',
        'certificate_eligible'    => 'boolean',
        'certificate_issued_at'   => 'datetime',
        'total_hours_rendered'    => 'decimal:2',
        'final_grade'             => 'decimal:2',
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

    public function workSchedules()
    {
        return $this->hasMany(WorkSchedule::class);
    }

    public function overtimeEntries()
    {
        return $this->hasMany(OvertimeEntry::class);
    }

    public function correctionRequests()
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
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
        return $this->hasOne(StudentPortfolio::class);
    }

    public function placements()
    {
        return $this->hasMany(InternshipPlacement::class);
    }

    public function currentPlacement()
    {
        return $this->belongsTo(InternshipPlacement::class, 'current_placement_id');
    }

    public function statusHistories()
    {

        return $this->hasMany(InternshipStatusHistory::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * User IDs linked on this internship row (source of truth for messaging).
     * Includes student, industry supervisor, faculty, and coordinator when set.
     * Does not include director — directors have no FK on internships.
     *
     * @return list<int>
     */
    public function participantUserIds(): array
    {
        return collect([
            $this->student_id,
            $this->supervisor_id,
            $this->faculty_id,
            $this->coordinator_id,
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isParticipant(int $userId): bool
    {
        return in_array($userId, $this->participantUserIds(), true);
    }

    // ─── Computed Helpers ──────────────────────────────────────────────────────
    public function getProgressPercentAttribute(): float
    {
        if ($this->target_hours <= 0) return 0;
        return min(100, round(($this->total_hours_rendered / $this->target_hours) * 100, 1));
    }

    public function refreshTotalHours(): void
    {
        // Sum hours from all placements, or fallback to attendance logs if no placements
        $placementHours = $this->placements()->sum('accumulated_hours');
        
        if ($placementHours > 0) {
            $total = $placementHours;
        } else {
            // Legacy: sum directly from attendance logs (for internships created before placement model)
            $total = $this->attendance()
                ->where('status', 'validated')
                ->sum('hours_rendered');
        }
        
        $this->update(['total_hours_rendered' => $total]);
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────
    public function scopeInDepartment($query)
    {
        return \App\Support\DepartmentScope::constrainInternships($query, auth()->user());
    }

    protected static function booted(): void
    {
        $resolveFaculty = function (Internship $internship) {
            if (empty($internship->faculty_id) && $internship->student_id) {
                $user = \App\Models\User::with('studentProfile')->find($internship->student_id);
                if ($user?->studentProfile) {
                    $facultyId = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($user->studentProfile)?->id;
                    if ($facultyId) {
                        $internship->faculty_id = $facultyId;
                    }
                }
            }
        };

        static::creating($resolveFaculty);
        static::updating($resolveFaculty);
        static::created(function (Internship $internship) {
            \App\Services\InternshipPlacementService::ensurePlacements($internship);
        });
    }
}
