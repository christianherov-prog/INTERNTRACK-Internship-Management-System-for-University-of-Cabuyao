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
        'username', 'email', 'password', 'role', 'is_active', 'last_login_at', 'avatar_path',
        'notification_preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active'                 => 'boolean',
        'last_login_at'             => 'datetime',
        'notification_preferences'  => 'array',
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

    /**
     * The internship whose portfolio the student may still build/edit.
     * Includes 'completed' since the portfolio (final report & appendices)
     * is typically finished during or right after wrap-up of the internship.
     */
    public function portfolioInternship()
    {
        return $this->hasOne(Internship::class, 'student_id')
                    ->whereIn('status', ['ongoing', 'placed', 'active', 'for_evaluation', 'completed'])
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

    // ─── Helper Methods ───────────────────────────────────────────────────────

    public function getProfileNameAttribute(): string
    {
        $p = $this->studentProfile ?? $this->facultyProfile ?? $this->supervisorProfile;
        return $p ? trim("{$p->first_name} {$p->last_name}") : $this->username;
    }

    public function isStudent(): bool     { return $this->role === 'student'; }
    public function isSupervisor(): bool  { return $this->role === 'supervisor'; }
    public function isFaculty(): bool     { return $this->role === 'faculty'; }
    public function isCoordinator(): bool { return $this->role === 'coordinator'; }
    public function isDirector(): bool    { return $this->role === 'director'; }
    public function isAdmin(): bool       { return $this->role === 'admin'; }
}
