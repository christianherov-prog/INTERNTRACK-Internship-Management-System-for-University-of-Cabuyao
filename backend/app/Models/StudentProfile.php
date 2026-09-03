<?php
namespace App\Models;
use App\Support\NameParts;
use Illuminate\Database\Eloquent\Model;
class StudentProfile extends Model {
    protected $fillable = [
        'user_id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix',
        'email', 'contact_number', 'birthday', 'sex', 'department_id', 'program_id', 'course_description', 'section', 'year_level', 'school_year', 'semester',
        'enrollment_status', 'synced_at',
    ];
    protected $casts = ['synced_at' => 'datetime', 'birthday' => 'date'];
    protected $appends = ['full_name'];
    
    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function program() { return $this->belongsTo(Program::class); }
    
    public function getFullNameAttribute(): string
    {
        return NameParts::fromProfile($this) ?: trim("{$this->last_name}, {$this->first_name}");
    }

    protected static function booted(): void
    {
        static::saved(function (StudentProfile $profile) {
            if ($profile->user_id) {
                $internship = \App\Models\Internship::where('student_id', $profile->user_id)->first();
                $facultyId = app(\App\Services\FacultySectionAssignmentService::class)->resolveFacultyForProfile($profile)?->id;

                if (!$internship) {
                    $user = \App\Models\User::find($profile->user_id);
                    if ($user && $user->role === 'student') {
                            $progName = $profile->program ? $profile->program->name : 'Bachelor of Science in Information Technology';
                            $deptName = $profile->department ? $profile->department->name : '';
                            
                            $targetHours = 500;
                            if (stripos($progName, 'Computer Science') !== false) $targetHours = 300;
                            if (stripos($progName, 'Engineering') !== false || stripos($deptName, 'Engineering') !== false) $targetHours = 240;

                            $user->internshipsAsStudent()->create([
                                'status' => 'pending_placement',
                                'school_year' => $profile->school_year ?: '2025-2026',
                                'semester' => $profile->semester ?: '2nd Semester',
                                'term' => "AY " . ($profile->school_year ?: '2025-2026') . ", " . ($profile->semester ?: '2nd Semester'),
                                'faculty_id' => $facultyId,
                                'target_hours' => $targetHours,
                                'total_hours_rendered' => 0,
                            ]);
                    }
                } elseif ($internship->faculty_id !== $facultyId) {
                    $internship->forceFill(['faculty_id' => $facultyId])->saveQuietly();
                }
            }
        });
    }
}
