<?php

declare(strict_types=1);

namespace Tests\Feature\Rtl;

use Tests\TestCase;

/**
 * `docs/18` §Security/a11y — "RTL testi en az bir kritik akışta (login)".
 *
 * Stage 1'in şartı RTL **altyapısıdır**, içerik değil: Arapça bir kullanıcı
 * giriş ekranını açtığında belge sağdan sola akmalı ve bunu hiçbir şablonun
 * elle bilmesi gerekmemeli. Arapça çeviri içeriğinin tamlığı Stage 2'dir
 * (`docs/13` §2a) ve bu test onu ölçmez.
 *
 * Requirement ID'leri: RTL-LOGIN-DOCUMENT-01, RTL-LOGIN-DERIVED-02,
 * RTL-LTR-UNAFFECTED-03.
 */
final class CriticalFlowDirectionTest extends TestCase
{
    // --- RTL-LOGIN-DOCUMENT-01 --------------------------------------------

    public function test_the_login_page_flows_right_to_left_for_an_arabic_reader(): void
    {
        /*
            Dil ARTIK istekte bildirilir (FF-93): `NegotiateLocale` her
            istekte tarayıcının `Accept-Language` başlığından seçer. Testin
            ölçtüğü sözleşme değişmedi — belge dili/yönü şablonun kendi
            kararı değil, locale'den türer — yalnız locale'in nereden geldiği
            gerçek bir istemcinin yaptığı gibi ifade ediliyor.
        */
        $response = $this->withHeaders(['Accept-Language' => 'ar'])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_the_login_page_flows_left_to_right_for_a_turkish_reader(): void
    {
        /*
            Dil ARTIK istekte bildirilir (FF-93): `NegotiateLocale` her
            istekte tarayıcının `Accept-Language` başlığından seçer. Testin
            ölçtüğü sözleşme değişmedi — belge dili/yönü şablonun kendi
            kararı değil, locale'den türer — yalnız locale'in nereden geldiği
            gerçek bir istemcinin yaptığı gibi ifade ediliyor.
        */
        $this->withHeaders(['Accept-Language' => 'tr'])->get('/login')
            ->assertStatus(200)
            ->assertSee('<html lang="tr" dir="ltr">', false);
    }

    // --- RTL-LOGIN-DERIVED-02 ---------------------------------------------

    public function test_no_auth_template_decides_direction_by_itself(): void
    {
        // Yön bir locale özelliğidir. Bir şablon `lang="en"` yazarsa o sayfa
        // dilden bağımsız olarak donar ve kimse fark etmez — bu test tam da
        // böyle bir körlük yüzünden var: 2026-08-26'da yedi auth şablonu
        // `<html lang="en">` diyordu ve hiçbirinde `dir` yoktu, yani `docs/18`
        // RTL şartının adıyla andığı giriş akışı hiç RTL değildi.
        //
        // Arama kök dizinle sınırlı DEĞİLDİR: hata tam olarak alt dizindeydi.
        $templates = array_merge(
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/**/*.blade.php')) ?: [],
        );

        $checked = 0;

        foreach ($templates as $view) {
            $source = (string) file_get_contents($view);

            if (! str_contains($source, '<html')) {
                continue; // parça şablon; kendi belge kökü yok
            }

            $checked++;

            self::assertMatchesRegularExpression(
                '/<html lang="\{\{[^}]*\}\}" dir="\{\{[^}]*\}\}">/',
                $source,
                'RTL-LOGIN-DERIVED-02: '.basename($view).' yönü türetmiyor.'
            );
        }

        self::assertGreaterThanOrEqual(
            10,
            $checked,
            'RTL-LOGIN-DERIVED-02: taranan şablon sayısı beklenenden az — arama deseni şablonları kaçırıyor olabilir.'
        );
    }

    // --- RTL-LTR-UNAFFECTED-03 --------------------------------------------

    public function test_the_password_reset_flow_carries_the_same_direction_contract(): void
    {
        /*
            Dil ARTIK istekte bildirilir (FF-93): `NegotiateLocale` her
            istekte tarayıcının `Accept-Language` başlığından seçer. Testin
            ölçtüğü sözleşme değişmedi — belge dili/yönü şablonun kendi
            kararı değil, locale'den türer — yalnız locale'in nereden geldiği
            gerçek bir istemcinin yaptığı gibi ifade ediliyor.
        */
        $this->withHeaders(['Accept-Language' => 'ar'])->get('/forgot-password')
            ->assertStatus(200)
            ->assertSee('dir="rtl"', false);
    }
}
