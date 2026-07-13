<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookEntry extends Model
{
    protected $fillable = [
        'student_id',
        'entry_date',
        'hours_rendered',
        'tasks_completed',
        'learning_reflection',
        'status',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'hours_rendered' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
