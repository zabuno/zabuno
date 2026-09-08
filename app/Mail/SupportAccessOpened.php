<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Platform ekibinden biri hesabınıza baktı" — `docs/133` §3.
 *
 * SEBEP E-POSTAYA DA GİRER. Sahibe yalnız "biri baktı" demek, sorunun
 * yarısını cevaplar ve kalan yarısını bir destek çağrısına dönüştürür;
 * sebep zaten kayıtta duruyor ve saklanacak bir yanı yok.
 *
 * BİTİŞ ANI DA YAZILIR: erişimin ne zaman kendiliğinden biteceğini bilmek,
 * "hâlâ bakıyorlar mı?" sorusunu sormaya gerek bırakmaz.
 */
final class SupportAccessOpened extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $workspaceName,
        public readonly string $reason,
        public readonly string $startedAt,
        public readonly string $expiresAt,
        public readonly ?string $actorEmail,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Zabuno — support access to %s', $this->workspaceName),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.support-access-opened');
    }
}
