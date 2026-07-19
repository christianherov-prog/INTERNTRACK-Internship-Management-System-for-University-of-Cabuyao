<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentReview extends Model
{
    protected $fillable = [
        'document_id',
        'stage',
        'action',
        'from_status',
        'to_status',
        'remarks',
        'reviewed_by',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
