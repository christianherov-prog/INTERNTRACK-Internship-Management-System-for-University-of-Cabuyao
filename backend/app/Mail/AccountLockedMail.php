<?php

namespace App\Mail;

use App\Models\User;
use App\Support\ManilaTime;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once when an account is locked after repeated failed sign-in attempts.
 * Contains no password, token, or reset link.
 */
class AccountLockedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $recipient,
        public readonly CarbonInterface $lockedAt,
        public readonly int $attempts,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->recipient,
            subject: 'Security Alert: Your InternTrack account has been locked',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account_locked',
            text: 'emails.account_locked_text',
            with: [
                'displayName' => $this->user->username ?: 'InternTrack user',
                'lockedAtDisplay' => $this->lockedAtDisplay(),
                'attempts' => $this->attempts,
                'supportContact' => (string) config('interntrack.account_support_contact'),
            ],
        );
    }

    public function lockedAtDisplay(): string
    {
        return $this->lockedAt->copy()->timezone(ManilaTime::TZ)->format('F j, Y g:i A').' (Asia/Manila)';
    }
}
