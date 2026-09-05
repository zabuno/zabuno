<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Localization\DocumentLocale;
use Tests\TestCase;

/**
 * `<html lang>` / `<html dir>` sözleşmesi.
 *
 * Bu değerler dört şablonda elle yazılıydı ve birbirini tutmuyordu. İstemci
 * tarafı çevirici locale'i `<html lang>`'den okuduğu için sabit kodlanmış bir
 * etiket dil seçimini sessizce dondururdu — altı katalog kurulsa bile.
 *
 * Requirement ID'leri: DOC-LOCALE-DERIVED-01, DOC-LOCALE-RTL-02.
 */
final class DocumentLocaleTest extends TestCase
{
    // --- DOC-LOCALE-DERIVED-01 -------------------------------------------

    public function test_every_shell_derives_its_language_from_the_application_locale(): void
    {
        /*
            Dil ARTIK istekte bildirilir (FF-93): `NegotiateLocale` her
            istekte tarayıcının `Accept-Language` başlığından seçer. Testin
            ölçtüğü sözleşme değişmedi — belge dili/yönü şablonun kendi
            kararı değil, locale'den türer — yalnız locale'in nereden geldiği
            gerçek bir istemcinin yaptığı gibi ifade ediliyor.
        */
        /*
            2026-09-05: pazarlık artık SUNULAN dil listesini okuyor
            (`i18n.shipped_locales`). Bu testin ölçtüğü sözleşme değişmedi —
            belge dili şablonun kararı değil, locale'den türer — ama kuralı
            sınamak için sunulan bir dil gerekiyor. Liste sabit yazılmıyor,
            testin kendi kurduğu bağlamda genişletiliyor: sahibin hangi dili
            sunduğu bu sözleşmenin konusu değil.
        */
        config()->set('i18n.shipped_locales', ['en', 'tr', 'ar']);

        $checked = 0;

        foreach (['/', '/app', '/platform'] as $uri) {
            $response = $this->withHeaders(['Accept-Language' => 'tr'])->get($uri);

            // Yalnız gerçekten belge döndüren kabuklar ölçülür: yetki
            // gerektiren yüzeyler 302 ile giriş sayfasına düşer ve o
            // yönlendirme gövdesi bu sözleşmenin konusu değildir.
            if ($response->getStatusCode() !== 200) {
                continue;
            }

            $checked++;

            self::assertStringContainsString(
                'lang="tr"',
                (string) $response->getContent(),
                "DOC-LOCALE-DERIVED-01: {$uri} dili uygulama locale'inden türetmeli."
            );
        }

        // Hiçbiri 200 dönmezse test sessizce "geçer" ve hiçbir şey kanıtlamaz.
        self::assertGreaterThan(
            0,
            $checked,
            'DOC-LOCALE-DERIVED-01: hiçbir kabuk ölçülmedi; test kör kalmış.'
        );
    }

    public function test_the_tag_normalises_underscore_locales(): void
    {
        self::assertSame('pt-BR', DocumentLocale::tag('pt_BR'));
    }

    // --- DOC-LOCALE-RTL-02 ------------------------------------------------

    public function test_direction_is_a_locale_property_not_a_template_decision(): void
    {
        self::assertSame('rtl', DocumentLocale::direction('ar'), 'DOC-LOCALE-RTL-02: Arapça RTL olmalı.');
        self::assertSame('rtl', DocumentLocale::direction('ar-SA'), 'DOC-LOCALE-RTL-02: bölgeli Arapça da RTL olmalı.');

        foreach (['en', 'tr', 'de', 'fr', 'ru'] as $locale) {
            self::assertSame('ltr', DocumentLocale::direction($locale), "DOC-LOCALE-RTL-02: {$locale} LTR olmalı.");
        }
    }

    public function test_an_rtl_locale_reaches_the_rendered_document(): void
    {
        /*
            Dil ARTIK istekte bildirilir (FF-93): `NegotiateLocale` her
            istekte tarayıcının `Accept-Language` başlığından seçer. Testin
            ölçtüğü sözleşme değişmedi — belge dili/yönü şablonun kendi
            kararı değil, locale'den türer — yalnız locale'in nereden geldiği
            gerçek bir istemcinin yaptığı gibi ifade ediliyor.
        */
        config()->set('i18n.shipped_locales', ['en', 'ar']);

        $response = $this->withHeaders(['Accept-Language' => 'ar'])->get('/');
        $response->assertSuccessful();

        self::assertStringContainsString(
            'dir="rtl"',
            (string) $response->getContent(),
            'DOC-LOCALE-RTL-02: RTL locale belgeye ulaşmalı; yön şablon kararı değildir.'
        );
    }
}
