<?php

declare(strict_types=1);

namespace App\Application\Rating\Dto;

/**
 * Bir menü satırının puan özeti — panelin okuduğu satır (`docs/116` §3, P5).
 *
 * ═══ EŞİK ALTINDA PUAN BU SINIFTAN ÇIKAMAZ ═══
 *
 * Kural yapıcıda UYGULANIR, çağıranın nezaketine bırakılmaz: karar olumsuzsa
 * `score` `null` olur, ne yazılmış olursa olsun. Bu tek satır, "eşik altında
 * puan gösterilmez" kuralının ikinci bir yerde yeniden yazılmasını
 * gereksizleştirir — ekran unutsa bile gösterecek bir sayı bulamaz.
 *
 * ═══ SIFIR ASLA "BİLİNMİYOR" DEMEK DEĞİLDİR ═══
 *
 * `null` ile `0.0` arasındaki fark bu sınıfın var oluş sebebidir. Sıfır bir
 * ÖLÇÜMDÜR: "misafirler bu tabağa sıfır verdi" der. Bilinmeyenin yerine
 * konursa, hiç oy almamış her yeni ürün menünün en kötüsü gibi görünür.
 *
 * ═══ SAYIM BİR ÖLÇÜMDÜR VE SAHİPTEN GİZLENMEZ ═══
 *
 * `signalCount` eşik altında da doludur ve bu bir çelişki değil: sahibin
 * "kaç oy geldi" sorusunun cevabı bilinen bir sayıdır. Gizlenen şey PUAN,
 * yani henüz güvenilmeyen türetilmiş değerdir. Misafire ise sayım da
 * inmez — o soru misafirin sorusu değildir.
 */
final class RatingSummary
{
    public readonly ?float $score;

    public function __construct(
        public readonly int $menuItemId,
        public readonly int $productId,
        public readonly string $productName,
        ?float $score,
        public readonly int $scaleMax,
        public readonly int $signalCount,
        public readonly bool $meetsDisplayThreshold,
        /** Türetilmiş puanın yaşı — "bu sayı ne kadar eski?" (ATOM). */
        public readonly ?string $computedAt,
        public readonly ?string $replyBody,
        public readonly ?string $replyPublishedAt,
    ) {
        $this->score = $meetsDisplayThreshold ? $score : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'menuItemId' => $this->menuItemId,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'score' => $this->score,
            'scaleMax' => $this->scaleMax,
            'signalCount' => $this->signalCount,
            'meetsDisplayThreshold' => $this->meetsDisplayThreshold,
            'computedAt' => $this->computedAt,
            'reply' => $this->replyBody === null ? null : [
                'body' => $this->replyBody,
                'publishedAt' => $this->replyPublishedAt,
            ],
        ];
    }
}
