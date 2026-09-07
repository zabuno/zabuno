<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Yeni destek talebinin SAHİBE bildirimi — `docs/93` (P0-06), FF-201 ile
 * referans kazandı (`docs/125`).
 *
 * Bildirim GÖNDERENE değil sahibe gider; gönderen kendi alındı
 * bildirimini ayrıca alır (`SupportRequestAcknowledged`). Referans burada
 * da vardır: sahip cevap yazarken müşterinin elindeki numarayla aynı
 * numarayı görmeli.
 *
 * Metin İngilizce ve katalog dışı: bu sahibin iç bildirimi, bir müşteri
 * yüzeyi değil. Alındı e-postası ise katalogdan gelir.
 */
final class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $reference,
        public readonly string $senderName,
        public readonly string $senderEmail,
        public readonly string $requestSubject,
        public readonly string $body,
        public readonly string $channel,
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
            subject: sprintf('Zabuno — support request %s from %s', $this->reference, $this->senderName),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message-received');
    }
}
