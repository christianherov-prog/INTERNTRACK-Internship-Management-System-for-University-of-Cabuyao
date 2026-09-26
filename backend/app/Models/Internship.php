<?php

namespace App\Models;

use App\Services\FacultySectionAssignmentService;
use App\Services\InternshipPlacementService;
use App\Support\DepartmentScope;
use App\Support\InternshipStatuses;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

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
        'evaluation_period_status', 'evaluation_period_approved_by', 'evaluation_period_approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'expected_end_date' => 'date',
        'absorbed_at' => 'date',
        'absorption_recorded_at' => 'datetime',
        'student_declared_at' => 'datetime',
        'student_declared_hired' => 'boolean',
        'evaluation_period_approved_at' => 'datetime',
        'total_hours_rendered' => 'decimal:2',
        'final_grade' => 'decimal:2',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * True when an Industry / HTE supervisor is assigned on the internship
     * or on the current placement row (canonical approved-supervisor check).
     */
    public function hasApprovedHteSupervisor(): bool
    {
        if ($this->supervisor_id) {
            return true;
        }

        if ($this->relationLoaded('currentPlacement')) {
            return (bool) $this->currentPlacement?->supervisor_id;
        }

        if ($this->current_placement_id && \App\Support\SchemaCache::hasTable('internship_placements')) {
            return (bool) $this->currentPlacement()->value('supervisor_id');
        }

        return false;
    }

    /**
     * Human-readable reason attendance/DTR is locked, or null when unlocked.
     * Distinguishes fresh "not yet placed" students from "supervisor pending approval".
     */
    public function attendanceLockReason(): ?string
    {
        if ($this->hasApprovedHteSupervisor()) {
            return null;
        }

        $status = InternshipStatuses::normalize($this->status);
        $notPlaced = in_array($status, ['pending_placement', 'pending'], true)
            || ! $this->company_id;

        if ($notPlaced) {
            return 'Attendance is unavailable until you complete company placement and an Industry Supervisor is assigned.';
        }

        return 'Attendance tracking is locked until your HTE Supervisor is approved.';
    }

    public const ATTENDANCE_COMPLETED_MESSAGE = 'You have completed your required internship hours. New attendance entries are no longer available.';

    /**
     * Completion is the official internship status set through the staff
     * status workflow — not a derived hours check. Reaching the target hours
     * alone leaves an internship open until it is formally completed.
     */
    public function isCompleted(): bool
    {
        return InternshipStatuses::normalize($this->status) === 'completed';
    }

    /**
     * Reason new attendance activity (clock in/out, break, resume, schedule and
     * correction requests) is refused, or null when allowed. History stays
     * readable for completed internships; only new entries are closed.
     */
    public function newAttendanceLockReason(): ?string
    {
        if ($this->isCompleted()) {
            return self::ATTENDANCE_COMPLETED_MESSAGE;
        }

        return $this->attendanceLockReason();
    }

    public function faculty()
    {
        return $this->belongsTo(User::class, 'faculty_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

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

    public function journalDeadlines()
    {
        return $this->hasMany(JournalDeadline::class);
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

    public function evaluationPeriodIsApproved(): bool
    {
        return ($this->evaluation_period_status ?: 'pending') === 'approved';
    }

    public function abortUnlessEvaluationPeriodApproved(): void
    {
        if (! $this->evaluationPeriodIsApproved()) {
            abort(403, 'Waiting for Faculty approval of the evaluation period.');
        }
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
        if ($this->target_hours <= 0) {
            return 0;
        }

        return min(100, round(($this->total_hours_rendered / $this->target_hours) * 100, 1));
    }

    public function computeTotalHours(): float
    {
        $attendanceHours = (float) $this->attendance()
            ->where('status', 'validated')
            ->sum('hours_rendered');

        $placementHours = 0.0;
        if (\App\Support\SchemaCache::hasTable('internship_placements')) {
            $placementHours = (float) $this->placements()->sum('accumulated_hours');
        }

        if ($attendanceHours > 0) {
            return $attendanceHours;
        }

        if ($placementHours > 0) {
            return $placementHours;
        }

        return (float) ($this->total_hours_rendered ?? 0);
    }

    public function refreshTotalHours(): void
    {
        if (\App\Support\SchemaCache::hasTable('internship_placements')) {
            $this->loadMissing('placements');

            foreach ($this->placements as $placement) {
                $placement->refreshAccumulatedHours();
            }
        }

        $total = $this->computeTotalHours();

        if ((float) $this->total_hours_rendered !== $total) {
            $this->update(['total_hours_rendered' => $total]);
        }
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────
    public function scopeInDepartment($query)
    {
        return DepartmentScope::constrainInternships($query, auth()->user());
    }

    protected static function booted(): void
    {
        $resolveFaculty = function (Internship $internship) {
            if (empty($internship->faculty_id) && $internship->student_id) {
                $user = User::with('studentProfile')->find($internship->student_id);
                if ($user?->studentProfile) {
                    $facultyId = app(FacultySectionAssignmentService::class)->resolveFacultyForProfile($user->studentProfile)?->id;
                    if ($facultyId) {
                        $internship->faculty_id = $facultyId;
                    }
                }
            }
        };

        static::creating($resolveFaculty);
        static::updating($resolveFaculty);
        static::created(function (Internship $internship) {
            InternshipPlacementService::ensurePlacements($internship);
        });
    }
}
