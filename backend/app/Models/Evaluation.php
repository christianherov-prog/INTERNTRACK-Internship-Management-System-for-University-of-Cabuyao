<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluation extends Model
{
    protected $fillable = [
        'student_id',
        'evaluation_type',
        'evaluator_name',
        'evaluator_role',
        'score',
        'max_score',
        'work_quality',
        'punctuality',
        'communication',
        'initiative',
        'remarks',
        'status',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'evaluated_at' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
