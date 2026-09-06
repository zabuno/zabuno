<?php

declare(strict_types=1);

namespace App\Application\Legal;

use App\Application\Legal\Port\ConsentLedgerPort;
use App\Application\Legal\Port\LegalLibraryPort;
use App\Domain\Legal\ConsentRecord;
use DateTimeImmutable;
use Illuminate\Http\Request;
use LogicException;

/**
 * Onayı KAYDEDER — hangi belgenin hangi sürümü o an yayındaysa (FF-198).
 *
 * Sürüm çağırandan değil KÜTÜPHANEDEN okunur: denetleyicinin "1.0" yazması,
 * metin "1.1"e geçtiğinde sessizce yanlış sürüm kaydetmek olurdu. Kayıt
 * anında yayında olan sürüm, kabul edilen sürümdür.
 *
 * İki giriş noktası, iki an:
 *   - `recordRegistration`: hesap açılırken — Hizmet Koşulları + Gizlilik
 *     Politikası zorunlu; ticari ileti izni yalnız işaretlendiyse.
 *   - `recordCheckout`: ödeme adımında — Ön Bilgilendirme Formu + Mesafeli
 *     Satış Sözleşmesi. Bu paket yalnız servisi kurar; ödeme akışı ayrı
 *     paketin işi (ff-197) ve o paket bu metodu çağırır.
 */
final class ConsentRecorder
{
    public const KIND_REGISTRATION = 'registration';

    public const KIND_MARKETING = 'marketing';

    public const KIND_CHECKOUT = 'checkout';

    public function __construct(
        private readonly LegalLibraryPort $library,
        private readonly ConsentLedgerPort $ledger,
    ) {}

    public function recordRegistration(int $userId, Request $request, bool $marketingConsent): void
    {
        $this->write($userId, null, self::KIND_REGISTRATION, ['terms', 'privacy'], $request);

        // SESSİZLİK ONAY DEĞİLDİR: işaretlenmeyen kutu için satır yazılmaz.
        if ($marketingConsent) {
            $this->write($userId, null, self::KIND_MARKETING, ['marketing-consent'], $request);
        }
    }

    public function recordCheckout(int $userId, ?int $workspaceId, Request $request): void
    {
        $this->write($userId, $workspaceId, self::KIND_CHECKOUT, ['pre-information', 'distance-sales'], $request);
    }

    /** @param  list<string>  $documentKeys */
    private function write(int $userId, ?int $workspaceId, string $kind, array $documentKeys, Request $request): void
    {
        $now = new DateTimeImmutable;

        foreach ($documentKeys as $key) {
            $document = $this->library->find($key)
                ?? throw new LogicException("Cannot record consent for unknown legal document \"{$key}\".");

            $this->ledger->append(new ConsentRecord(
                userId: $userId,
                workspaceId: $workspaceId,
                kind: $kind,
                documentKey: $document->key,
                documentVersion: $document->version,
                granted: true,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                recordedAt: $now,
            ));
        }
    }
}
