<?php

declare(strict_types=1);

namespace App\Domain\Publication;

/**
 * ZENGİN GÖRSEL YÜZEYİ — hakkı olmayan kiracıda fotoğraf ne olur?
 *
 * Sahibin cümlesi (`docs/114` §2): *"Anonim kullanıcı frontend tarafında
 * resimleri görmeyebilir, veya pricing table'da üst pakete geçerse
 * görebilir."* Yani misafirin gördüğü şey restoranın planına bağlıdır.
 *
 * ═══ HAK YOKSA FOTOĞRAF YOK — VE BU DÜRÜSTÇE OLUR ═══
 *
 * Kaldırma İŞARETLEMEDEN ÖNCE, veride yapılır: `image` bloğu anlık
 * görüntüden çıkarılır ve şablon hiç fotoğraf olmamış gibi çizer. Şablonda
 * bir `@if` ile gizlemek üç şeyi bozardı:
 *
 * 1. Boş bir çerçeve ya da kırık görsel yer tutucusu kalırdı. Misafir
 *    müşteri değildir; ona eksik bir şey göstermek, ona satılmamış bir
 *    şeyin reklamını yapmaktır.
 * 2. Yapılandırılmış veri (`MenuStructuredData`) fotoğrafı yine bildirirdi:
 *    arama motoruna gösterilmeyen bir görseli vaat etmek olurdu.
 * 3. Ürün sayfasının kalite kapısı (`ShowPublicMenuItemController::
 *    hasSomethingToSay`) fotoğrafı "anlatacak şey" saymaya devam ederdi ve
 *    menüde, açıldığında satırın kopyasından başka bir şey göstermeyen bir
 *    bağlantı çizerdi.
 *
 * Veriden çıkarınca üçü de kendiliğinden doğru olur; şablonun yanlış
 * yapabileceği bir şey kalmaz.
 *
 * ═══ "YÜKSELTİN" YAZILMAZ ═══
 *
 * Masadaki misafir restoranın müşterisidir, bizim değil. Ona plan
 * satmak, restoranın masasını bizim satış kanalımıza çevirirdi. Eksikliği
 * gören taraf sahiptir ve o fiyat sayfasında okur.
 *
 * ═══ MARKA LOGOSU BU KAPININ DIŞINDADIR ═══
 *
 * `identity.logo` bir ürün fotoğrafı değil, sayfanın kim olduğudur: kaldırmak
 * menüyü isimsiz bırakırdı ve bu bir SÜS değil, temel yolculuğun parçasıdır
 * (`Entitlement`: kademe bir yeteneği açar, temel yolculuğu kapatmaz).
 * Markanın kendi görünümünü YÜKLEMEK zaten ayrı bir hakka bağlı
 * (`branding.custom`, `UpdateBrandController`).
 */
final class GuestRichMedia
{
    /**
     * Anlık görüntünün ürün fotoğrafsız hâli.
     *
     * Anahtar SİLİNİR, boşaltılmaz: `'image' => null` bırakmak, şablonun
     * `empty()` ile geçtiği ama yapılandırılmış verinin bir gün `array_key_
     * exists` ile takıldığı ikinci bir gerçek üretirdi.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function withoutImages(array $snapshot): array
    {
        if (! isset($snapshot['categories']) || ! is_array($snapshot['categories'])) {
            return $snapshot;
        }

        foreach ($snapshot['categories'] as $categoryIndex => $category) {
            if (! is_array($category) || ! isset($category['menuItems']) || ! is_array($category['menuItems'])) {
                continue;
            }

            foreach ($category['menuItems'] as $itemIndex => $item) {
                if (is_array($item)) {
                    unset($snapshot['categories'][$categoryIndex]['menuItems'][$itemIndex]['image']);
                }
            }
        }

        return $snapshot;
    }
}
