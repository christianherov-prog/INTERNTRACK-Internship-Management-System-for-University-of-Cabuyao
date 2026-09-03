<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageThreadState extends Model
{
    protected $fillable = [
        'user_id',
        'internship_id',
        'peer_id',
        'archived_at',
        'cleared_before',
        'cleared_before_message_id',
    ];

    protected $casts = [
        'archived_at'               => 'datetime',
        'cleared_before'            => 'datetime',
        'cleared_before_message_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function peer()
    {
        return $this->belongsTo(User::class, 'peer_id');
    }

    public static function forThread(int $userId, int $internshipId, int $peerId): self
    {
        return static::firstOrCreate(
            [
                'user_id'        => $userId,
                'internship_id'  => $internshipId,
                'peer_id'        => $peerId,
            ],
            [
                'archived_at'    => null,
                'cleared_before' => null,
            ]
        );
    }
}
