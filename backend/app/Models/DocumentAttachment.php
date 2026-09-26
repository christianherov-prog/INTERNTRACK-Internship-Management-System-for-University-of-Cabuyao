<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentAttachment extends Model
{
    protected $fillable = ['document_id', 'file_path', 'file_name', 'file_size', 'mime_type'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
