<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = [
        'title', 'type', 'description', 'starts_at', 'ends_at',
        'location', 'meeting_url', 'created_by', 'internship_id', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public const TYPES = ['orientation', 'check_in', 'defense_prep', 'other'];

    public const STATUSES = ['scheduled', 'cancelled', 'completed'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function attendees()
    {
        return $this->hasMany(MeetingAttendee::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'meeting_attendees')
            ->withPivot('rsvp')
            ->withTimestamps();
    }
}
