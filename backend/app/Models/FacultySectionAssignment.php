<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultySectionAssignment extends Model
{
    protected $fillable = [
        'program',
        'section',
        'academic_year',
        'semester',
        'faculty_user_id',
        'is_active',
    ];

    protected $casts = [
        'semester'  => 'integer',
        'is_active' => 'boolean',
    ];

    public function faculty()
    {
        return $this->belongsTo(User::class, 'faculty_user_id');
    }
}
