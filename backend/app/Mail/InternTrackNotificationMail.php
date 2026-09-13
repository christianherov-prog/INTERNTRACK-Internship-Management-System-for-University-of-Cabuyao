<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InternTrackNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $notifTitle;
    public string $notifMessage;
    public ?string $notifLink;
    public string $logoPath;

    public function __construct(string $title, string $message, ?string $link = null)
    {
        $this->notifTitle   = $title;
        $this->notifMessage = $message;
        $this->notifLink    = $link;
        $preferred = resource_path('images/pnc-logo.png');
        $fallback = resource_path('images/fo30/pnc-seal.png');
        $this->logoPath = file_exists($preferred) ? $preferred : $fallback;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[InternTrack] ' . $this->notifTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'logoPath' => $this->logoPath,
            ],
        );
    }
}
