<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use DateTimeImmutable;

/**
 * BİZİM VARLIĞIMIZ ↔ DIŞ SİSTEMDEKİ KİMLİK — `docs/116` §1 Ö4 (P7).
 *
 * ═══ EŞLEME KESİN DEĞİLDİR VE ÖYLE DAVRANILMAZ ═══
 *
 * Bu tablo olmadan dış veri ancak isim benzerliğiyle bağlanır — ve "Lezzet
 * Sarayı" adında üç restoran vardır. Yanlış eşleme, BAŞKASININ PUANINI
 * bizim restoranımızda göstermektir; hiçbir ekran uyarısı bunu telafi etmez.
 *
 * ═══ TEK KURAL, TEK YERDE ═══
 *
 * `mayCarryExternalDataToGuest()` bu paketin tamamıdır: dış veri misafire
 * ancak SAHİBİN ONAYLADIĞI bir eşleme üzerinden ulaşabilir. Kural burada
 * durur ki her yeni okuma yolu onu yeniden hatırlamak zorunda kalmasın —
 * bir gün biri hatırlamazdı.
 *
 * Güven düzeyi bu kararın İÇİNE GİRMEZ. En yüksek bant bile eşleştiricinin
 * kendi iddiasıdır; iddianın kendini onaylaması diye bir şey yoktur.
 */
final class ExternalReference
{
    public function __construct(
        public readonly int $id,
        /** Kiracı kapsamı: bu satırı okuyan her sorgunun `WHERE`'i bunu görür. */
        public readonly int $workspaceId,
        /** Bizim varlığımızın türü — şube ya da ürün. */
        public readonly RatingSubject $subjectType,
        public readonly int $subjectId,
        public readonly ExternalSystem $system,
        /** Dış sistemin kendi kimliği (ör. bir Google yer kimliği). */
        public readonly string $externalId,
        /**
         * Dış sistemde görünen ad — sahibin kararı verebilmesi için.
         *
         * Bir işletme adı kişisel veri değildir; §5 D5'in yasakladığı şey
         * yorum yazarının adı, fotoğrafı ve profilidir ve bu alan onları
         * taşımaz.
         */
        public readonly ?string $externalLabel,
        public readonly ExternalMatchConfidence $confidence,
        /** Eşlemeyi KİM kurdu — onaylayanla aynı soru değildir. */
        public readonly ExternalMatchedBy $matchedBy,
        /** Eşlemenin kurulduğu an (öneri anı). */
        public readonly DateTimeImmutable $matchedAt,
        /** Boş: sahip henüz cevap vermedi. */
        public readonly ?ExternalReferenceDecision $decision,
        public readonly ?int $decidedByUserId,
        public readonly ?DateTimeImmutable $decidedAt,
    ) {}

    /**
     * Dış veri bu eşleme üzerinden misafire gösterilebilir mi?
     *
     * Tek cevap sahibin onayıdır (`docs/116` §5 D4): *"Otomatik eşleme bir
     * öneridir. Sahip 'bu benim restoranım değil' diyebilmelidir; diyemezse
     * başkasının puanını taşıyoruz demektir."*
     */
    public function mayCarryExternalDataToGuest(): bool
    {
        return $this->decision === ExternalReferenceDecision::Confirmed;
    }

    /**
     * Sahibin karar kutusunda duruyor mu?
     *
     * Reddedilmiş bir eşleme BEKLEMİYOR: "hayır" verilmiş bir cevaptır,
     * yeniden sorulacak bir soru değil.
     */
    public function awaitsOwnerDecision(): bool
    {
        return $this->decision === null;
    }
}
