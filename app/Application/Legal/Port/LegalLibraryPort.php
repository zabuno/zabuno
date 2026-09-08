<?php

declare(strict_types=1);

namespace App\Application\Legal\Port;

use App\Domain\Legal\LegalDocument;

/**
 * Yasal belgelerin kaynağı — FF-198.
 *
 * Bir port, çünkü metin bugün kodda yaşıyor ama orada kalmak zorunda
 * değil: hukukçu incelemesi sonrası metin bir tabloya ya da dosyaya
 * taşınırsa değişecek tek şey bu portun arkasıdır. `ContentLibraryPort`
 * ile aynı gerekçe.
 */
interface LegalLibraryPort
{
    /**
     * Kütüphanenin TANIDIĞI anahtarlar — ve aynı zamanda adresleri.
     *
     * Burada, portta duruyor (FF-228) çünkü iki farklı katman aynı listeye
     * bakıyor: rota kütüğü (`routes/web.php`) adresleri buradan açar,
     * sitemap yaşayan yollarını buradan tanır. Liste uygulamanın kendi
     * kaydında olmasaydı, üç yere elle kopyalanır ve bir belge eklendiği gün
     * biri unutulurdu — sonuç: altbilgide bağlantısı olan bir 404.
     *
     * SIRA `all()` SIRASIDIR ve anlamlıdır: önce kullanıcının kaydolurken
     * kabul ettikleri, sonra uzaktan satışın belgeleri, en sonra bir
     * zincirin satın alma birimi için yazılan kurumsal sözleşmeler
     * (`docs/140`).
     *
     * @var list<string>
     */
    public const KEYS = [
        'terms',
        'privacy',
        'kvkk',
        'distance-sales',
        'pre-information',
        'delivery',
        'refund-policy',
        'cookies',
        'marketing-consent',
        'data-processing',
        'sla',
        'acceptable-use',
        'third-party-licenses',
    ];

    /** Belge yoksa `null` — bir hata değil, bir DURUM. */
    public function find(string $key, string $locale = 'en'): ?LegalDocument;

    /** @return list<LegalDocument> */
    public function all(string $locale = 'en'): array;
}
