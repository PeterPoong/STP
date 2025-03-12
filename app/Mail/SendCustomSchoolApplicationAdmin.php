<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendCustomSchoolApplicationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public $institute_name;
    public $course_name;
    public $student_name;
    public $student_email;
    public $student_phone;
    public $application_date;


    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->institute_name = $data['institute_name'];
        $this->course_name = $data['course_name'];
        $this->student_name = $data['student_name'];
        $this->student_email = $data['student_email'];
        $this->student_phone = $data['student_phone'];
        $this->application_date = $data['application_date'];
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Applicant For ' . $this->institute_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notifyCustomSchoolEmailForAdmin',  // Update this to the correct view path
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
