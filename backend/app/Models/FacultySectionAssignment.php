<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultySectionAssignment extends Model
{
    protected $fillable = [
        'section',
        'program',
        'school_year',
        'semester',
        'faculty_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function faculty()
    {
        return $this->belongsTo(User::class, 'faculty_user_id');
    }

    protected static function booted(): void
    {
        static::saved(function (FacultySectionAssignment $assignment) {
            if ($assignment->is_active && $assignment->section && $assignment->faculty_user_id) {
                app(\App\Services\FacultySectionAssignmentService::class)->syncInternshipsForSection(
                    $assignment->section,
                    $assignment->program ?? '',
                    $assignment->school_year,
                    $assignment->semester
                );
            }
        });
    }
}
