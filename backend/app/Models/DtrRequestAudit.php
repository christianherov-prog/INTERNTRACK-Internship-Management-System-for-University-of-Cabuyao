<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrRequestAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'auditable_type', 'auditable_id',
        'internship_id', 'student_id',
        'event', 'actor_id', 'actor_role',
        'original_state', 'requested_values',
        'decision', 'remarks', 'final_applied_value',
        'created_at',
    ];

    protected $casts = [
        'original_state' => 'array',
        'requested_values' => 'array',
        'final_applied_value' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
