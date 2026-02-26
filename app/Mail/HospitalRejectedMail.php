<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HospitalRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $hospital;
    public string $reason;

    /**
     * Create a new message instance.
     */
    public function __construct(array $hospital, string $reason)
    {
        $this->hospital = $hospital;
        $this->reason = $reason;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.email_hospital_rejected_subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.hospital-rejected',
            with: [
                'hospital' => $this->hospital,
                'reason' => $this->reason,
            ],
        );
    }
}
