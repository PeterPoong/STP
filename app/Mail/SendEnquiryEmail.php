<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendEnquiryEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $fullName;
    public $email;
    public $contact;
    public $emailSubject;
    public $messageContent;


    public function __construct($emailSubject, $fullName, $email, $contact, $messageContent)

    {
        $this->fullName = $fullName;
        $this->email = $email;
        $this->contact = $contact;
        $this->emailSubject = $emailSubject;
        $this->messageContent = $messageContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Enquiry FROM StudyPal',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.enquiry',
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
