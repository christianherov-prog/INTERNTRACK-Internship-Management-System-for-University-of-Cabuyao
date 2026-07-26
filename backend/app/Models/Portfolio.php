<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model
{
    protected $fillable = [
        'internship_id',
        // Chapter I
        'company_profile',
        'company_background',
        'company_vision',
        'company_mission',
        'company_logo_path',
        'org_chart_path',
        'org_chart_caption',
        // Chapter III
        'prof_ethical_responsibilities',
        'things_learned',
        'experience_with_people',
        'industry_best_practices',
        'recommendations',
        'advice',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function photos()
    {
        return $this->hasMany(PortfolioPhoto::class);
    }
}
