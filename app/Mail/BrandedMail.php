<?php

namespace App\Mail;

use App\Models\Dealer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrandedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $viewName,
        public array $payload = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->mailSubject);
    }

    public function content(): Content
    {
        $dealer = $this->payload['dealer'] ?? Dealer::query()->orderBy('id')->first();

        return new Content(
            view: $this->viewName,
            with: array_merge([
                'subject' => $this->mailSubject,
                'dealer' => $dealer,
            ], $this->payload),
        );
    }
}
