<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternshipStatusHistory extends Model
{
    protected $fillable = [
        'internship_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
    ];

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
