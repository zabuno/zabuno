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
 *     Satış Sözleşmesi, ve AYRI bir kayıt olarak ifaya derhâl başlama onayı.
 *
 * ═══ İFAYA DERHÂL BAŞLAMA ONAYI NEDEN AYRI BİR SATIR (FF-216) ═══
 *
 * Bu, sözleşmeyi okudum onayının bir parçası DEĞİLDİR ve olmamalıdır.
 * Mesafeli Sözleşmeler Yönetmeliği'nde hizmetin ifasına cayma süresi dolmadan
 * başlanması tüketicinin AYRI ve AÇIK onayına bağlıdır ve bu onayın cayma
 * hakkı üzerinde sonucu vardır. İki onayı tek kutuda toplamak, tüketicinin
 * neye evet dediğini ayırt edilemez hâle getirirdi: defterde "sözleşmeyi
 * kabul etti" satırı olur, "ifaya derhâl başlanmasını istedi" satırı olmazdı
 * — ve tam olarak o ikinci satır, cayma hakkının ne zaman sona erdiğini
 * gösteren kanıttır.
 *
 * Bu yüzden ayrı bir KİP (`immediate_performance`) ve ayrı bir satır. Yeni
 * bir mekanizma icat edilmedi: aynı defter, aynı sütunlar, aynı sürüm
 * okuma kuralı. Kutu işaretlenmemişse satır YAZILMAZ ve sipariş de
 * başlamaz (`StoreCheckoutController`) — sessizlik onay değildir.
 */
final class ConsentRecorder
{
    public const KIND_REGISTRATION = 'registration';

    public const KIND_MARKETING = 'marketing';

    public const KIND_CHECKOUT = 'checkout';

    /**
     * Cayma süresi dolmadan ifaya başlanmasına verilen AÇIK onay.
     *
     * Belge anahtarı `distance-sales`: onay o sözleşmenin bir maddesine
     * verilir ve sözleşmenin sürümü değiştiğinde bu onayın da hangi metne
     * verildiği değişir. Ayrı bir "belge" uydurmak, kütüphanede karşılığı
     * olmayan bir anahtar yazmak olurdu.
     */
    public const KIND_IMMEDIATE_PERFORMANCE = 'immediate_performance';

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

    /**
     * @param  bool  $immediatePerformance  Alıcı, ifaya derhâl başlanmasını
     *                                      AÇIKÇA istedi mi? İşaretlenmemiş
     *                                      bir kutu için satır yazılmaz.
     */
    public function recordCheckout(int $userId, ?int $workspaceId, Request $request, bool $immediatePerformance): void
    {
        $this->write($userId, $workspaceId, self::KIND_CHECKOUT, ['pre-information', 'distance-sales'], $request);

        if ($immediatePerformance) {
            $this->write($userId, $workspaceId, self::KIND_IMMEDIATE_PERFORMANCE, ['distance-sales'], $request);
        }
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
