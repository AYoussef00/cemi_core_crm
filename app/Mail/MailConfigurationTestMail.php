<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Administrator-only configuration test message (own account recipient).
 */
class MailConfigurationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $adminName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'اختبار إعداد البريد — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.configuration-test',
            with: [
                'adminName' => $this->adminName,
                'appName' => config('app.name'),
                'sentAt' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ],
        );
    }
}
