<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Support\Site\HelpDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FF-240-01…10 — İletişim, Hakkımızda ve Yardım girişinin OLGUNLUĞU.
 *
 * ═══ ÖLÇÜLEN BOŞLUK ═══
 *
 * Üç sayfa da bugün 200 dönüyordu ve hiçbiri yanlış bir şey söylemiyordu.
 * Eksik olan doğruluk değil, KULLANILABİLİRLİKTİ:
 *
 *   • telefon ve e-posta düz metindi — telefonundan bakan bir restoran
 *     sahibi numarayı elle kopyalamak zorundaydı;
 *   • üç sayfanın hiçbirinde atlama bağlantısının hedefi yoktu, yani
 *     klavyeyle gezen biri "İçeriğe atla"ya basınca hiçbir yere gitmiyordu;
 *   • Hakkımızda, bu satışı bağlayan belgelere hiç bağlanmıyordu;
 *   • Yardım, okuyucuya başka ne yazıldığını göstermiyordu ve aradığını
 *     bulamayanı bir cümleyle uğurluyordu.
 *
 * ═══ BU TEST NE ÖLÇMEZ ═══
 *
 * Estetiği, hiyerarşiyi ve kelime seçimini. Onlar insan kararıdır; dar
 * ekran geometrisini ise `scripts/mobile-ux-audit` gerçek bir düzen
 * motorunda ölçer. Buradaki her iddia, sunucunun ürettiği HTML'de
 * DOĞRULANABİLİR bir olgudur.
 */
final class CorporatePageMaturityTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string|null> */
    private const COMPANY = [
        'legal_name' => 'Örnek Yazılım A.Ş.',
        'address' => 'Örnek Mah. 1, İstanbul',
        'mersis' => '0123456789012345',
        'tax_office' => 'Kadıköy',
        'tax_number' => '1234567890',
        'email' => 'legal@example.test',
        'phone' => '+90 212 000 00 00',
    ];

    private function html(string $path): string
    {
        return (string) $this->get($path)->assertOk()->getContent();
    }

    /** @return list<array{0:string}> */
    public static function maturedPaths(): array
    {
        return [['/about'], ['/contact'], ['/help']];
    }

    // --- FF-240-01: atlama bağlantısının hedefi ---------------------------

    /**
     * Kabuk her sayfada "İçeriğe atla" çiziyor; hedefi olmayan bir atlama
     * bağlantısı, basıldığında hiçbir şey yapmayan bir bağlantıdır — ve
     * klavyeyle gezen biri için sayfanın ilk etkileşimidir.
     */
    #[DataProvider('maturedPaths')]
    public function test_the_skip_link_has_exactly_one_target(string $path): void
    {
        $html = $this->html($path);

        self::assertStringContainsString('href="#main-content"', $html);
        self::assertSame(
            1,
            preg_match_all('#\bid="main-content"#', $html),
            "FF-240-01: [{$path}] atlama hedefi tam olarak bir kez bulunmalı."
        );
    }

    // --- FF-240-02/03: dokunulabilir kimlik satırları ---------------------

    /**
     * Bir iletişim sayfasında en sık yapılan iş ARAMAKTIR.
     *
     * `tel:` numarası AYRAÇSIZ yazılır (RFC 3966): görünen değer sahibin
     * yazdığı gibi okunur kalır, çevrilen numara ise boşluksuzdur.
     */
    public function test_the_phone_and_the_email_can_be_used_from_a_phone(): void
    {
        config(['legal.company' => self::COMPANY]);

        foreach (['/about', '/contact'] as $path) {
            $html = $this->html($path);

            self::assertStringContainsString('href="mailto:legal@example.test"', $html,
                "FF-240-02: [{$path}] e-posta adresi dokunulabilir değil.");
            self::assertStringContainsString('href="tel:+902120000000"', $html,
                "FF-240-03: [{$path}] telefon numarası çevrilebilir değil.");

            // Görünen değer DEĞİŞMEDİ: okunur numara, çevrilebilir bağlantı.
            self::assertStringContainsString('+90 212 000 00 00', $html);
        }
    }

    /**
     * Girilmemiş bir alan bağlantı OLMAZ — hiçbir yere gitmeyen bir
     * bağlantı, olmayan bir bağlantıdan kötüdür. Satır yine de atlanmaz,
     * "girilmedi" diye yazılır (FF-216 kararı korunuyor).
     */
    public function test_a_missing_field_never_becomes_a_link(): void
    {
        config(['legal.company' => array_fill_keys(array_keys(self::COMPANY), null)]);

        foreach (['/about', '/contact'] as $path) {
            $html = $this->html($path);

            self::assertStringNotContainsString('mailto:', $html,
                "FF-240-02: [{$path}] girilmemiş e-posta bağlantıya çevrilmiş.");
            self::assertStringNotContainsString('href="tel:', $html);
            self::assertStringContainsString('Not entered yet', $html);
            self::assertStringContainsString('data-company-identity="incomplete"', $html);
        }
    }

    /** Rakam taşımayan bir "telefon" çevrilemez ve bağlantı olmaz. */
    public function test_a_phone_without_digits_is_not_dialable(): void
    {
        config(['legal.company' => ['phone' => 'call the office'] + self::COMPANY]);

        self::assertStringNotContainsString('href="tel:', $this->html('/contact'));
    }

    // --- FF-240-04/05: Hakkımızda'nın sözleşme listesi --------------------

    /**
     * Ödeme kuruluşunun incelemesi "hangi sözleşme geçerli" sorusunu bu
     * sayfada arar. Liste ELLE YAZILMAZ: altbilgideki yasal grubun AYNI
     * dizisinden süzülür, dolayısıyla ikisi ayrışamaz.
     */
    public function test_about_links_to_every_agreement_the_footer_names(): void
    {
        $html = $this->html('/about');

        preg_match('#<main\b.*?</main>#s', $html, $main);
        preg_match('#<footer\b.*?</footer>#s', $html, $footer);

        $inBody = $this->hrefsIn($main[0] ?? '');
        $inFooter = $this->hrefsIn($footer[0] ?? '');

        foreach (['/terms', '/privacy', '/kvkk', '/distance-sales', '/pre-information', '/delivery', '/refund-policy', '/cookies'] as $agreement) {
            self::assertContains($agreement, $inFooter, "Altbilgi [{$agreement}] belgesini adlandırmıyor.");
            self::assertContains($agreement, $inBody,
                "FF-240-04: /about gövdesi [{$agreement}] belgesine bağlanmıyor — ödemeden önce okunabilmeli.");
        }
    }

    /** Gövdedeki her bağlantı 200 döner — `MP-06` ile aynı kapı, aynı cümle. */
    #[DataProvider('maturedPaths')]
    public function test_every_link_in_the_body_resolves(string $path): void
    {
        preg_match('#<main\b.*?</main>#s', $this->html($path), $main);

        foreach ($this->hrefsIn($main[0] ?? '') as $target) {
            $status = $this->get($target)->getStatusCode();

            self::assertContains($status, [200, 302],
                "FF-240-05: [{$path}] gövdesindeki [{$target}] {$status} döndü — ölü bağlantı yasak.");
        }
    }

    // --- FF-240-06: iletişim formunun okunabilirliği ----------------------

    /**
     * Her alanın kendi etiketi ve — ipucu varsa — o ipucuna `aria-describedby`
     * bağı vardır. Ekran okuyucu ipucunu alanın parçası olarak okur; sayfada
     * başıboş duran bir cümle olarak değil.
     */
    public function test_every_contact_field_is_labelled_and_its_hint_is_bound(): void
    {
        $html = $this->html('/contact');

        foreach (['contact-name', 'contact-email', 'contact-message'] as $field) {
            self::assertStringContainsString('for="'.$field.'"', $html,
                "FF-240-06: [{$field}] alanının etiketi yok.");
            self::assertStringContainsString('id="'.$field.'"', $html);
        }

        foreach (['contact-email-hint', 'contact-message-hint'] as $hint) {
            self::assertStringContainsString('aria-describedby="'.$hint.'"', $html);
            self::assertStringContainsString('id="'.$hint.'"', $html);
        }

        // Bal küpü KORUNDU: insan görmez, ekran okuyucu ve klavye de değmez.
        self::assertStringContainsString('tabindex="-1"', $html);
    }

    /**
     * Formdan ÖNCE, cevabı zaten yazılmış bir soruyu sormamak için yardıma
     * bir yol vardır; formdan SONRA, yazılanın ne olacağı yazar.
     */
    public function test_contact_offers_the_written_answer_before_the_form_and_names_the_privacy_notice(): void
    {
        $html = $this->html('/contact');

        self::assertStringContainsString('href="/help"', $html);
        self::assertStringContainsString('href="/privacy"', $html);

        $before = strpos($html, 'id="contact-before-heading"');
        $form = strpos($html, 'id="contact-form-heading"');

        self::assertIsInt($before);
        self::assertIsInt($form);
        self::assertLessThan($form, $before,
            'FF-240-06: "yazmadan önce" bölümü formdan SONRA çizilmiş — sırası cevabı beklemeyi önlemek.');
    }

    // --- FF-240-07/08: yardım girişi --------------------------------------

    /**
     * Aradığını bulamayan biri sayfanın sonunda ne yapacağını bilir.
     *
     * Panel bir HIZ SÖZÜ VERMEZ: yanıt taahhüdü ayrı bir anahtardır ve
     * yalnız sahibi bir sayı girdiyse çizilir (`docs/125` §3).
     */
    public function test_help_ends_with_a_way_out(): void
    {
        $html = $this->html('/help');

        self::assertStringContainsString('id="help-stuck-heading"', $html);
        self::assertStringContainsString('href="/contact"', $html);
    }

    /**
     * TEK MAKALEDE DİZİN ÇİZİLMEZ.
     *
     * Tek maddelik bir dizin, okuyucunun zaten üzerinde olduğu sayfayı
     * listeler. Bu test bir DAVRANIŞI dondurur, bir sayıyı değil: ikinci
     * makale yazıldığı gün bant kendiliğinden belirir ve bu test onu
     * `HelpDirectory` üzerinden ölçer.
     */
    public function test_the_index_band_appears_only_when_there_is_more_than_one_article(): void
    {
        $articles = HelpDirectory::articles();
        $html = $this->html('/help');

        if (count($articles) > 1) {
            self::assertStringContainsString('site-help-index', $html);

            foreach ($articles as $article) {
                self::assertStringContainsString('href="'.$article['path'].'"', $html,
                    "FF-240-07: yazılmış makale [{$article['slug']}] dizinde yok.");
            }

            return;
        }

        self::assertStringNotContainsString('site-help-index', $html,
            'FF-240-07: tek makale varken dizin bandı çizilmemeli.');
    }

    /** Dizin, ikinci bir makale konduğunda gerçekten çizilir. */
    public function test_a_second_article_produces_a_two_row_index(): void
    {
        $root = storage_path('framework/testing/help-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($root);
        File::put($root.'/'.HelpDirectory::ENTRY.'.blade.php',
            "@section('title', 'Your first 15 minutes')\n@section('description', 'Import, print, change a price.')\n");
        File::put($root.'/who-can-do-what.blade.php',
            "@section('title', 'Who can do what')\n@section('description', 'Roles decide what each person sees.')\n");

        try {
            $articles = HelpDirectory::articles($root);
        } finally {
            File::deleteDirectory($root);
        }

        self::assertCount(2, $articles);

        // Giriş makalesi HER ZAMAN ilk ve adresi `/help`tir.
        self::assertSame(HelpDirectory::ENTRY, $articles[0]['slug']);
        self::assertSame('/help', $articles[0]['path']);
        self::assertSame('/help/who-can-do-what', $articles[1]['path']);

        // Ad ve tarif MAKALENİN KENDİSİNDEN gelir, katalogdan değil.
        self::assertSame('Who can do what', $articles[1]['title']);
        self::assertSame('Roles decide what each person sees.', $articles[1]['summary']);
    }

    /** Başlığı okunamayan makale LİSTELENMEZ — ve kapı bunu görür. */
    public function test_an_article_without_a_declared_title_is_not_listed(): void
    {
        $root = storage_path('framework/testing/help-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($root);
        File::put($root.'/nameless.blade.php', "<main>No declaration here.</main>\n");

        try {
            $articles = HelpDirectory::articles($root);
        } finally {
            File::deleteDirectory($root);
        }

        self::assertSame([], $articles);
    }

    /**
     * FF-240-08 — YAZILMIŞ HER MAKALENİN ADI VAR VE ADRESİ AÇILIYOR.
     *
     * `NAV-REGISTRY-05` "404'e bağlantı yok" der; bu onun eşidir: yazılmış
     * ama açılmayan sayfa yok. Eksiklik ziyaretçiye değil CI'a görünür.
     */
    public function test_every_written_article_has_a_name_and_an_address_that_opens(): void
    {
        $files = glob(resource_path('help/'.HelpDirectory::SOURCE.'/*.blade.php')) ?: [];
        $articles = HelpDirectory::articles();

        self::assertCount(count($files), $articles,
            'FF-240-08: bir makalenin başlık bildirimi okunamadı — dizinde görünmez.');

        foreach ($articles as $article) {
            self::assertSame(200, $this->get($article['path'])->getStatusCode(),
                "FF-240-08: [{$article['path']}] açılmıyor.");
        }
    }

    // --- FF-240-09: kırılma noktası ve emoji yok --------------------------

    /**
     * `MP-05` `/about`ı listesinde saymıyor; bu paket o sayfaya bir bölüm
     * eklediği için burada aynı soru sorulur. Kırılma noktası jetonu
     * kullanmak, ikinci bir düzen yazmak demektir (`docs/118` E2).
     */
    #[DataProvider('maturedPaths')]
    public function test_no_matured_page_is_breakpoint_gated(string $path): void
    {
        preg_match_all('/class="([^"]*)"/', $this->html($path), $matches);

        foreach ($matches[1] as $classList) {
            self::assertDoesNotMatchRegularExpression('/(^|\s)(sm|md|lg|xl|2xl):/', $classList,
                "FF-240-09: [{$path}] kırılma noktası: {$classList}");
        }
    }

    /** @return list<string> */
    private function hrefsIn(string $fragment): array
    {
        preg_match_all('#href="(/[a-z0-9/-]*)"#', $fragment, $matches);

        return array_values(array_unique($matches[1]));
    }
}
