<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $expiresInMinutes = '10'
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'AGRIGUARD');

        return new Envelope(
            subject: 'AGRIGUARD Password Reset Code',
            from: new Address($fromAddress, $fromName),
            replyTo: [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'auth.password-reset-code-email',
        );
    }
}
