<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramHteRequirement extends Model
{
    protected $fillable = [
        'program_id',
        'sequence_order',
        'label',
        'required_hours',
    ];

    protected $casts = [
        'required_hours' => 'decimal:2',
        'sequence_order' => 'integer',
    ];

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function placements()
    {
        return $this->hasMany(InternshipPlacement::class);
    }
}
