<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gönderene ALINDI bildirimi — FF-201 (`docs/125` §1).
 *
 * Bu e-posta müşterinin elinde kalan tek şeydir: referans numarası. Ekran
 * kapanır, sekme kaybolur; e-posta durur. Bu yüzden konu satırı referansı
 * taşır — gelen kutusunda aranan şey odur.
 *
 * METİN HAZIR GELİR: cümleler çağıranın (bildirici) katalogdan gönderenin
 * dilinde çözdüğü dizelerdir. Mailable ne katalog ne yapılandırma bilir;
 * yalnız verilen cümleleri sırayla yazar. Böylece "bu e-postada ne yazıyor"
 * sorusunun cevabı tek yerde durur ve testte okunabilir.
 */
final class SupportRequestAcknowledged extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject:string,greeting:string,received:string,subjectLine:string,keep:string,panel:?string,reply:?string,commitment:?string}  $text
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $recipientName,
        public readonly string $requestSubject,
        public readonly array $text,
        public readonly ?string $replyToAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            /*
                CEVAP ADRESİ yalnız yapılandırılmışsa. `from` adresi
                `noreply` olabilir; müşteri "cevapla"ya bastığında mesajı
                kimsenin okumadığı bir kutuya yazmamalı. Adres yoksa
                e-postada "cevaplayabilirsiniz" cümlesi de yoktur.
            */
            replyTo: $this->replyToAddress !== null ? [$this->replyToAddress] : [],
            subject: str_replace('{reference}', $this->reference, $this->text['subject']),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.support-request-acknowledged',
            with: [
                'greeting' => str_replace('{name}', $this->recipientName, $this->text['greeting']),
                'received' => str_replace('{reference}', $this->reference, $this->text['received']),
                'subjectLine' => str_replace('{subject}', $this->requestSubject, $this->text['subjectLine']),
                'keep' => $this->text['keep'],
                'panel' => $this->text['panel'],
                'reply' => $this->text['reply'],
                'commitment' => $this->text['commitment'],
            ],
        );
    }
}
