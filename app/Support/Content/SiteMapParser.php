<?php

declare(strict_types=1);

namespace App\Support\Content;

/**
 * Site haritası belgesini registry satırlarına çevirir — FF-117, yönerge Faz 1.
 *
 * Kaynak `docs/106-SITE-MAP-INPUT.md`: sahibin verdiği, 414 canonical yol
 * taşıyan bir markdown ağacı. O yolları elle kopyalamak, bir gün belgeyle
 * registry'nin ayrışması demekti — belge "bu sayfa var" derken registry onu
 * hiç bilmiyor olurdu.
 *
 * Ayrıştırıcı SAF'tır: dosya okumaz, veritabanı bilmez, çerçeveye bağlanmaz.
 * Girdisi metin, çıktısı satır listesi. Böylece testi de saftır ve gerçek
 * belgeyi ölçen tek bir test yeterli olur.
 */
final class SiteMapParser
{
    /**
     * Ağacın yaşadığı bölüm. `## 5` XML sitemap bölümüdür ve oradaki
     * `/sitemap.xml` bir SAYFA değildir; sınırı bilmeyen bir ayrıştırıcı
     * registry'ye olmayan sayfalar yazar.
     */
    private const TREE_HEADING = '## 4. Tam site ağacı';

    /**
     * @return list<array{
     *     page_key: string,
     *     canonical_path: string,
     *     parent_path: string|null,
     *     title: string,
     *     priority: string,
     *     is_template: bool,
     *     is_external: bool,
     *     depth: int
     * }>
     */
    public static function parse(string $markdown): array
    {
        $rows = [];
        $inTree = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with($line, '## ')) {
                $inTree = str_starts_with($line, self::TREE_HEADING);

                continue;
            }

            if (! $inTree) {
                continue;
            }

            $matched = preg_match('/^(\s*)- `(?<path>[^`]+)`(?<rest>.*)$/u', $line, $match);

            if ($matched !== 1) {
                continue;
            }

            $path = $match['path'];

            // Ağacın kökü alan adıdır, bir sayfa değil.
            if (! str_starts_with($path, '/')) {
                continue;
            }

            $depth = (int) floor(strlen($match[1]) / 2);
            $rest = $match['rest'];

            $rows[] = [
                'page_key' => self::pageKeyFor($path),
                'canonical_path' => $path,
                // İkinci geçişte doldurulur: ebeveyn GİRİNTİDEN değil YOLDAN
                // türer (aşağıdaki gerekçe).
                'parent_path' => null,
                'title' => self::titleOf($rest),
                'priority' => self::priorityOf($rest),
                // `{slug}` bir DESENDİR: tek tek yaratılan bir sayfa değil,
                // bir şablon. Registry'de sabit bir sayfa gibi davranamaz.
                'is_template' => str_contains($rest, '[TEMPLATE]') || str_contains($path, '{'),
                // Dış bağlantı bu sitede bir sayfa DEĞİLDİR; ona hazırlanıyor
                // ekranı göstermek, olmayan bir sayfayı yapıyormuş gibi
                // göstermek olurdu.
                'is_external' => str_contains($rest, '[EXTERNAL]'),
                'depth' => $depth,
            ];
        }

        return self::withParents($rows);
    }

    /**
     * Ebeveyn GİRİNTİDEN DEĞİL YOLDAN türer.
     *
     * Belgede `/tr/` ile `/tr/urun/` aynı girintide duruyor — yani kardeş
     * yazılmışlar — oysa adres hiyerarşisinde biri diğerinin altındadır.
     * Girintiye güvenmek, belgedeki bir biçim tercihini ürünün bilgi
     * mimarisi sanmak olurdu. Adres hiyerarşisi ise tartışmasızdır.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function withParents(array $rows): array
    {
        $known = array_flip(array_column($rows, 'canonical_path'));

        foreach ($rows as $index => $row) {
            $rows[$index]['parent_path'] = self::nearestAncestor((string) $row['canonical_path'], $known);
        }

        return $rows;
    }

    /** @param  array<string, int>  $known */
    private static function nearestAncestor(string $path, array $known): ?string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        for ($take = count($segments) - 1; $take >= 1; $take--) {
            $candidate = '/'.implode('/', array_slice($segments, 0, $take)).'/';

            if (isset($known[$candidate]) && $candidate !== $path) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Yoldan KALICI bir anahtar türetir.
     *
     * Anahtar yol değişse bile aynı kalmalıdır — bu yüzden dil dizini düşer ve
     * geri kalan segmentler noktayla birleşir. `/tr/urun/qr-menu/` →
     * `urun.qr-menu`. Yol yarın `/tr/urunler/qr-menu/` olursa anahtar elle
     * korunur ve kayıt yeni bir sayfaya bölünmez.
     */
    public static function pageKeyFor(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        /*
            Dil dizini anahtarın parçası DEĞİLDİR ve bu, çok dilliliğin
            koşuludur: `/tr/urun/qr-menu/` ile `/en/product/qr-menu/` AYNI
            anahtarı taşır — `urun.qr-menu` — ve dil değiştirici karşılığı
            o anahtardan bulur (`docs/120` §5 madde 7).

            (Bu satır kurulduğunda gerekçesi "iki dil TEK kayıttır" diye
            yazılmıştı; ölçüldü ve yanlıştı — tek kayıt, aynı sayfanın iki
            dilinin birlikte var olmasını imkânsız kılıyordu. Anahtarın
            dilsiz olması doğruydu, ondan çıkarılan sonuç değil.)
        */
        if ($segments !== [] && in_array($segments[0], ['tr', 'en'], true)) {
            array_shift($segments);
        }

        if ($segments === []) {
            return 'home';
        }

        $segments = array_map(
            static fn (string $segment): string => trim($segment, '{}'),
            $segments,
        );

        return implode('.', $segments);
    }

    private static function titleOf(string $rest): string
    {
        if (preg_match('/—\s*(?<title>.+?)\s*(?:`\[|$)/u', $rest, $match) === 1) {
            return trim($match['title']);
        }

        return '';
    }

    private static function priorityOf(string $rest): string
    {
        return preg_match('/\[(?<priority>P[0-2])\]/', $rest, $match) === 1 ? $match['priority'] : 'P2';
    }
}
