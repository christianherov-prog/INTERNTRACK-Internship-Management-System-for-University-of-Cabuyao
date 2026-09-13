<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteRequest extends Model
{
    protected $fillable = [
        'student_id',
        'company_name',
        'address',
        'organization_type',
        'contact_person',
        'contact_email',
        'contact_number',
        'status',
        'remarks',
        'moa_path',
        'moa_original_name',
        'coordinator_remarks',
    ];

    protected $appends = [
        'organization_type_label',
    ];

    public function getOrganizationTypeLabelAttribute(): string
    {
        return \App\Support\OrganizationTypes::label($this->organization_type ?? null);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
