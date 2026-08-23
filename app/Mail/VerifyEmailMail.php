<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $verificationUrl,
    ) {}

    public function build(): self
    {
        return $this->subject(__('auth.verify_email_subject'))
            ->view('emails.verify-email')
            ->with([
                'recipientName' => $this->recipientName,
                'verificationUrl' => $this->verificationUrl,
            ]);
    }
}
