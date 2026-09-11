<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Application\Platform\Port\PlatformAuditPort;
use App\Application\Support\Port\SupportNotifierPort;
use App\Application\Support\Port\SupportRequestRepositoryPort;
use App\Domain\Support\SupportReplyOutcome;
use App\Domain\Support\SupportRequestStatus;

/**
 * Süperadminin cevabı — SUPPORT-REPLY-01 (`docs/125` §6, `docs/107` 1.6).
 *
 * SIRA BU PAKETİN BÜTÜN ARGÜMANI: önce GÖNDER, sonra damgala. Ters sırada
 * (önce `answered`, sonra gönder) taşıyıcı düştüğünde satır cevaplanmış
 * görünürdü, Hüseyin beklerdi ve "kaç saatte cevap verdik" ölçümü
 * gönderilmemiş bir cevabı sayardı. Damga yalnız GERÇEKTEN dışarı çıkmış
 * bir cevabın arkasından düşer; ilk kez damgalama kuralı deponun
 * `changeStatus` yolunda, tek yerde durur.
 *
 * EXACTLY-ONCE İDDİASI YOK. Yinelenen gönderimi eleyen bir anahtar (ledger,
 * idempotency tablosu) bu pakette YOKTUR: iki eşzamanlı POST iki e-posta
 * üretir. Tek savunma ekrandadır (gönderim sürerken düğme basılamaz) ve
 * bu, belgede saklanmıyor.
 *
 * GÖVDE HİÇBİR YERDE KALMAZ: ne satırda, ne denetim izinde. Denetim izi
 * KİMİN hangi talebe cevapladığını taşır — bir yönetişim olgusu; cevabın
 * METNİ müşterinin cümlesidir.
 */
final readonly class ReplyToSupportRequest
{
    private const AUDIT_SCOPE = 'support.reply';

    public function __construct(
        private SupportRequestRepositoryPort $requests,
        private SupportNotifierPort $notifier,
        private PlatformAuditPort $audit,
    ) {}

    /** `null` = böyle bir talep yok (çağıran bunu sayım yapılamayan 404'e çevirir). */
    public function handle(int $id, string $body, ?int $actorUserId): ?SupportReplyOutcome
    {
        $target = $this->requests->findReplyTarget($id);

        if ($target === null) {
            return null;
        }

        $outcome = $this->notifier->reply($target, $body);

        if ($outcome !== SupportReplyOutcome::Sent) {
            // Satır DEĞİŞMEZ: aynı gövde yeniden gönderilebilir.
            $this->audit->record(
                self::AUDIT_SCOPE,
                'reply_failed',
                $target->reference,
                ['outcome' => $outcome->value],
                $actorUserId,
            );

            return $outcome;
        }

        $this->requests->changeStatus($id, SupportRequestStatus::Answered);

        $this->audit->record(
            self::AUDIT_SCOPE,
            'reply_sent',
            $target->reference,
            ['outcome' => $outcome->value],
            $actorUserId,
        );

        return $outcome;
    }
}
