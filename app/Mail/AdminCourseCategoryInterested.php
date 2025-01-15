<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminCourseCategoryInterested extends Mailable
{
    use Queueable, SerializesModels;

    public $courseCategory;
    public $schoolName;
    public $totalCourse;

    public function __construct($category, $totalNumber, $schoolName)
    {
        $this->courseCategory = $category;
        $this->schoolName = $schoolName;
        $this->totalCourse = $totalNumber;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            // subject: 'Exciting Update: New Student Inquiries for your university ' . $this->schoolName . '!'
            subject: 'Student Interest Update: Prospects for' . $this->courseCategory . ' Program'

        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.adminCourseCategoryInterested',
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
