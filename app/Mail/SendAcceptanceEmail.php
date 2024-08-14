<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendAcceptanceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $studentName;
    public $courseName;
    public $schoolName;
    public $feedback;

    public function __construct($data)
    {
        $this->studentName = $data['studentName'];
        $this->courseName = $data['courseName'];
        $this->schoolName = $data['schoolName'];
        $this->feedback = $data['feedback'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations! You\'ve Been Accepted to ' . $this->schoolName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.acceptance',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
