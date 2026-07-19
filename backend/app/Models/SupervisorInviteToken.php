<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorInviteToken extends Model
{
    protected $fillable = [
        'internship_id', 'student_id', 'token', 'expires_at', 'status',
        'supervisor_user_id', 'first_name', 'last_name', 'email',
        'contact_number', 'position', 'company_id',
        'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function internship() { return $this->belongsTo(Internship::class); }
    public function student()    { return $this->belongsTo(User::class, 'student_id'); }
    public function supervisor() { return $this->belongsTo(User::class, 'supervisor_user_id'); }
    public function company()    { return $this->belongsTo(Company::class); }
    public function reviewer()   { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }
}
