<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use App\Application\MenuCatalog\UseCase\ResolveServingMenu;
use App\Application\Publication\Dto\GuestClosedNotice;
use App\Application\Publication\Dto\GuestMenuView;
use App\Application\Publication\Port\GuestOpeningHoursPort;
use App\Application\Publication\Port\PublicationRepositoryPort;

/**
 * MİSAFİRİN EKRANI — "şu an hangi menü" ile "o menünün yayını var mı"
 * sorularının TEK cevabı (FF-139).
 *
 * İki soru ayrı ayrı zaten cevaplanabiliyordu ve tam da bu yüzden ürün
 * yalan söyleyebiliyordu: `ResolveServingMenu` saate göre doğru menüyü
 * seçiyor, denetleyici o menünün yayınını bulamayınca misafire "menü
 * bulunamadı" diyordu. Oysa menü duruyordu; sahip gece menüsünü tanımlayıp
 * saatini vermiş, içeriğini henüz yayınlamamıştı. Masadaki misafir için bu
 * iki cümle aynı değildir: biri restoranın kapandığını, diğeri o saatte
 * servis olmadığını anlatır.
 *
 * SESSİZ YEDEK YOK. Servis dışı saatte çıpa menüsünü göstermek kolay
 * olurdu ve daha da kötü olurdu: misafir 23:00'te kahvaltı fiyatlarına
 * bakıp sipariş verirdi. Ürün bilmediğini söyler, uydurmaz.
 *
 * GÜVENLİK SINIRI — `QR-PUBLIC-404-UNIFORM-01` KORUNUR
 *
 * Dürüst "servis dışı" sayfası yalnız ZATEN 200 dönebilen bir adres için
 * açılır: adresin kendi (çıpa) menüsünün yayını varsa o adres günün başka
 * bir saatinde nasılsa sayfa gösteriyordur, dolayısıyla bu yanıt saldırgana
 * yeni bir bilgi vermez. Hiç yayını olmayan bir adres bugün olduğu gibi
 * bilinmeyen bir anahtardan AYIRT EDİLEMEZ kalır — `null` döner ve çağıran
 * tek tip çıkmaz sokağa düşer.
 *
 * KAPALI ŞUBE ÜÇÜNCÜ BİR HÂL DEĞİLDİR (FF-141)
 *
 * "Servis dışı" ile "kapalı" bilerek ayrı durur ve ayrı davranır: birincisi
 * gösterilecek menünün olmamasıdır ve sayfa menüyü hiç çizmez; ikincisi menü
 * dururken kapının kapalı olmasıdır ve menü ÇİZİLİR. İkisini tek duruma
 * indirmek en kolay kısayoldu ve masadaki misafire en pahalıya patlayanı:
 * gece 23:00'te yarını planlayan kişi menüyü hiç göremezdi.
 *
 * Kararın burada verilmesinin sebebi, iki denetleyicinin (karekod ve kalıcı
 * adres) bir gün ayrışmaması: aynı soruyu iki yerde cevaplarsak birinin
 * cevabı eskir.
 */
final class ResolveGuestMenuView
{
    public function __construct(
        private readonly ResolveServingMenu $servingMenu,
        private readonly PublicationRepositoryPort $publications,
        private readonly GuestOpeningHoursPort $openingHours,
    ) {}

    /**
     * @param  int  $addressedMenuId  Karekodun/kalıcı adresin işaret ettiği menü.
     * @return GuestMenuView|null `null`, misafire tek tip çıkmaz sokak gösterilmeli demektir.
     */
    public function forAddressedMenu(int $workspaceId, int $addressedMenuId): ?GuestMenuView
    {
        $servingMenuId = $this->servingMenu->forMenu($addressedMenuId);
        $publication = $this->publications->current($workspaceId, $servingMenuId);

        if ($publication !== null) {
            return new GuestMenuView(
                $servingMenuId,
                $publication,
                null,
                $this->closedNoticeForMenu($workspaceId, $addressedMenuId),
            );
        }

        /*
            Servis edilen menü ADRESİN KENDİ menüsüyse ve onun da yayını
            yoksa, ortada gösterilecek hiçbir şey yoktur: bu adres bugüne
            kadar da 404 dönüyordu ve dönmeye devam eder. Aksi hâlde tek
            tip 404 kuralı delinir.
        */
        if ($servingMenuId === $addressedMenuId) {
            return null;
        }

        if ($this->publications->current($workspaceId, $addressedMenuId) === null) {
            return null;
        }

        return new GuestMenuView(
            $servingMenuId,
            null,
            $this->nextServiceClock($workspaceId, $addressedMenuId),
        );
    }

    /**
     * Sonraki servisin saati — GÖSTERİLEBİLİR ilk menünün geçişi.
     *
     * Listedeki ilk geçişi körlemesine yazmak yanlış olurdu: sıradaki menü
     * de yayınlanmamış olabilir ve misafire tutulmayacak bir saat vermiş
     * olurduk. Bu yüzden yürüyüş, yayını olan ilk menüde durur; hiçbiri
     * yoksa `null` döner ve ekranda saat YAZMAZ.
     */
    private function nextServiceClock(int $workspaceId, int $addressedMenuId): ?string
    {
        foreach ($this->servingMenu->upcomingSwitchesForMenu($addressedMenuId) as $switch) {
            if ($this->publications->current($workspaceId, $switch['menuId']) !== null) {
                return $switch['clock'];
            }
        }

        return null;
    }

    /**
     * ŞU ANDA KAPALI MIYIZ, VE AÇILIŞ NE ZAMAN?
     *
     * AÇIK OLMASININ SEBEBİ, İKİNCİ BİR HESABIN OLMAMASI (FF-143). Misafirin
     * gördüğü her yüzey bu şeridi çizmek zorunda ve hepsi elinde bir
     * `GuestMenuView` tutmuyor: taslak önizlemesi yayınlanmış sürümü değil
     * TASLAĞI çizer, dolayısıyla `forAddressedMenu` yolundan hiç geçmez.
     * O yüzeye şeridi kendi başına hesaplattırsaydık, aynı şube için bir gün
     * iki farklı cevap üretebilirlerdi — ve ikisinden hangisinin doğru olduğu
     * ancak masadaki misafir yanlış saatte kapıya dayandığında anlaşılırdı.
     *
     * "Menü var mı" sorusu ile "kapı açık mı" sorusu zaten AYRI sorulardır;
     * ikincisi birincinin cevabına ihtiyaç duymaz.
     *
     * Üç sessizlik hâli vardır ve üçü de aynı cevabı verir — şerit ÇİZİLMEZ:
     *
     * 1. Şubenin saati hiç girilmemiş (bugün çalışan şubelerin çoğu). Uydurma
     *    bir varsayılan hafta, sahibin hiç söylemediği bir iddiayı ekranda
     *    doğruymuş gibi gösterirdi.
     * 2. Hafta okunamıyor (yarım kayıt). Misafirin ekranı bir veri hatasının
     *    yeri değildir; o hata sahibin panelinde görünür.
     * 3. Şube o anda AÇIK. Açıkken şerit için boş bir kap bırakmak, sayfanın
     *    üstünde sebepsiz bir boşluk ve ekran okuyucuda boş bir duyuru bölgesi
     *    demekti.
     *
     * GÜN VE DAKİKA BURADA HESAPLANMAZ, PORTTAN GELİR. Bu katman çerçeveden
     * bağımsız kalmak zorunda (`PublicationApplicationBoundaryTest`) ve
     * "şubede şu an saat kaç" sorusunun cevabı bir kütüphane çağrısıdır. Duvar
     * saati de veritabanı satırı gibi DIŞARIDAN girer; içeride yalnız kural
     * çalışır. Şubenin kendi saat dilimi böylece tek bir yerde okunur ve
     * sunucunun saati hiçbir zaman cevaba karışamaz.
     */
    public function closedNoticeForMenu(int $workspaceId, int $addressedMenuId): ?GuestClosedNotice
    {
        $local = $this->openingHours->forMenu($workspaceId, $addressedMenuId);

        if ($local === null) {
            return null;
        }

        if (! $local['hours']->isClosedAt($local['isoWeekday'], $local['minuteOfDay'])) {
            return null;
        }

        $next = $local['hours']->nextOpeningAfter($local['isoWeekday'], $local['minuteOfDay']);

        if ($next === null) {
            // Yedi günü de kapalı şube: kapalı olduğumuz DOĞRU, açılış saati
            // ise veriden çıkmıyor. Cümlenin yarısını söyleyip diğer yarısını
            // uydurmayız.
            return new GuestClosedNotice;
        }

        return new GuestClosedNotice(
            $next['day']->opensClock(),
            $next['day']->day,
            $next['dayOffset'] === 0,
        );
    }
}
