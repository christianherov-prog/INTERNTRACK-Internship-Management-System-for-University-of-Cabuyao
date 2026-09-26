<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Weekly Journal submission deadline set by the internship's assigned Faculty.
 * due_at is stored in the application timezone (UTC) and shown in Asia/Manila.
 */
class JournalDeadline extends Model
{
    protected $fillable = ['internship_id', 'week_number', 'due_at', 'set_by'];

    protected $casts = [
        'week_number' => 'integer',
        'due_at' => 'datetime',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function setter()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
