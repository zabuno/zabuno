<?php

declare(strict_types=1);

namespace App\Domain\Content\Block;

use App\Domain\Content\PageContentException;

/**
 * Bir blok içindeki TEK satır — yönerge §12 (`source_claim_map`).
 *
 * Bütün bloklar aynı satır biçimini paylaşır. Alternatif, blok başına ayrı
 * bir veri sınıfı yazmaktı; on bir sınıf, on bir şablon ve on bir test
 * ederdi ve hepsi aynı üç alanı taşırdı.
 *
 * `source` bu paketin dürüstlük kapısıdır. Yönerge §1 madde 18 *"ürünün
 * gerçekten desteklemediği özellik yayınlanmaz"* diyor; bu, iyi niyete
 * bırakıldığında tutulmaz. Yetenek, adım, gereksinim ve sınırlama satırları
 * DEPODA BİR YOL göstermek zorundadır ve test o yolun var olduğunu ölçer.
 * Kanıtı silinen bir iddia kapıyı kırar.
 */
final class BlockEntry
{
    public function __construct(
        /** Görünen metin: paragraf, cevap, adım açıklaması, sınırlama cümlesi. */
        public readonly string $text,
        /** Satırın adı: soru, adım başlığı, yetenek adı, gereksinim etiketi. */
        public readonly ?string $term = null,
        /** İddiayı taşıyan depo yolu — depo köküne göre. */
        public readonly ?string $source = null,
        /** Yalnız CTA için: bugün gerçekten yayında olan bir yol. */
        public readonly ?string $href = null,
        /**
         * Yalnız "ilgili sayfalar" için: kütükteki sayfa anahtarı.
         *
         * Hazır bir adres DEĞİL, çünkü o adres kütükte yaşar ve sayfa henüz
         * yayında olmayabilir. Bağlantı verilebilirliği çizim anında
         * sorulur; yayınlanmamış sayfa hiçbir yerden iç bağlantı almaz
         * (`docs/105` §2.2(3)).
         */
        public readonly ?string $pageKey = null,
    ) {
        if (trim($this->text) === '') {
            throw new PageContentException('Boş bir satır içerik değildir.');
        }
    }
}
