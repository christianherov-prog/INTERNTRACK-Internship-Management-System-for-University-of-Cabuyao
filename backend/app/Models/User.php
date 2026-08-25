<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'student_number', 'faculty_number', 'email', 'password', 'role', 'sex', 'is_active', 'must_change_password',
        'last_login_at', 'avatar_path', 'notification_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'     => 'boolean',
        'must_change_password' => 'boolean',
        'last_login_at' => 'datetime',
        'notification_preferences' => 'array',
    ];

    // ─── Profile Relationships ────────────────────────────────────────────────

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function facultyProfile()
    {
        return $this->hasOne(FacultyProfile::class);
    }

    public function supervisorProfile()
    {
        return $this->hasOne(SupervisorProfile::class);
    }

    // ─── Internship Relationships ─────────────────────────────────────────────

    /** Internship where this user is the student */
    public function internshipsAsStudent()
    {
        return $this->hasMany(Internship::class, 'student_id');
    }

    /** The current internship for this student (includes suspended/deferred). */
    public function activeInternship()
    {
        return $this->hasOne(Internship::class, 'student_id')
                    ->whereIn('status', \App\Support\InternshipStatuses::currentRelation())
                    ->latest();
    }

    /** Internships where this user is the supervisor */
    public function internshipsSupervised()
    {
        return $this->hasMany(Internship::class, 'supervisor_id');
    }

    /** Internships where this user is the faculty adviser */
    public function internshipsAdvised()
    {
        return $this->hasMany(Internship::class, 'faculty_id');
    }

    /** Internships where this user is the coordinator */
    public function internshipsCoordinated()
    {
        return $this->hasMany(Internship::class, 'coordinator_id');
    }

    /** Scope to get students whose section matches the faculty's assigned sections */
    public function scopeAssignedToFaculty($query, int $facultyId)
    {
        $sections = \App\Models\FacultySectionAssignment::where('faculty_user_id', $facultyId)->pluck('section');
        return $query->where('role', 'student')->whereHas('studentProfile', function ($q) use ($sections) {
            $q->whereIn('section', $sections);
        });
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────
    public function scopeInDepartment($query)
    {
        $user = auth()->user();
        if (!$user) return $query;

        // Directors/Admins see all
        if ($user->hasRole('director') || $user->hasRole('admin')) {
            return $query;
        }

        $deptId = $user->facultyProfile?->department_id;
        if ($deptId) {
            return $query->whereHas('studentProfile', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        }

        return $query;
    }

    // ─── Helpers ───────────────────────────────────────────────────────────────

    public function getProfileNameAttribute(): string
    {
        $p = $this->studentProfile ?? $this->facultyProfile ?? $this->supervisorProfile;
        return $p ? trim("{$p->last_name}, {$p->first_name}") : ($this->student_number ?? $this->faculty_number ?? 'Unknown');
    }

    public function getUsernameAttribute(): ?string
    {
        return $this->student_number ?? $this->faculty_number ?? $this->email;
    }

    public function isStudent(): bool     { return $this->role === 'student'; }
    public function isSupervisor(): bool  { return $this->role === 'supervisor'; }
    public function isFaculty(): bool     { return $this->role === 'faculty'; }
    public function isCoordinator(): bool { return $this->role === 'coordinator'; }
    public function isDirector(): bool    { return $this->role === 'director'; }
    public function isAdmin(): bool       { return $this->role === 'admin'; }

    public function hasRole($roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role, $roles);
        }
        return $this->role === $roles;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /** Whether this user wants inbox notifications for a Settings preference key. */
    public function wantsNotification(string $prefKey): bool
    {
        $prefs = \App\Support\NotificationPreferences::mergeForUser($this->role, $this->notification_preferences);

        return (bool) ($prefs[$prefKey] ?? true);
    }
}
