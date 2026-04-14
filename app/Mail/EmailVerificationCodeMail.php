<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  string  $code  The 6-digit verification code (plain text for display in email).
     * @param  string  $expiresInMinutes  Human-readable expiry (e.g. "10 minutes").
     */
    public function __construct(
        public readonly string $code,
        public readonly string $expiresInMinutes = '5'
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'AGRIGUARD');

        return new Envelope(
            subject: 'AGRIGUARD Email Verification',
            from: new Address($fromAddress, $fromName),
            replyTo: [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'auth.email-verification-code',
        );
    }
}
