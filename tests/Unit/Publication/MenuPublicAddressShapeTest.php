<?php

declare(strict_types=1);

namespace Tests\Unit\Publication;

use App\Domain\Publication\BusinessType;
use App\Domain\Publication\MenuPublicAddress;
use PHPUnit\Framework\TestCase;

/**
 * TENANT-URL-01 — FF-116, `docs/105` §4.2.
 *
 * Sahibin talebi (2026-09-04): adres insanın anlayacağı, pazarlamaya uygun,
 * arama motoru ve dil modeli dostu olmalı:
 *
 *     /restoran/pasa-doner/menu/ab12cd34ef
 *
 * Bugünkü adres `/menu/ab12cd34ef/pasa-doner` idi: en anlamlı parça (işletme
 * adı) en sonda, en anlamsız parça (10 karakterlik anahtar) ortadaydı. Bir
 * kartvizite yazıldığında ya da telefonda söylendiğinde önce anlamsız kısım
 * geliyordu.
 *
 * Öndeki segment ayrıca bir NAMESPACE'tir: kiracı adresleri kurumsal
 * adreslerden (`/tr/urun/...`) ayrı bir kökte durur, böylece bir işletme
 * slug'ı hiçbir zaman `/pricing` ile çakışamaz.
 */
final class MenuPublicAddressShapeTest extends TestCase
{
    public function test_the_business_type_segment_is_written_in_the_restaurants_own_language(): void
    {
        $turkish = MenuPublicAddress::create('ab12cd34ef', 'Paşa Döner', 'tr');
        $english = MenuPublicAddress::create('ab12cd34ef', 'Pasha Doner', 'en');

        self::assertSame('/restoran/pasa-doner/menu/ab12cd34ef', $turkish->path());
        self::assertSame('/restaurant/pasha-doner/menu/ab12cd34ef', $english->path());
    }

    public function test_an_unsupported_language_falls_back_to_the_international_segment(): void
    {
        // Segmentin çevirisi olmayan bir dilde uydurma bir kelime üretmek,
        // okunmayan bir adres yapmaktır.
        self::assertSame(
            '/restaurant/pasha-doner/menu/ab12cd34ef',
            MenuPublicAddress::create('ab12cd34ef', 'Pasha Doner', 'de')->path(),
        );
    }

    public function test_a_business_with_no_readable_name_still_has_a_working_address(): void
    {
        // Slug uydurulmaz. Adres yine de çalışır; kimlik anahtardadır.
        self::assertSame(
            '/restoran/menu/ab12cd34ef',
            MenuPublicAddress::create('ab12cd34ef', '', 'tr')->path(),
        );
    }

    public function test_the_item_address_is_a_real_path_not_a_fragment(): void
    {
        /*
            Sahibin örneği `#item=101` idi. Fragment sunucuya HİÇ ulaşmaz:
            indekslenmez, ayrı bir görüntüleme olarak ölçülemez ve paylaşılan
            bağlantıda hangi ürün olduğu sunucu tarafından bilinemez. Yol
            segmenti üçünü de yapar.
        */
        $address = MenuPublicAddress::create('ab12cd34ef', 'Paşa Döner', 'tr');

        self::assertSame(
            '/restoran/pasa-doner/menu/ab12cd34ef/urun/101-adana-kebap',
            $address->itemPath(101, 'Adana Kebap'),
        );
    }

    public function test_the_item_segment_is_also_localised(): void
    {
        $address = MenuPublicAddress::create('ab12cd34ef', 'Pasha Doner', 'en');

        self::assertSame(
            '/restaurant/pasha-doner/menu/ab12cd34ef/dish/101-adana-kebab',
            $address->itemPath(101, 'Adana Kebab'),
        );
    }

    public function test_an_item_with_no_readable_name_keeps_its_identity(): void
    {
        $address = MenuPublicAddress::create('ab12cd34ef', 'Paşa Döner', 'tr');

        self::assertSame(
            '/restoran/pasa-doner/menu/ab12cd34ef/urun/101',
            $address->itemPath(101, '🍢'),
        );
    }

    public function test_the_key_stays_lowercase(): void
    {
        /*
            Sahibin örneği `aB245iKj` karışık harfliydi. Karışık harfli anahtar
            üç sorun üretir: telefonda sözlü aktarılamaz ("büyük B, küçük i"),
            URL büyük/küçük harfe duyarlı olduğu için `/AB245/` ile `/ab245/`
            iki ayrı sayfa olur ve kopya içerik doğar, ve basılı kodların
            anahtarları zaten küçük harflidir.
        */
        self::assertTrue(MenuPublicAddress::isKey('ab12cd34ef'));
        self::assertFalse(MenuPublicAddress::isKey('aB245iKj'));
    }

    public function test_every_type_segment_is_a_reserved_word(): void
    {
        // Bir işletme `restoran` slug'ını alırsa `/restoran/restoran/menu/...`
        // gibi çözülemeyen adresler doğar.
        $segments = BusinessType::allSegments();

        self::assertContains('restoran', $segments);
        self::assertContains('restaurant', $segments);
        self::assertSame($segments, array_values(array_unique($segments)));
    }
}
