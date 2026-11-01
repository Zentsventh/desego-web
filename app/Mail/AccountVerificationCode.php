<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de verificación de tu cuenta DESEGO',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.account-verification',
            with: ['code' => $this->code],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
