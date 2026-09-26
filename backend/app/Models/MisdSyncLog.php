<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MisdSyncLog extends Model
{
    protected $fillable = [
        'direction',
        'entity_type',
        'entity_key',
        'status',
        'actor_user_id',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
