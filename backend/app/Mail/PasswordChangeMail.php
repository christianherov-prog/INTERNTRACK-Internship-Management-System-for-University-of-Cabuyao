<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a user requests a password-change confirmation link.
 * Uses a plaintext body — no Blade view dependency needed.
 */
class PasswordChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User   $user,
        public readonly string $confirmationLink,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->user->email,
            subject: 'Confirm Password Change - INTERNTRACK',
        );
    }

    public function content(): Content
    {
        $displayName = $this->user->name ?: $this->user->username;

        return new Content(
            view: 'emails.password_change',
            text: 'emails.password_change.text',
            with: [
                'displayName'      => $displayName,
                'confirmationLink' => $this->confirmationLink,
            ],
        );
    }
}
