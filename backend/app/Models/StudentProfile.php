<?php

namespace App\Models;

use App\Services\FacultySectionAssignmentService;
use App\Services\ProgramRequirementService;
use App\Support\InternshipProvisioning;
use App\Support\NameParts;
use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'contact_number', 'birthday', 'sex', 'department_id', 'program_id', 'course_description', 'section', 'year_level', 'school_year', 'semester',
        'enrollment_status', 'synced_at',
    ];

    protected $casts = ['synced_at' => 'datetime', 'birthday' => 'date'];

    protected $appends = ['full_name'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function getFullNameAttribute(): string
    {
        return NameParts::fromProfile($this) ?: trim("{$this->last_name}, {$this->first_name}");
    }

    protected static function booted(): void
    {
        static::saved(function (StudentProfile $profile) {
            if ($profile->user_id) {
                $internship = InternshipProvisioning::openForStudent($profile->user_id);
                $facultyId = app(FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;

                if (! $internship) {
                    $hasAny = Internship::where('student_id', $profile->user_id)->exists();
                    $user = User::find($profile->user_id);
                    if (! $hasAny && $user && $user->role === 'student') {
                        $profile->loadMissing('program');
                        $progName = $profile->getRelation('program')?->name;

                        InternshipProvisioning::createPendingIfNone($user, [
                            'status' => 'pending_placement',
                            'school_year' => $profile->school_year ?: '2025-2026',
                            'semester' => $profile->semester ?: '2nd Semester',
                            'term' => 'AY '.($profile->school_year ?: '2025-2026').', '.($profile->semester ?: '2nd Semester'),
                            'program' => $progName,
                            'faculty_id' => $facultyId,
                            'target_hours' => ProgramRequirementService::targetHoursForProfile($profile),
                            'total_hours_rendered' => 0,
                        ]);
                    }
                } elseif (
                    $facultyId
                    && (int) $internship->faculty_id !== (int) $facultyId
                    && (
                        // No usable adviser yet: the section default fills it.
                        ! FacultySectionAssignmentService::isValidAdviser($internship->faculty_id)
                        // A section/program transfer (MISD registry sync) moves the
                        // Student to the new section's faculty.
                        || $profile->wasChanged(['section', 'program_id'])
                    )
                ) {
                    // Any other profile save (contact details, address, …) never
                    // replaces a deliberate adviser assignment.
                    $internship->forceFill(['faculty_id' => $facultyId])->saveQuietly();
                }
            }
        });
    }
}
