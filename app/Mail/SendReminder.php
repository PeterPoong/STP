<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $courseName;
    public $studentName;
    public $schoolName;
    public $reviewLink;

    public function __construct($data)
    {
        $this->studentName = $data['studentName'];
        $this->courseName = $data['courseName'];
        $this->schoolName = $data['schoolName'];
        $this->reviewLink = $data['reviewLink'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[IMPORTANT] Reminder: Student still waiting for your response',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
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
