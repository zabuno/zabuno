<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * Bir sayfanın metadata'sı — yönerge §12.
 *
 * Burada OLMAYAN alanlar da bir karardır:
 *
 * - **`meta keywords` yok.** Yönerge §12 açıkça "meta keywords alanı
 *   oluşturma" diyor. Hiçbir arama motoru onu okumuyor; okuyan tek şey
 *   rakiptir.
 * - **`canonical_url` yok.** Kanonik adres İÇERİĞİN değil KAYIT'ın
 *   özelliğidir (`content_pages.canonical_path`) ve `CanonicalUrl` ile
 *   üretilir. İçeriğe ikinci bir kopyasını koymak, bir gün ikisinin
 *   ayrışması demekti.
 * - **`robots` yok.** Robots kararı `PageGate`'ten gelir; içeriğin onu
 *   ezebilmesi, kapının atlanabilmesi demekti.
 */
final class PageMetadata
{
    public function __construct(
        /** Sekmede ve arama sonucunda görünen başlık. */
        public readonly string $seoTitle,
        public readonly string $metaDescription,
        /** Sayfanın TEK H1'i. Title ile aynı olmak zorunda değil (§12). */
        public readonly string $h1,
        /** Ekmek kırıntısındaki kısa ad. */
        public readonly string $breadcrumbTitle,
    ) {
        foreach ([
            'seoTitle' => $this->seoTitle,
            'metaDescription' => $this->metaDescription,
            'h1' => $this->h1,
            'breadcrumbTitle' => $this->breadcrumbTitle,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new PageContentException("Metadata alanı boş: {$field}.");
            }
        }
    }
}
