<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HospitalApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $hospital;

    /**
     * Create a new message instance.
     */
    public function __construct(array $hospital)
    {
        $this->hospital = $hospital;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.email_hospital_approved_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.hospital-approved',
            with: [
                'hospital' => $this->hospital,
            ],
        );
    }
}
