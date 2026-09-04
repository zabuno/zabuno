<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Support\Content\SiteMapParser;
use PHPUnit\Framework\TestCase;

/**
 * PAGE-REGISTRY-PARSE-01 — FF-117, yönerge Faz 1.
 *
 * Site haritası bir markdown ağacıdır ve 414 canonical yol taşır. Bu yolları
 * elle kopyalamak, bir gün belgeyle registry'nin ayrışması demekti: belge
 * "yayında" derken registry o sayfayı hiç bilmiyor olurdu.
 *
 * Ayrıştırıcı SAF'tır: dosya okumaz, veritabanı bilmez, çerçeveye bağlanmaz.
 * Girdisi metin, çıktısı satır listesi.
 */
final class SiteMapParserTest extends TestCase
{
    private const SAMPLE = <<<'MD'
        ## 4. Tam site ağacı

        - `zabuno.com`
          - `/tr/` — Ana sayfa `[P0]`
          - `/tr/urun/` — Ürün genel bakış `[P0]`
            - `/tr/urun/qr-menu/` — QR menü `[P0]`
            - `/tr/urun/tablet-menu/` — Tablet `[P2]`
          - `/tr/blog/{slug}/` — Blog yazısı `[TEMPLATE] [P0]`
          - `/tr/gelistiriciler/durum/` — Durum `[EXTERNAL] [P1]`

        ## 5. XML sitemap yapısı

        - `/sitemap.xml` — Sitemap index `[P0]`
        MD;

    public function test_it_reads_every_canonical_path_with_its_title_and_priority(): void
    {
        $rows = SiteMapParser::parse(self::SAMPLE);

        $paths = array_column($rows, 'canonical_path');

        self::assertContains('/tr/', $paths);
        self::assertContains('/tr/urun/qr-menu/', $paths);
        self::assertSame('QR menü', $rows[array_search('/tr/urun/qr-menu/', $paths, true)]['title']);
        self::assertSame('P0', $rows[array_search('/tr/urun/qr-menu/', $paths, true)]['priority']);
        self::assertSame('P2', $rows[array_search('/tr/urun/tablet-menu/', $paths, true)]['priority']);
    }

    public function test_it_stops_at_the_end_of_the_tree_section(): void
    {
        // `## 5` XML sitemap bölümüdür ve orada yazan `/sitemap.xml` bir SAYFA
        // değildir. Ayrıştırıcı bölüm sınırını bilmezse registry'ye olmayan
        // bir sayfa girer.
        $paths = array_column(SiteMapParser::parse(self::SAMPLE), 'canonical_path');

        self::assertNotContains('/sitemap.xml', $paths);
    }

    public function test_it_records_the_parent_so_the_tree_survives(): void
    {
        $rows = SiteMapParser::parse(self::SAMPLE);
        $byPath = array_column($rows, null, 'canonical_path');

        self::assertNull($byPath['/tr/']['parent_path']);
        self::assertSame('/tr/', $byPath['/tr/urun/']['parent_path']);
        self::assertSame('/tr/urun/', $byPath['/tr/urun/qr-menu/']['parent_path']);
    }

    public function test_it_marks_templates_and_external_links(): void
    {
        $byPath = array_column(SiteMapParser::parse(self::SAMPLE), null, 'canonical_path');

        // Şablon sayfası tek tek yaratılmaz; `{slug}` bir DESENDİR ve
        // registry'de sabit bir sayfa gibi davranamaz.
        self::assertTrue($byPath['/tr/blog/{slug}/']['is_template']);
        self::assertFalse($byPath['/tr/urun/qr-menu/']['is_template']);

        // Dış bağlantı bu sitede bir sayfa DEĞİLDİR; ona hazırlanıyor ekranı
        // göstermek, olmayan bir sayfayı yapıyormuş gibi göstermek olurdu.
        self::assertTrue($byPath['/tr/gelistiriciler/durum/']['is_external']);
    }

    public function test_it_derives_a_stable_page_key_from_the_path(): void
    {
        $byPath = array_column(SiteMapParser::parse(self::SAMPLE), null, 'canonical_path');

        // Anahtar YOLDAN türer ve bir daha değişmez: yol değişse bile eski
        // anahtar aynı sayfayı gösterir, yeni bir kayıt doğmaz.
        self::assertSame('home', $byPath['/tr/']['page_key']);
        self::assertSame('urun.qr-menu', $byPath['/tr/urun/qr-menu/']['page_key']);
        self::assertSame('blog.slug', $byPath['/tr/blog/{slug}/']['page_key']);
    }

    public function test_the_real_site_map_has_no_duplicate_path_or_key(): void
    {
        /*
            Yönergenin 5. değiştirilemez kararı: her canonical URL tek bir
            içerik kaydına karşılık gelir. Bu test kaynağın kendisini ölçer —
            belgede iki kez geçen bir yol, registry'de sessizce ikinci bir
            sayfa üretirdi.
        */
        $rows = SiteMapParser::parse((string) file_get_contents(__DIR__.'/../../../docs/106-SITE-MAP-INPUT.md'));

        self::assertGreaterThan(300, count($rows), 'PAGE-REGISTRY-PARSE-01: site haritası okunamadı.');

        $paths = array_column($rows, 'canonical_path');
        $keys = array_column($rows, 'page_key');

        self::assertSame([], $this->duplicates($paths), 'PAGE-REGISTRY-PARSE-01: aynı canonical yol iki kez geçiyor.');
        self::assertSame([], $this->duplicates($keys), 'PAGE-REGISTRY-PARSE-01: aynı page_key iki kez üretiliyor.');
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function duplicates(array $values): array
    {
        $counts = array_count_values($values);

        return array_values(array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)));
    }
}
