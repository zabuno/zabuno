<?php

declare(strict_types=1);

namespace App\Application\QrDestination\UseCase;

use App\Application\Publication\UseCase\ResolveGuestMenuView;
use App\Application\QrDestination\Port\QrCodeRepositoryPort;
use App\Domain\QrDestination\QrToken;
use InvalidArgumentException;

/**
 * "BU MİSAFİR HANGİ MASADAN GELDİ, VE ORAYA GERİ DÖNEBİLİR Mİ?" — GUEST-B2.
 *
 * ═══ NEDEN BÖYLE BİR ŞEY VAR ═══
 *
 * Ürünün adresi KANONİKTİR ve masasızdır: paylaşılan bağlantı, arama sonucu
 * ve sitemap hepsi aynı adresi görmek zorunda (`docs/105` §4.3). Ama masadaki
 * misafir o adrese MASADAN geliyor ve dönüş bağlantısı onu masasız menüye
 * bırakıyordu. Masasız menüde sepet HİÇ çizilmez — çünkü o sayfanın bir masası
 * yoktur — ve misafirin topladığı sepet cihazında dururken EKRANDAN kayboluyordu.
 * Masada bu, sepeti kaybetmekle aynı şeydir (denetim af51bbe1).
 *
 * Çözüm adresi değil, BAĞLAMI taşır: masadan kurulan ürün bağlantısı belirteci
 * açık bir sorgu olarak yanında götürür, bu sınıf onu doğrular ve yalnız
 * doğrulanmışsa dönüş yolunu masaya çevirir.
 *
 * ═══ SORGU BİR YETKİ DEĞİL, BİR İDDİADIR ═══
 *
 * Sorgudaki değer misafirin telefonundan gelir ve orada her şey yazılabilir.
 * Bu yüzden buradan ASLA "misafirin verdiği adres" dönmez — dönen tek şey
 * `/menu/{token}` biçiminde, DOĞRULANMIŞ bir belirteçten kurulmuş kendi
 * adresimizdir. Serbest bir dönüş adresi kabul etseydik, bir ürün sayfası
 * istenen her yere açılan bir kapı olurdu.
 *
 * Dört kapı da geçilmek zorunda ve hepsinin cevabı tek: geçemeyen `null`
 * alır, çağıran da güvenli kanonik görünüme düşer. Bu `null` bir hata değildir
 * — aramadan gelen misafirin normal hâli tam olarak budur.
 *
 * 1. BİÇİM. `QrToken` deseni tutmayan bir dize hiç sorgulanmaz.
 * 2. KOD YAŞIYOR MU. Kapatılmış bir kod aktif değildir: masadaki kâğıt
 *    söküldüyse dönüş yolu da onunla kapanır.
 * 3. AYNI KİRACI. Başka bir restoranın gerçek ve aktif masası, bu menüde
 *    hiçbir şey açmaz. Kabul edilseydi bir restoranın ürün sayfası, başka bir
 *    restoranın masasına dönüş bağlantısı basardı.
 * 4. AYNI MENÜ — HEM ÇIPA HEM SERVİS EDİLEN. Kodun çıpası bu adresin çıpası
 *    olmalı (kod başka bir menüye taşınmışsa artık bu masa değildir), VE o
 *    çıpadan o an servis edilen menü sayfanın okuduğu menüyle aynı olmalı.
 *    İkincisi ilkinden bugün türetilebilir; yine de ayrıca soruluyor, çünkü
 *    "bugün türetilebilir" bir kural değil bir tesadüftür ve saate göre menü
 *    değiştiren bir üründe sessizce bozulur.
 */
final class ResolveGuestTableReturn
{
    /**
     * Bağlamın taşındığı sorgu adı.
     *
     * Kısa ve DİLDEN BAĞIMSIZ: yol segmentleri işletmenin diline göre yazılır
     * (`/restoran/` ↔ `/restaurant/`), ama bu sorgu adı her dilde aynı kalmak
     * zorunda — yoksa aynı bağlantı iki dilde iki farklı anahtar taşırdı.
     */
    public const QUERY = 'qr';

    public function __construct(
        private readonly QrCodeRepositoryPort $qrCodes,
        private readonly ResolveGuestMenuView $guestMenuView,
    ) {}

    /**
     * @param  string|null  $candidate  Misafirin taşıdığı iddia — doğrulanmamış.
     * @param  int  $workspaceId  Sayfanın kiracısı.
     * @param  int  $addressedMenuId  Sayfanın adresinin ÇIPA menüsü.
     * @param  int  $servingMenuId  Sayfanın ürünü GERÇEKTEN okuduğu menü.
     * @return string|null DOĞRULANMIŞ belirtecin kendisi, ya da `null`
     *                     (güvenli kanonik dönüş). Dönen dize misafirin
     *                     gönderdiği bayt değil, depodan okunan kaydın
     *                     belirtecidir.
     */
    public function tokenForItemPage(
        ?string $candidate,
        int $workspaceId,
        int $addressedMenuId,
        int $servingMenuId,
    ): ?string {
        if ($candidate === null || $candidate === '') {
            return null;
        }

        try {
            $token = QrToken::fromString($candidate);
        } catch (InvalidArgumentException) {
            return null;
        }

        $record = $this->qrCodes->findActiveByToken($token->value());

        if ($record === null) {
            return null;
        }

        if ($record->workspaceId !== $workspaceId || $record->menuId !== $addressedMenuId) {
            return null;
        }

        $view = $this->guestMenuView->forAddressedMenu($record->workspaceId, $record->menuId);

        if ($view === null || $view->publication === null || $view->servingMenuId !== $servingMenuId) {
            return null;
        }

        // DEPODAN okunan belirteç döner, istekten gelen dize değil: aradaki
        // fark bugün yok (biri diğerine eşit olmasa buraya gelinmezdi) ama
        // dönüş yolunu misafirin baytından kurmamak bir alışkanlık olarak
        // kalmalı.
        return $record->token;
    }
}
