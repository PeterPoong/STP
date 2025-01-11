<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendInterestedCourseCategoryEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $courseCategory;
    public $schoolName;

    public function __construct($schoolName, $data)
    {
        $this->courseCategory = $data;
        $this->schoolName = $schoolName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Congratulations!! Your Monthly Student Interested Report had Arrived'
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.sendInterestedCourseCategory',
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
