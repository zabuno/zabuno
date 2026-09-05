<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use App\Application\MenuCatalog\UseCase\ResolveServingMenu;
use App\Application\Publication\Dto\GuestMenuView;
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
 */
final class ResolveGuestMenuView
{
    public function __construct(
        private readonly ResolveServingMenu $servingMenu,
        private readonly PublicationRepositoryPort $publications,
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
            return new GuestMenuView($servingMenuId, $publication);
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
}
