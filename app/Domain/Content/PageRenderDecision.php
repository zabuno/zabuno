<?php

declare(strict_types=1);

namespace App\Domain\Content;

/**
 * `PageGate`'in kararı — FF-117, yönerge §7.
 *
 * Bir sayfa hakkındaki BÜTÜN yayın soruları tek bir nesnede yanıtlanır: ne
 * çizilecek, hangi HTTP kodu dönecek, robots ne diyecek, sitemap'e ve menüye
 * girecek mi, ona iç bağlantı verilebilir mi. Bu soruları ayrı ayrı yanıtlamak,
 * bir gün "menüde görünen ama 404 dönen" bir sayfa üretirdi.
 */
final class PageRenderDecision
{
    public function __construct(
        /** `content` | `construction` | `not-found` | `maintenance` | `preview` */
        public readonly string $mode,
        public readonly int $statusCode,
        public readonly string $robots,
        public readonly bool $includeInSitemap,
        public readonly bool $includeInNavigation,
    ) {}

    /**
     * Bu sayfaya İÇ BAĞLANTI verilebilir mi?
     *
     * Yönergenin kendi içindeki çelişki buradan kapanıyor (`docs/105` §2.2,
     * madde 3): plan hem "yayınlanmamış sayfa 404 döner" hem de CI'da "broken
     * link scan" istiyordu. Menüde ya da içerik içinde duran her yayınlanmamış
     * bağlantı kendi CI'ını kırar. Kural tek yerde yaşar: bağlantı
     * verilebilirlik, gerçekten çalışan bir sayfa olmakla aynı şeydir.
     */
    public function isLinkable(): bool
    {
        return $this->statusCode === 200 && $this->mode === 'content';
    }
}
