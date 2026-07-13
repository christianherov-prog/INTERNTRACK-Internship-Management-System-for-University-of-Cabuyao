<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    /**
     * Requirement checklist used to compute document compliance.
     * Kept here (not in DB) because it is fixed per internship program.
     */
    public const REQUIRED_TYPES = [
        'Endorsement Letter',
        'Memorandum of Agreement',
        'Medical Certificate',
        'Parent Consent',
        'Resume',
        'Insurance',
        'Training Plan',
        'Waiver',
        'Evaluation Form',
    ];

    protected $fillable = [
        'student_id',
        'document_type',
        'file_path',
        'original_name',
        'remarks',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
