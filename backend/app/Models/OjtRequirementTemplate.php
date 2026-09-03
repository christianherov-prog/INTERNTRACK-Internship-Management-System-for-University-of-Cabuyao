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
        'template_file_path',
        'template_file_name',
        'drive_link',
        'created_by',
        'deadline',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'deadline'   => 'datetime',
    ];

    /** Scope to only active requirements */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(RequirementTemplateAttachment::class, 'requirement_template_id');
    }

    public function targets()
    {
        return $this->hasMany(RequirementTarget::class, 'requirement_template_id');
    }
}


