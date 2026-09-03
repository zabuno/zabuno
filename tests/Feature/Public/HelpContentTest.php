<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

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
     * Desteklenen her dilin makalesi VAR OLMALI.
     *
     * Eksik bir dil, o dili seçen kullanıcıya sessizce İngilizce gösterirdi
     * — ve bunu kimse fark etmezdi. Kapı, eksikliği kullanıcıya değil CI'a
     * gösterir.
     */
    #[DataProvider('supportedLocales')]
    public function test_every_supported_language_has_the_article(string $locale): void
    {
        self::assertFileExists(
            HelpLibrary::pathFor($locale),
            "HELP-EVERY-LOCALE-01: [{$locale}] için yardım makalesi yok."
        );
    }

    /** @return list<array{0:string}> */
    public static function supportedLocales(): array
    {
        return array_map(static fn (string $l): array => [$l], HelpLibrary::SUPPORTED);
    }

    public function test_the_reader_gets_their_own_language(): void
    {
        $turkish = $this->withHeaders(['Accept-Language' => 'tr'])->get('/help')->getContent();
        $english = $this->withHeaders(['Accept-Language' => 'en'])->get('/help')->getContent();

        self::assertMatchesRegularExpression('#<html lang="tr"#', $turkish);
        self::assertMatchesRegularExpression('#<html lang="en"#', $english);
        self::assertNotSame($turkish, $english, 'İki dil aynı metni veriyorsa çeviri yoktur.');
    }
}
