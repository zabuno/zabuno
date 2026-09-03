<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gelen iletişim mesajının SAHİBE bildirimi — `docs/93` (P0-06).
 *
 * Bildirim GÖNDERENE değil sahibe gider. Gönderen ekranda zaten teyit aldı;
 * ayrıca kum havuzu alanı ona ulaşamaz (`docs/93` kısıt bölümü).
 */
final class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            /*
                CEVAP ADRESİ gönderenin adresidir, `from` DEĞİL.

                `from` alanına ziyaretçinin adresini yazmak, alan adımızın
                adına başkasının adresinden posta göndermek olurdu: SPF ve
                DMARC bunu reddeder ve bildirim hiç ulaşmazdı.
            */
            replyTo: [$this->senderEmail],
            subject: 'Zabuno — new message from '.$this->senderName,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message-received');
    }
}
