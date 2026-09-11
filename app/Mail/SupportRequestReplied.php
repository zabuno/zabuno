<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Süperadminin CEVABI — SUPPORT-REPLY-01 (`docs/125` §6).
 *
 * `SupportRequestAcknowledged` ile aynı deseni izler: çerçeve cümleleri
 * çağıran tarafından katalogdan, talebin dilinde çözülür; Mailable ne
 * katalog ne yapılandırma bilir. AYRILDIĞI tek yer `body`'dir: o, bir
 * insanın o an yazdığı metindir ve çevrilmez, biçimlenmez, kırpılmaz.
 *
 * KONU REFERANSI TAŞIR, çünkü müşteri gelen kutusunda onu arar — ve konu
 * satırına satır sonu SIZAMAZ: bir `\r\n`, başlıkların ortasına ikinci bir
 * başlık yazmanın klasik yoludur.
 */
final class SupportRequestReplied extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{subject:string,greeting:string,intro:string,closing:string,reply:?string}  $text
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $recipientName,
        public readonly string $requestSubject,
        public readonly string $body,
        public readonly array $text,
        public readonly ?string $replyToAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            // Cevap adresi YALNIZ `SUPPORT_EMAIL` doluysa: boşken müşteri
            // "cevapla"ya bastığında kimsenin okumadığı bir kutuya yazardı.
            replyTo: $this->replyToAddress !== null ? [$this->replyToAddress] : [],
            subject: self::singleLine(str_replace('{reference}', $this->reference, $this->text['subject'])),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.support-request-replied',
            with: [
                'greeting' => str_replace('{name}', $this->recipientName, $this->text['greeting']),
                'intro' => str_replace(
                    ['{reference}', '{subject}'],
                    [$this->reference, $this->requestSubject],
                    $this->text['intro'],
                ),
                'body' => $this->body,
                'closing' => str_replace('{reference}', $this->reference, $this->text['closing']),
                'reply' => $this->text['reply'],
            ],
        );
    }

    /** CR/LF (ve sekme) tek boşluğa iner: başlık enjeksiyonu tek satırda ölür. */
    private static function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\r\n\t]+/', ' ', $value));
    }
}
