<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CONTENT-PAGE-IDENTITY-01 — `docs/120` §1 ve §5(7), `docs/121` Ö12.
 *
 * Ölçülen tek soru: **`page_key` bir SAYFANIN kimliği mi, yoksa bir SATIRIN
 * mı?** Bugüne kadar satırındı — `page_key` genel olarak benzersizdi — ve bu
 * tek kısıt, aynı sayfanın Türkçesiyle İngilizcesinin aynı anda var olmasını
 * imkânsız kılıyordu. Sonucu ölçüldü: `site:import-map` 386 satır üretiyor ve
 * hepsi `tr`, sıfırı `en`. Kaynak dilin İngilizce olduğu bir sitede kütükte
 * tek bir İngilizce sayfa yoktu.
 *
 * Bu testler SONUCU ölçer: aynı anahtarın dokuz dilde birlikte yaşayabilmesi,
 * bir dilin aynı sayfayı iki kez taşıyamaması ve bir sayfanın başka dildeki
 * karşılığının anahtar üzerinden bulunabilmesi.
 */
final class ContentPageIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $overrides */
    private function row(string $pageKey, string $locale, string $path, array $overrides = []): ContentPage
    {
        return ContentPage::query()->create($overrides + [
            'page_key' => $pageKey,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'urun',
            'template_key' => 'urun',
            'title' => 'Placeholder title',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Planned->value,
        ]);
    }

    public function test_the_same_page_carries_one_row_per_language(): void
    {
        /*
            Sayfanın kimliği `urun.qr-menu`; adresi dile göre değişir. İkisi
            ayrı sorulardır ve kütük ikisini ayrı tutmak zorundadır — yoksa
            "aynı sayfa" diye bir şey kalmaz, yalnız birbirini tanımayan iki
            kayıt olur.
        */
        $this->row('urun.qr-menu', 'en', '/en/product/qr-menu/');
        $this->row('urun.qr-menu', 'tr', '/tr/urun/qr-menu/');

        self::assertSame(2, ContentPage::query()->where('page_key', 'urun.qr-menu')->count());
    }

    public function test_the_registry_carries_all_nine_infrastructure_languages(): void
    {
        /*
            `docs/120` §1: **altyapı dokuz dili bugünden tanır, ürün bugün
            yalnız birini sunar.** Bu test birincisini ölçer, ikincisini
            DEĞİL: burada tek satır çeviri yok, `shipped_locales` de
            genişletilmiyor. Ölçülen tek şey yapının dokuzu taşıyabilmesi.

            Dokuz satırı bugün ÜRETMEK ayrı bir karar olurdu ve alınmadı:
            hiç yazılmayacak üç bin satır demekti (`docs/120` §7).
        */
        $locales = (array) config('i18n.supported_locales');

        self::assertCount(9, $locales, 'Altyapı dokuz dili tanımalı (docs/120 §2).');

        foreach ($locales as $locale) {
            $this->row('urun.qr-menu', (string) $locale, "/{$locale}/urun/qr-menu/");
        }

        self::assertSame(9, ContentPage::query()->where('page_key', 'urun.qr-menu')->count());
    }

    public function test_one_language_cannot_carry_the_same_page_twice(): void
    {
        /*
            Kısıt GEVŞEMEDİ, YER DEĞİŞTİRDİ. `page_key` artık genel olarak
            benzersiz değil ama `page_key + locale` benzersiz: bir dilde aynı
            sayfanın iki kaydı olsaydı, dil değiştirici hangisine gideceğini
            bilemez ve hreflang aynı dili iki adresle ilan ederdi.
        */
        $this->row('urun.qr-menu', 'en', '/en/product/qr-menu/');

        $this->expectException(QueryException::class);

        $this->row('urun.qr-menu', 'en', '/en/product/qr-code-menu/');
    }

    public function test_the_other_language_of_a_page_is_found_through_its_key(): void
    {
        /*
            Dil değiştiricinin "aynı sayfada kal" sözü (`docs/120` §5 madde 7)
            tam olarak buna dayanıyor. Karşılık ADRESTEN bulunamaz: `/tr/urun/`
            ile `/en/product/` arasında mekanik bir bağ yoktur ve olmamalıdır —
            slug'ın çevrilebilir olması SEO'nun gereğidir.
        */
        $english = $this->row('urun.qr-menu', 'en', '/en/product/qr-menu/');
        $this->row('urun.qr-menu', 'tr', '/tr/urun/qr-menu/');
        // Başka bir sayfanın Türkçesi karışmamalı.
        $this->row('urun.menu-yonetimi', 'tr', '/tr/urun/menu-yonetimi/');

        $alternates = $english->alternates()->get();

        self::assertSame(['tr'], $alternates->pluck('locale')->all());
        self::assertSame('/tr/urun/qr-menu/', $alternates->first()?->canonical_path);
    }

    public function test_the_site_map_import_writes_source_language_rows_too(): void
    {
        /*
            ÖLÇÜLEN TIKANIKLIK. Girdi belgesi (`docs/106`) Türkçe yollarla
            yazılmış ve `docs/118` gereği DÜZENLENMEZ; kaynak dil ise artık
            İngilizce (`docs/118` E4, `docs/120` §1). Komut bugüne kadar yalnız
            belgenin kendi dilini yazıyordu, dolayısıyla kütükte sıfır `en`
            satırı vardı.

            Kaynak dil satırı, ADRESİ YAZILMIŞ sayfalar için üretilir
            (`config/site-source-paths.php`). Adresi makineyle türetmek —
            `/en/urun/qr-menu/` gibi — yarım çevrilmiş bir adres olurdu ve
            `docs/119` §10.4 "URL slug"ı çevrilip ONAYLANMASI gereken alanların
            başında sayıyor.
        */
        $this->artisan('site:import-map')->assertSuccessful();

        $turkish = ContentPage::query()->where('locale', 'tr')->count();
        $source = ContentPage::query()->where('locale', 'en')->count();

        // Belgeden gelen 386 Türkçe satır KAYBOLMADI.
        self::assertSame(386, $turkish);
        self::assertGreaterThan(0, $source, 'Kaynak dilin kütükte hiç satırı yok.');

        // Anahtar bir kimliktir (`docs/121` Ö12): iki dil AYNI anahtarı taşır.
        $english = ContentPage::query()->where('locale', 'en')->where('page_key', 'urun.qr-menu')->first();

        self::assertNotNull($english);
        self::assertSame('/en/product/qr-menu/', $english->canonical_path);
        self::assertNotNull(
            ContentPage::query()->where('locale', 'tr')->where('page_key', 'urun.qr-menu')->first(),
        );
    }

    public function test_a_second_import_creates_no_duplicate_source_rows(): void
    {
        // Komut yıkıcı olmadığı gibi ÇOĞALTICI da değildir: belgeyi ikinci kez
        // içe aktarmak kütüğü ikiye katlasaydı, tazeleme bir daha yapılamazdı.
        $this->artisan('site:import-map')->assertSuccessful();
        $first = ContentPage::query()->count();

        $this->artisan('site:import-map')->assertSuccessful();

        self::assertSame($first, ContentPage::query()->count());
    }
}
