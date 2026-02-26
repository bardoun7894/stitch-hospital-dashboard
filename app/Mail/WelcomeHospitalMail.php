<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeHospitalMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $hospitalData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $hospitalData)
    {
        $this->hospitalData = $hospitalData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.email_welcome_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-hospital',
            with: [
                'hospitalData' => $this->hospitalData,
            ],
        );
    }
}
