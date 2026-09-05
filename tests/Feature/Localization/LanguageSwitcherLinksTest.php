<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use App\Support\Localization\LanguageChoice;
use App\Support\Localization\LanguageSwitcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `docs/120` §5 — dil değiştiricinin SUNUCU tarafı: hangi dil, hangi adres.
 *
 * Bileşenin kendisi bir liste çizer; hangi listeyi çizeceğine burası karar
 * verir. Ayrım kasıtlı: "bu dilin karşılığı var mı" bir veri sorusudur ve
 * tarayıcıda cevaplanamaz.
 *
 * Requirement ID'leri: I18N-SWITCH-SAMEPAGE-11, I18N-SWITCH-NOCOUNTERPART-12,
 * I18N-SWITCH-UNSHIPPED-13, I18N-SWITCH-NINE-14.
 */
final class LanguageSwitcherLinksTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $key, string $locale, string $path): void
    {
        ContentPage::query()->create([
            'page_key' => $key,
            'locale' => $locale,
            'canonical_path' => $path,
            'content_type' => 'product',
            'template_key' => 'product',
            'title' => 'QR menü',
            'priority' => 'P0',
            'publication_status' => PagePublicationStatus::Published->value,
            'is_template' => false,
            'is_external' => false,
            'was_ever_published' => true,
        ]);
    }

    private function switcher(): LanguageSwitcher
    {
        return app(LanguageSwitcher::class);
    }

    /** @param  list<LanguageChoice>  $choices */
    private function choice(array $choices, string $code): LanguageChoice
    {
        foreach ($choices as $choice) {
            if ($choice->language->value === $code) {
                return $choice;
            }
        }

        self::fail("`{$code}` dil değiştiricide hiç yok.");
    }

    // --- I18N-SWITCH-NINE-14 ----------------------------------------------

    /**
     * DOKUZ DİLİN HEPSİ KARAR VERİLMİŞ OLARAK DÖNER.
     *
     * Bir dilin listeden sessizce düşmesi ile "henüz hazır değil" demesi
     * ayrı şeylerdir; ikisine de bileşen karar verir, ama karar için her dil
     * hakkında bir cevap gerekir. Eksik bırakmak, bileşeni "bu dili hiç
     * duymadım" durumuna sokardı.
     */
    public function test_every_one_of_the_nine_languages_gets_an_answer(): void
    {
        $this->page('urun-qr-menu', 'tr', '/tr/urun/qr-menu/');

        $choices = $this->switcher()->choicesFor('urun-qr-menu', 'tr');

        self::assertCount(9, $choices, 'I18N-SWITCH-NINE-14: dokuz dilin hepsi için bir karar dönmeli.');
    }

    // --- I18N-SWITCH-SAMEPAGE-11 ------------------------------------------

    /**
     * DİL DEĞİŞTİRMEK AYNI SAYFADA KALIR.
     *
     * Karşılık `page_key` üzerinden bulunur; ana sayfaya atmak, kullanıcının
     * okuduğu şeyi elinden almaktır ve dili değiştirdiği için cezalandırmak
     * gibi görünür.
     */
    public function test_switching_language_stays_on_the_same_page(): void
    {
        $this->page('urun-qr-menu', 'tr', '/tr/urun/qr-menu/');
        $this->page('urun-qr-menu-en', 'en', '/en/product/qr-menu/');

        $choices = $this->switcher()->choicesFor('urun-qr-menu', 'tr');

        self::assertSame(
            '/en/product/qr-menu/',
            $this->choice($choices, 'en')->href,
            'I18N-SWITCH-SAMEPAGE-11: İngilizce bağlantı aynı sayfanın İngilizcesine gitmeli.'
        );
    }

    public function test_the_current_language_is_marked_and_is_not_a_link_to_elsewhere(): void
    {
        $this->page('urun-qr-menu', 'tr', '/tr/urun/qr-menu/');

        // Bugün sunulan tek dil `en`; ölçüm dil uzayını AÇIKÇA verir, çünkü
        // ölçülen şey sunulan diller listesi değil, aktif dilin işaretlenmesi.
        $current = $this->choice($this->switcher()->choicesFor('urun-qr-menu', 'tr', ['tr', 'en']), 'tr');

        self::assertTrue($current->isCurrent, 'I18N-SWITCH-SAMEPAGE-11: aktif dil işaretlenmedi.');
        self::assertSame('/tr/urun/qr-menu/', $current->href);
    }

    // --- I18N-SWITCH-NOCOUNTERPART-12 -------------------------------------

    /**
     * KARŞILIĞI YOKSA BUNU SÖYLER.
     *
     * Karşılığı olmayan bir dile bağlantı vermek 404 üretirdi; sessizce
     * ana sayfaya bağlamak ise kullanıcıyı kaybederdi. Üçüncü yol dürüst
     * olanıdır: bağlantı yok, sebep yazılı.
     */
    public function test_a_language_without_a_counterpart_page_is_offered_with_a_reason_not_a_broken_link(): void
    {
        $this->page('urun-qr-menu', 'tr', '/tr/urun/qr-menu/');

        // Almanca dil uzayında VAR ama bu sayfanın Almancası yok: iki sebep
        // birbirine karışmasın diye uzay açıkça veriliyor.
        $german = $this->choice($this->switcher()->choicesFor('urun-qr-menu', 'tr', ['tr', 'en', 'de']), 'de');

        self::assertNull($german->href, 'I18N-SWITCH-NOCOUNTERPART-12: olmayan bir sayfaya bağlantı verildi.');
        self::assertFalse($german->isAvailable);
        self::assertSame('no-counterpart', $german->unavailableReason);
    }

    // --- I18N-SWITCH-UNSHIPPED-13 -----------------------------------------

    /**
     * SUNULMAYAN DİL "SEÇİLEBİLİR" GÖRÜNMEZ.
     *
     * `docs/120` §5.8: seçilebilir görünüp yarım çeviri vermek, 2026-09-05'te
     * kapatılan kusurun ta kendisidir. Sebep AYRI kaydedilir — "sayfası yok"
     * ile "bu dil henüz sunulmuyor" kullanıcı için farklı iki cümledir.
     */
    public function test_a_language_outside_the_offered_space_says_so_explicitly(): void
    {
        $this->page('urun-qr-menu', 'tr', '/tr/urun/qr-menu/');
        // Sayfası VAR ama dil uzayında değil: iki sebep karışmasın diye.
        $this->page('urun-qr-menu-ku', 'ku', '/ku/urun/qr-menu/');

        $choices = $this->switcher()->choicesFor('urun-qr-menu', 'tr', ['tr', 'en']);
        $kurdish = $this->choice($choices, 'ku');

        self::assertFalse($kurdish->isAvailable);
        self::assertSame(
            'not-offered',
            $kurdish->unavailableReason,
            'I18N-SWITCH-UNSHIPPED-13: sunulmayan dil ile karşılığı olmayan sayfa aynı sebebe indirgenmiş.'
        );
        self::assertNull($kurdish->href);
    }

    /**
     * BU PAKET ÇEVİRİ KİLİDİNE DOKUNMAZ.
     *
     * Dil değiştiricinin var olması, bir dilin sunulduğu anlamına gelmez.
     * Bugün sunulan tek dil `en` ve bu ölçüm onu dondurur: paket
     * `shipped_locales`'i genişletmedi.
     */
    public function test_the_package_did_not_widen_the_shipped_language_list(): void
    {
        self::assertSame(
            ['en'],
            config('i18n.shipped_locales'),
            'I18N-SWITCH-UNSHIPPED-13: sunulan diller listesi genişlemiş — çeviri kilidi sahibin kararıdır.'
        );
    }
}
