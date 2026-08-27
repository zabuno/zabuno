<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use App\Application\Localization\Port\TranslationPort;
use Tests\TestCase;

/**
 * I18N-SHIPPED-COMPLETE-17 — yarım çevrilmiş bir dil kullanıcıya sunulmaz.
 *
 * Bu kapı gözle görülmüş bir kusurdan doğdu. Uygulama `APP_LOCALE=tr` ile
 * çalışıyordu; `menu` kataloğu çevriliydi, `workspace` kataloğu değildi.
 * Sonuç, tek ekranda yan yana duran iki dildi: "Kategori adı" ile "Build
 * and edit the categories…". Yarım çeviri, çevirisizlikten kötü görünür —
 * çünkü çevirisizlik en azından tutarlıdır.
 *
 * Kural: bir dil `config('i18n.shipped_locales')` içine ancak kataloğu TAM
 * olduğunda girer. Katalogların derlenmiş olması yeterli değildir; altı
 * katalog derleniyor, sunulan bir tane.
 */
final class ShippedLocalesAreCompleteTest extends TestCase
{
    /** @return list<string> */
    private function domains(): array
    {
        $domains = [];

        foreach (glob(base_path('lang/mo/en/*.mo')) ?: [] as $path) {
            $domains[] = basename($path, '.mo');
        }

        sort($domains);

        return $domains;
    }

    public function test_every_shipped_locale_has_a_complete_catalog(): void
    {
        /** @var list<string> $shipped */
        $shipped = config('i18n.shipped_locales');
        $translator = app(TranslationPort::class);

        self::assertNotEmpty($shipped, 'I18N-SHIPPED-COMPLETE-17: en az bir dil sunulmalı.');

        foreach ($shipped as $locale) {
            foreach ($this->domains() as $domain) {
                $missing = $translator->missingCount($domain, $locale);

                self::assertSame(
                    0,
                    $missing,
                    "I18N-SHIPPED-COMPLETE-17: `{$locale}` sunuluyor ama `{$domain}` kataloğunda "
                    ."{$missing} dize eksik. Yarım çevrilmiş dil kullanıcıya gösterilmez: ekran "
                    .'iki dilli çıkar. Ya katalogu tamamla ya da dili `shipped_locales` dışında tut.'
                );
            }
        }
    }

    public function test_the_source_locale_is_always_shipped(): void
    {
        /** @var list<string> $shipped */
        $shipped = config('i18n.shipped_locales');

        self::assertContains(
            (string) config('i18n.source_locale'),
            $shipped,
            'I18N-SHIPPED-COMPLETE-17: kaynak dil her zaman sunulur — geri düşülecek yer odur.'
        );
    }

    public function test_the_running_locale_is_one_that_is_actually_shipped(): void
    {
        /** @var list<string> $shipped */
        $shipped = config('i18n.shipped_locales');

        self::assertContains(
            (string) config('app.locale'),
            $shipped,
            'I18N-SHIPPED-COMPLETE-17: uygulama, sunulmayan bir dilde çalışıyor. '
            .'Bugün tam olarak bu oldu: `APP_LOCALE=tr` ile ekran yarı Türkçe göründü.'
        );
    }
}
