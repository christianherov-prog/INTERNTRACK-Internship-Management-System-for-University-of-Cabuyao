<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'internship_id', 'document_type', 'file_path', 'file_name', 'file_size', 'mime_type',
        'status', 'current_stage', 'remarks', 'reviewed_by', 'reviewed_at', 'submitted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviews()
    {
        return $this->hasMany(DocumentReview::class);
    }

    public function getFileUrlAttribute(): string
    {
        return asset('storage/'.$this->file_path);
    }
}
