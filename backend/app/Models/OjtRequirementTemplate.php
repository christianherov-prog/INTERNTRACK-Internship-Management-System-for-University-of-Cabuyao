<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OjtRequirementTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',     // pre-ojt, during, post-ojt, general
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Scope to only active requirements */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
