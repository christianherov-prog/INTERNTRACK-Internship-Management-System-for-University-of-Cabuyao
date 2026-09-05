<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HteRequest extends Model
{
    protected $fillable = [
        'student_id',
        'company_name',
        'address',
        'contact_person',
        'contact_email',
        'contact_number',
        'status',
        'remarks',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
