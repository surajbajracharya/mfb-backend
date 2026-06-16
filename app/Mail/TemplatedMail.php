<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TemplatedMail extends Mailable
{

    public function __construct(
        public readonly string $emailSubject,
        public readonly string $bodyHtml,
        public readonly array  $settings,  // EmailSetting data array
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                $this->settings['from_email'],
                $this->settings['from_name'],
            ),
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.templated',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'settings' => $this->settings,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
