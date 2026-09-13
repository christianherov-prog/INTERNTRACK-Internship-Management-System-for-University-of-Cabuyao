<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $fillable = [
        'internship_id', 'proposed_by', 'start_time', 'end_time',
        'status', 'effective_from', 'effective_to',
        'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
