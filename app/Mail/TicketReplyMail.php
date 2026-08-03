<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket,
        public TicketReply $reply,
        public string $customerFirstName,
        public string $signature = 'Code With Mosh Support'
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Re: [Ticket #{$this->ticket->id}] {$this->ticket->subject}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket_reply',
            with: [
                'ticket'            => $this->ticket,
                'reply'             => $this->reply,
                'customerFirstName' => $this->customerFirstName,
                'signature'         => $this->signature,
            ],
        );
    }
}
