<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPortfolio extends Model
{
    protected $fillable = [
        'internship_id',
        'user_id',
        'company_name',
        'company_address',
        'company_vision',
        'company_mission',
        'company_history',
        'assessment_ethical',
        'assessment_learnings',
        'assessment_experience',
        'assessment_standards',
        'assessment_recommendations',
        'assessment_advice',
        'custom_fields',
    ];

    protected $casts = [
        'custom_fields' => 'array',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
