<?php

declare(strict_types=1);

namespace App\Domain\Rating;

use InvalidArgumentException;

/**
 * KÖTÜYE KULLANIM KURALLARI — `docs/116` §4, algoritma dosyasının `abuse`
 * bölümünden okunur.
 *
 * ═══ KURALLAR DOSYADA, UYGULAMASI BURADA ═══
 *
 * P2 bu kuralları `config/rating-algorithm/v1.php` içine yazdı; oy verme
 * ucu (P4) onları buradan okur. Sayıları koda gömseydik iki liste olurdu ve
 * bir gün ayrışırlardı: dosyada yazan pencere on beş dakika, kodda çalışan
 * pencere otuz.
 *
 * ═══ EKSİK BİR KURAL SESSİZCE "KAPALI" SAYILMAZ ═══
 *
 * Bu sınıfın her alanı dosyada AÇIKÇA bulunmak zorundadır. Eksik bir
 * `require_table_scan_for_guest_signals` alanını `false` saymak, kötüye
 * kullanım korumasını bir yazım hatasıyla kapatmak olurdu — ve o kapanma
 * hiçbir yerde görünmezdi.
 *
 * ═══ SİLME YOK, İŞARETLEME VAR ═══
 *
 * Sebepler KAPALI BİR LİSTEDİR. Serbest metin olsaydı, altı ay sonra aynı
 * sebep dört farklı yazımla kayıtlı olurdu ve "kaç oyu neden eledik?"
 * sorusu cevaplanamazdı — yani algoritmayı ayarlarken kullanacağımız tek
 * geri bildirim okunamaz hâle gelirdi.
 */
final class RatingAbuse
{
    /**
     * Fikrini değiştiren misafirin ESKİ oyunun işaret sebebi.
     *
     * Kod bu dizeyi TANIR ama SAHİPLENMEZ: değerin kendisi algoritma
     * dosyasındaki kapalı listede yaşar ve orada yoksa burada patlar
     * (`assertReasonIsDeclared`). Sabit burada durmasaydı, uç ile dosya
     * arasındaki uyuşmazlık ancak üretimde bir satır yazılırken görülürdü.
     */
    public const REASON_SUPERSEDED = 'superseded';

    /** Ani yığılma tespitinin işaret sebebi. */
    public const REASON_BURST = 'burst_detected';

    /**
     * @param  list<string>  $exclusionReasons
     */
    public function __construct(
        private readonly bool $requiresTableScan,
        private readonly bool $oneSignalPerVisitorPerSubject,
        private readonly int $burstWindowMinutes,
        private readonly int $burstMaxSignalsPerTable,
        private readonly array $exclusionReasons,
    ) {
        if ($burstWindowMinutes <= 0) {
            throw new InvalidArgumentException('Rating abuse burst window must be a positive number of minutes.');
        }

        /*
            SIFIR TAVAN, TAVAN DEĞİLDİR: her oyu yığılma sayardı ve tek bir
            misafirin ilk oyu bile ağırlıklandırma dışında kalırdı. Ürün
            çalışıyor görünür, hiçbir puan oluşmazdı.
        */
        if ($burstMaxSignalsPerTable <= 0) {
            throw new InvalidArgumentException('Rating abuse burst ceiling must allow at least one signal.');
        }

        if ($exclusionReasons === []) {
            throw new InvalidArgumentException('Rating abuse rules must declare the closed list of exclusion reasons.');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromArray(int $version, array $definition): self
    {
        foreach (['require_table_scan_for_guest_signals', 'one_signal_per_visitor_per_subject', 'burst_window_minutes', 'burst_max_signals_per_table', 'exclusion_reasons'] as $key) {
            if (! array_key_exists($key, $definition)) {
                throw new InvalidArgumentException(
                    'Rating algorithm v'.$version.' abuse rules are missing `'.$key.'`.'
                );
            }
        }

        $reasons = [];

        foreach ((array) $definition['exclusion_reasons'] as $reason) {
            $reasons[] = (string) $reason;
        }

        return new self(
            (bool) $definition['require_table_scan_for_guest_signals'],
            (bool) $definition['one_signal_per_visitor_per_subject'],
            (int) $definition['burst_window_minutes'],
            (int) $definition['burst_max_signals_per_table'],
            $reasons,
        );
    }

    /**
     * Oy vermek için o masadan karekod okutmuş olmak gerekir mi?
     *
     * `docs/116` §4'ün en ağır cümlesi. Kapatılırsa masadan gelen oyun
     * ağırlık üstünlüğünün gerekçesi de ortadan kalkar — bu yüzden değer
     * dosyadan okunur ve kodda varsayılmaz.
     */
    public function requiresTableScan(): bool
    {
        return $this->requiresTableScan;
    }

    /** Ziyaretçi + ürün başına tek SAYILAN oy. */
    public function allowsOnlyOneSignalPerVisitorPerSubject(): bool
    {
        return $this->oneSignalPerVisitorPerSubject;
    }

    public function burstWindowMinutes(): int
    {
        return $this->burstWindowMinutes;
    }

    public function burstMaxSignalsPerTable(): int
    {
        return $this->burstMaxSignalsPerTable;
    }

    /** @return list<string> */
    public function exclusionReasons(): array
    {
        return $this->exclusionReasons;
    }

    /**
     * Bir sebebin dosyadaki kapalı listede olduğunu doğrular.
     *
     * Doğrulanmayan bir sebep sütuna yazılabilseydi, kapalı liste bir
     * yorumdan ibaret kalırdı.
     */
    public function assertReasonIsDeclared(string $reason): string
    {
        if (! in_array($reason, $this->exclusionReasons, true)) {
            throw new InvalidArgumentException(
                'Rating exclusion reason `'.$reason.'` is not declared by the algorithm file.'
            );
        }

        return $reason;
    }
}
