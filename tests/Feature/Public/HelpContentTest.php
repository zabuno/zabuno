<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Application\Localization\Port\TranslationPort;
use App\Support\Localization\HelpLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P1-01 (üçüncü ölçüt) RED — "ilk 15 dakika" yardımı (`docs/89`).
 *
 * Gereksinim üç soruyu adıyla istiyor: menümü nasıl aktarırım, karekodu
 * nasıl basarım, fiyatı nasıl güncellerim. Bunlar sahibin ilk oturumunda
 * takılacağı üç yerdir ve üçünün de cevabı ürünün içinde değil, kafasındaydı.
 *
 * BELGE, ARAYÜZ ETİKETİ DEĞİL. Bu sayfa 40'tan fazla cümle taşıyor ve cümle
 * başına katalog anahtarı makaleler için yanlış şekildir: çevirmen bağlamı
 * göremez, bir paragrafı bölmek anahtar listesini bozar ve gözden geçiren
 * metni bir bütün olarak okuyamaz. Makaleler DİLE GÖRE DOSYA olarak yaşar.
 *
 * Requirement IDs: HELP-NO-AUTH-01, HELP-THREE-QUESTIONS-01,
 * HELP-POINTS-AT-REAL-SCREENS-01, HELP-EVERY-LOCALE-01.
 */
final class HelpContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_is_readable_without_an_account(): void
    {
        // Tıkanan biri oturum açamıyor olabilir — yardımın oturum istemesi,
        // en çok ihtiyaç duyulduğu anda kapıyı kapatırdı.
        $this->get('/help')->assertOk();
    }

    public function test_it_answers_the_three_questions_the_first_session_raises(): void
    {
        $html = $this->get('/help')->getContent();

        foreach (['help-import', 'help-qr', 'help-price'] as $anchor) {
            self::assertStringContainsString(
                'id="'.$anchor.'"',
                $html,
                "HELP-THREE-QUESTIONS-01: '{$anchor}' bölümü yok — sahibi tek bir soruya bağlantı verebilmeli."
            );
        }
    }

    public function test_it_points_at_screens_that_actually_exist(): void
    {
        $html = $this->get('/help')->getContent();

        /*
            Yardım GERÇEK ekranlara işaret eder.

            Var olmayan bir ekranı tarif eden yardım, kullanıcıyı ikinci kez
            tıkar: önce özelliği bulamaz, sonra yardımın da yanıldığını görür
            ve bir daha açmaz.
        */
        foreach (['CSV', 'Publication', 'Sold out'] as $realSurface) {
            self::assertStringContainsString($realSurface, $html);
        }
    }

    // --- HELP-EVERY-LOCALE-01 ---------------------------------------------

    /**
     * Desteklenen her dilin HER makalesi VAR OLMALI.
     *
     * Eksik bir dil, o dili seçen kullanıcıya sessizce İngilizce gösterirdi
     * — ve bunu kimse fark etmezdi. Kapı, eksikliği kullanıcıya değil CI'a
     * gösterir.
     *
     * Kütüphane çok makaleye açıldığında bu kapının da açılması ZORUNLU:
     * yalnız giriş makalesini ölçen bir kapı, ikinci makale yarım
     * çevrildiğinde sessiz kalırdı ve "yedek dile düşme" borcu tam da
     * oradan doğardı. Ölçülen küme artık DİL × MAKALE.
     */
    #[DataProvider('supportedArticleTranslations')]
    public function test_every_supported_language_has_every_article(string $locale, string $slug): void
    {
        self::assertFileExists(
            HelpLibrary::pathFor($locale, $slug),
            "HELP-EVERY-LOCALE-01: [{$locale}] için '{$slug}' makalesi yok."
        );
    }

    /** @return list<array{0:string,1:string}> */
    public static function supportedArticleTranslations(): array
    {
        $cases = [];

        foreach (HelpLibrary::SUPPORTED as $locale) {
            foreach (HelpLibrary::ARTICLES as $slug) {
                $cases[] = [$locale, $slug];
            }
        }

        return $cases;
    }

    public function test_the_reader_gets_their_own_language(): void
    {
        $turkish = $this->withHeaders(['Accept-Language' => 'tr'])->get('/help')->getContent();
        $english = $this->withHeaders(['Accept-Language' => 'en'])->get('/help')->getContent();

        self::assertMatchesRegularExpression('#<html lang="tr"#', $turkish);
        self::assertMatchesRegularExpression('#<html lang="en"#', $english);
        self::assertNotSame($turkish, $english, 'İki dil aynı metni veriyorsa çeviri yoktur.');
    }

    public function test_explicit_language_choice_controls_both_help_article_and_chrome(): void
    {
        foreach ([['en', 'tr', 'Your first 15 minutes', 'Help'], ['tr', 'en', 'İlk 15 dakikanız', 'Yardım']] as [$choice, $browser, $title, $navigation]) {
            $response = $this->withUnencryptedCookie('zbn_language', $choice)
                ->withHeader('Accept-Language', $browser)->get('/help');
            $response->assertOk()->assertSee('<html lang="'.$choice.'"', false)
                ->assertSee($title)->assertSee('>'.$navigation.'<', false);
        }
    }

    public function test_turkish_instructions_name_the_actual_turkish_controls_and_keep_csv_columns(): void
    {
        $translator = app(TranslationPort::class);
        $article = (string) file_get_contents(HelpLibrary::pathFor('tr'));
        foreach ([
            ['workspace', 'workspace.shell.nav.menu'],
            ['menu', 'menu.export.download'],
            ['menu', 'menu.import.label'],
            ['workspace', 'workspace.shell.nav.publication'],
            ['menu', 'menu.item.price.edit.short'],
            ['workspace', 'workspace.publication.history.title'],
            ['menu', 'menu.item.stock.out.short'],
        ] as [$domain, $key]) {
            $label = $translator->translate($domain, $key, 'tr');
            self::assertNotSame($key, $label, 'Missing Turkish control label.');
            self::assertStringContainsString('<strong>'.e($label).'</strong>', $article, $key);
        }
        self::assertStringContainsString('category, product, price, currency, allergens, description, visible', $article);
        foreach (['help-import', 'help-qr', 'help-price'] as $anchor) {
            self::assertStringContainsString('id="'.$anchor.'"', $article);
        }
    }

    // --- HELP-PHOTO-01 -----------------------------------------------------

    /**
     * İkinci makale de oturum İSTEMEZ ve kendi dilinde açılır.
     *
     * Adres allowlist'tedir: `/help` giriş makalesi olarak KALIR, yeni
     * makale `/help/<slug>` altında durur ve ikisi de aynı çerez
     * pazarlığını kullanır — makale Türkçe geldiyse üst çubuk da Türkçe
     * okunmalı (`docs/100` MP-03).
     */
    public function test_the_photo_article_opens_without_an_account_in_the_readers_language(): void
    {
        foreach ([
            ['en', 'tr', 'How do I put a photo on a dish?', 'Help'],
            ['tr', 'en', 'Bir ürüne fotoğraf nasıl eklerim?', 'Yardım'],
        ] as [$choice, $browser, $title, $navigation]) {
            $this->withUnencryptedCookie('zbn_language', $choice)
                ->withHeader('Accept-Language', $browser)
                ->get('/help/a-photo-on-a-dish')
                ->assertOk()
                ->assertSee('<html lang="'.$choice.'"', false)
                ->assertSee($title)
                ->assertSee('>'.$navigation.'<', false);
        }
    }

    /**
     * Kayıtlı olmayan bir makale adı SAYFA DEĞİLDİR.
     *
     * Denetleyici adresi bir dosya yoluna çevirmez; kütüphanedeki listeyi
     * gezer. Aksi hâlde `/help/<herhangi bir şey>` bir görünüm arama
     * yüzeyi olurdu ve bir yardım sayfası, deponun geri kalanını yoklamanın
     * en ucuz yolu hâline gelirdi.
     */
    public function test_an_unregistered_help_address_is_not_a_page(): void
    {
        foreach (['/help/does-not-exist', '/help/first-15-minutes', '/help/public.layout'] as $unknown) {
            $this->get($unknown)->assertNotFound();
        }
    }

    /**
     * İkinci makale BULUNABİLİR olmalı.
     *
     * Kimse adresi tahmin etmez: giriş makalesi ona bağlanmıyorsa makale
     * yazılmamış sayılır. Bağlantı HER DİLDE var.
     */
    public function test_the_entry_article_points_at_the_photo_article_in_every_language(): void
    {
        foreach (HelpLibrary::SUPPORTED as $locale) {
            self::assertStringContainsString(
                'href="/help/a-photo-on-a-dish"',
                (string) file_get_contents(HelpLibrary::pathFor($locale)),
                "HELP-PHOTO-01: [{$locale}] giriş makalesi fotoğraf makalesine bağlanmıyor."
            );
        }
    }

    /**
     * Türkçe makale GERÇEK Türkçe denetim adlarını kullanır.
     *
     * Bir makale ekranda yazmayan bir düğme adı söylerse okuyucu iki kez
     * tıkanır: önce düğmeyi bulamaz, sonra yardımın da yanıldığını görür.
     * Adlar kataloğun kendisinden okunuyor — makale ile arayüz ayrışamaz.
     */
    public function test_the_turkish_photo_article_names_the_actual_turkish_controls(): void
    {
        $translator = app(TranslationPort::class);
        $article = (string) file_get_contents(HelpLibrary::pathFor('tr', 'a-photo-on-a-dish'));

        foreach ([
            ['workspace', 'workspace.shell.nav.media'],
            ['workspace', 'workspace.media.upload.heading'],
            ['workspace', 'workspace.media.upload.dropzone.label'],
            ['workspace', 'workspace.media.upload.field.assetSlot'],
            ['workspace', 'workspace.media.upload.field.assetSlot.itemImage'],
            ['menu', 'menu.media.slot.itemImage'],
            ['workspace', 'workspace.media.upload.field.altText'],
            ['workspace', 'workspace.media.upload.button'],
            ['workspace', 'workspace.media.library.asset.status.ready'],
            ['menu', 'menu.item.presentation.edit.short'],
            ['menu', 'menu.item.presentation.submit'],
            ['workspace', 'workspace.shell.nav.publication'],
            ['workspace', 'workspace.publication.preview.button'],
            ['workspace', 'workspace.publication.status.region'],
            ['workspace', 'workspace.publication.publishAction.checklistConfirmed'],
            ['workspace', 'workspace.publication.status.publishButton'],
            ['workspace', 'workspace.media.library.trash.heading'],
        ] as [$domain, $key]) {
            $label = $translator->translate($domain, $key, 'tr');
            self::assertNotSame($key, $label, "Missing Turkish control label for {$key}.");
            self::assertStringContainsString('<strong>'.e($label).'</strong>', $article, $key);
        }
    }

    /**
     * Makale ÜRÜNÜN YAPMADIĞI şeyi vaat etmez.
     *
     * Taslak metin üç yerde ölçülmemiş bir söz veriyordu: işlemenin kaç
     * saniye süreceği, telefondan çıkan her biçimin kabul edileceği ve
     * "Preview & publish" adında hiç var olmayan bir düğme. Üçü de
     * kullanıcının ekranda göremeyeceği şeylerdir; kapı onları geri
     * gelmekten alıkoyar.
     */
    public function test_the_photo_article_promises_only_what_the_screens_do(): void
    {
        $translator = app(TranslationPort::class);

        foreach (HelpLibrary::SUPPORTED as $locale) {
            $article = (string) file_get_contents(HelpLibrary::pathFor($locale, 'a-photo-on-a-dish'));

            // "Hemen yayınla" kataloğda DURUYOR ama hiçbir ekran onu çizmiyor
            // (`PublishActionConfigRegion` o kalıcı devre dışı kip seçimini
            // kaldırdı). Yayın makalesindeki kapının aynısı burada da durur:
            // iki makale aynı ekranı anlatıyor, biri ölü bir düğme adına
            // geri dönemez.
            $unrendered = $translator->translate(
                'workspace',
                'workspace.publication.publishAction.mode.immediate',
                $locale
            );

            self::assertStringNotContainsString(
                '<strong>'.e($unrendered).'</strong>',
                $article,
                "HELP-PHOTO-01: [{$locale}] makalesi çizilmeyen bir denetimi ('{$unrendered}') düğme diye gösteriyor."
            );

            foreach (['Preview &amp; publish', 'Preview & publish', 'HEIF', 'HEIC'] as $unsupportedClaim) {
                self::assertStringNotContainsString(
                    $unsupportedClaim,
                    $article,
                    "HELP-PHOTO-01: [{$locale}] makalesi ölçülmemiş bir söz veriyor: {$unsupportedClaim}."
                );
            }

            self::assertDoesNotMatchRegularExpression(
                '/\b(seconds|saniye)\b/iu',
                $article,
                "HELP-PHOTO-01: [{$locale}] makalesi işleme süresi vaat ediyor; o süre ölçülmedi."
            );
        }
    }

    // --- HELP-PUBLICATION-01 -----------------------------------------------

    /**
     * ÜÇÜNCÜ MAKALE de oturum İSTEMEZ ve kendi dilinde açılır.
     *
     * Bu makalenin okuru özel bir okurdur: menüsünü düzeltmiş, kaydetmiş ve
     * masadaki misafirde hiçbir şeyin değişmediğini görmüştür. O anda ürüne
     * güveni sarsılmıştır ve yardımın oturum sorması, güvensizliği doğrular.
     */
    public function test_the_publication_article_opens_without_an_account_in_the_readers_language(): void
    {
        foreach ([
            ['en', 'tr', 'Why has nothing changed for my guests?', 'Help'],
            ['tr', 'en', 'Misafirlerim için neden hiçbir şey değişmedi?', 'Yardım'],
        ] as [$choice, $browser, $title, $navigation]) {
            $this->withUnencryptedCookie('zbn_language', $choice)
                ->withHeader('Accept-Language', $browser)
                ->get('/help/nothing-changed-for-my-guests')
                ->assertOk()
                ->assertSee('<html lang="'.$choice.'"', false)
                ->assertSee($title)
                ->assertSee('>'.$navigation.'<', false);
        }
    }

    /**
     * Makale BULUNABİLİR olmalı, HER DİLDE.
     *
     * Kimse adresi tahmin etmez ve bu makaleyi arayan kişi zaten paniktedir:
     * giriş makalesi ona bağlanmıyorsa makale yazılmamış sayılır.
     */
    public function test_the_entry_article_points_at_the_publication_article_in_every_language(): void
    {
        foreach (HelpLibrary::SUPPORTED as $locale) {
            self::assertStringContainsString(
                'href="/help/nothing-changed-for-my-guests"',
                (string) file_get_contents(HelpLibrary::pathFor($locale)),
                "HELP-PUBLICATION-01: [{$locale}] giriş makalesi yayın makalesine bağlanmıyor."
            );
        }
    }

    /**
     * Makale, EKRANDA GERÇEKTEN YAZAN denetim adlarını kullanır — her dilde.
     *
     * Adlar kataloğun kendisinden okunuyor, makaleye elle yazılmıyor: arayüz
     * bir gün "Yayınla"yı başka bir şey yaparsa bu kapı kırmızıya döner ve
     * makale ile ekran ayrışamaz. Ölçüm İngilizceyi de kapsar, çünkü yanlış
     * düğme adı yalnız çeviride değil kaynak metinde de doğar.
     */
    #[DataProvider('supportedLocales')]
    public function test_the_publication_article_names_the_actual_publication_controls(string $locale): void
    {
        $translator = app(TranslationPort::class);
        $article = (string) file_get_contents(
            HelpLibrary::pathFor($locale, 'nothing-changed-for-my-guests')
        );

        foreach ([
            ['workspace', 'workspace.shell.nav.publication'],
            ['workspace', 'workspace.publication.stepper.draft'],
            ['workspace', 'workspace.publication.stepper.live'],
            ['workspace', 'workspace.publication.diff.region'],
            ['workspace', 'workspace.publication.readiness.region'],
            ['workspace', 'workspace.publication.readiness.fix'],
            ['workspace', 'workspace.publication.publishAction.checklistConfirmed'],
            ['workspace', 'workspace.publication.status.publishButton'],
            ['workspace', 'workspace.publication.preview.heading'],
            ['workspace', 'workspace.publication.preview.linkButton'],
            ['workspace', 'workspace.publication.schedule.region'],
            ['workspace', 'workspace.publication.history.title'],
            ['workspace', 'workspace.publication.history.restore'],
            ['menu', 'menu.item.stock.out.short'],
        ] as [$domain, $key]) {
            $label = $translator->translate($domain, $key, $locale);
            self::assertNotSame($key, $label, "Missing [{$locale}] control label for {$key}.");
            self::assertStringContainsString('<strong>'.e($label).'</strong>', $article, $key);
        }
    }

    /** @return list<array{0:string}> */
    public static function supportedLocales(): array
    {
        return array_map(static fn (string $locale): array => [$locale], HelpLibrary::SUPPORTED);
    }

    /**
     * Makale, YAYIN EKRANININ YAPMADIĞI şeyi vaat etmez.
     *
     * Üç yanlış söz bu makalede özellikle ucuzdur ve üçü de kaynakta
     * ölçüldü:
     *
     *   - "Hemen yayınla" kataloğda DURUYOR ama hiçbir ekran onu çizmiyor
     *     (`PublishActionConfigRegion` o kalıcı devre dışı kip seçimini
     *     kaldırdı). Var olmayan bir düğmeyi aramak, tıkanmış sahibi ikinci
     *     kez tıkatır.
     *   - "Önbellek" burada yok: misafir menüsü yayınlanmış snapshot'ı
     *     doğrudan okur (`ShowPublicMenuController`). Bir bekleme süresi
     *     uydurmak, sahibi olmayan bir şeyi beklerken bırakırdı.
     *   - Süre sözü verilmiyor: ne saniye, ne "anında". Ölçülmemiş bir hız
     *     sözü, tutulmadığı ilk gün ürünün tamamına mal olur.
     */
    #[DataProvider('supportedLocales')]
    public function test_the_publication_article_promises_only_what_the_screens_do(string $locale): void
    {
        $translator = app(TranslationPort::class);
        $article = (string) file_get_contents(
            HelpLibrary::pathFor($locale, 'nothing-changed-for-my-guests')
        );

        $unrendered = $translator->translate(
            'workspace',
            'workspace.publication.publishAction.mode.immediate',
            $locale
        );

        self::assertStringNotContainsString(
            '<strong>'.e($unrendered).'</strong>',
            $article,
            "HELP-PUBLICATION-01: [{$locale}] makalesi çizilmeyen bir denetimi ('{$unrendered}') düğme diye gösteriyor."
        );

        foreach (['Preview &amp; publish', 'Preview & publish'] as $inventedControl) {
            self::assertStringNotContainsString($inventedControl, $article, $inventedControl);
        }

        self::assertDoesNotMatchRegularExpression(
            '/(cache|caching|önbelle)/iu',
            $article,
            "HELP-PUBLICATION-01: [{$locale}] makalesi bir önbellek anlatıyor; misafir menüsünde önbellek YOK."
        );

        self::assertDoesNotMatchRegularExpression(
            '/\b(seconds|saniye|instantly|instant|anında)\b/iu',
            $article,
            "HELP-PUBLICATION-01: [{$locale}] makalesi ölçülmemiş bir hız sözü veriyor."
        );
    }
}
