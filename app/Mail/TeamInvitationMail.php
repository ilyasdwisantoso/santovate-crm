<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $workspaceName,
        public string $inviterName,
        public string $invitationUrl,
        public string $expiryLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Undangan Tim Santovate CRM — '.$this->workspaceName);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.team-invitation');
    }
}
