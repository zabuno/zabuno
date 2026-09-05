<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Support\Localization\SiteText;
use Tests\TestCase;

/**
 * FF-169 — hesap verisi talebinin TANIMLI BİR YOLU var (`docs/110` P0-09).
 *
 * ÜRÜN SORUNU. Denetim P0-09'u kısmen kapattı: sahip menüsünü CSV olarak
 * alıp gidebiliyor (`MenuCsvRoundTripTest`). Ama "hesabımdaki her şeyi
 * istiyorum" dediğinde ürün ona bir adres göstermiyordu. `/kvkk` yalnız
 * "nitelikli hukuki inceleme bekleniyor" yazan bir sayfaydı; talebi nereye
 * yazacağı hiçbir yerde yazmıyordu.
 *
 * BU PAKET METİN YAZMAZ, MEKANİZMA KURAR. Bir e-posta adresi, bir süre
 * taahhüdü ya da bir yasal hüküm UYDURULMAZ — bunlar sahibin ve hukukun
 * kararıdır. Kurulan şey şudur: talebin yürüyeceği yol üründe gerçekten
 * vardır (`/contact`, saklayan ve hız sınırlı), ve sahibin girmesi gereken
 * tek olgu (talebin iletileceği adres) girilmemişse sayfa bunu DÜRÜSTÇE
 * söyler. Sahte bir adres basmak, sahibin cevap gelmeyen bir kutuya
 * yazmasına yol açardı.
 */
final class AccountDataRequestPathTest extends TestCase
{
    private function siteText(string $key): string
    {
        return app(SiteText::class)->get($key, 'en');
    }

    // --- Yol VARDIR ve gerçekten yürüyen bir yoldur ------------------------

    public function test_the_kvkk_page_names_a_defined_path_for_an_account_data_request(): void
    {
        $response = $this->get('/kvkk');

        $response->assertOk();
        $response->assertSee($this->siteText('site.legal.dataRequest.heading'), false);
        $response->assertSee($this->siteText('site.legal.dataRequest.body'), false);
    }

    /**
     * Bağlantı VAR OLAN bir uca gider.
     *
     * `/contact` bu üründe çalışan, mesajı saklayan ve hız sınırlı bir yol
     * (`StoreContactMessageController`). Talebi oraya bağlamak, ikinci bir
     * yol icat etmekten dürüsttür: icat edilen yolun karşılığı olmazdı.
     */
    public function test_the_request_path_points_at_the_contact_route_that_actually_exists(): void
    {
        /*
            Altbilgide de bir `/contact` bağlantısı var; onu görmek bu
            sözleşmeyi kanıtlamaz. Kanıt, BÖLÜMÜN KENDİ çağrısıdır — yalnız
            burada geçen katalog dizesi.
        */
        $this->get('/kvkk')
            ->assertOk()
            ->assertSee($this->siteText('site.legal.dataRequest.cta'), false)
            ->assertSee('href="/contact"', false);

        // Ucun kendisi de ayakta: kırık bir bağlantı, tanımlı bir yol değildir.
        $this->get('/contact')->assertOk();
    }

    // --- Girilmemiş bilgi UYDURULMAZ, söylenir -----------------------------

    public function test_when_no_address_is_configured_the_page_says_so_instead_of_inventing_one(): void
    {
        config(['legal.data_request.address' => null]);

        $this->get('/kvkk')
            ->assertOk()
            ->assertSee($this->siteText('site.legal.dataRequest.addressMissing'), false);
    }

    public function test_the_configured_address_is_the_one_shown(): void
    {
        config(['legal.data_request.address' => 'Zabuno · Örnek Mah. 1 · İstanbul']);

        $this->get('/kvkk')
            ->assertOk()
            ->assertSee('Zabuno · Örnek Mah. 1 · İstanbul', false)
            // Girilmiş bilgi varken "girilmedi" cümlesi görünmemeli.
            ->assertDontSee($this->siteText('site.legal.dataRequest.addressMissing'), false);
    }

    // --- Bölüm VERİ sayfasına aittir ---------------------------------------

    /**
     * Kullanım koşulları bir veri koruma sayfası DEĞİLDİR.
     *
     * Aynı bölümü üç yasal sayfaya birden basmak, sahibe talebin üç ayrı
     * yolu varmış izlenimi verirdi; tek bir yol vardır ve o yol burada.
     */
    public function test_the_section_belongs_to_the_data_protection_page_only(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertDontSee($this->siteText('site.legal.dataRequest.heading'), false);
    }
}
