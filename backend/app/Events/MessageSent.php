<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Legacy conversation broadcast payload.
 * Flat DM (MessageController) uses polling — this event is unused for new sends.
 */
class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        // Legacy conversation channel; flat DM does not broadcast.
        return [
            new PrivateChannel('conversation.'.($this->message->conversation_id ?? 0)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender');

        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id ?? null,
            'sender_id' => $this->message->sender_id,
            'sender_username' => $this->message->sender?->username,
            'body' => $this->message->body,
            'created_at' => optional($this->message->created_at)?->toIso8601String(),
        ];
    }
}
