<?php

declare(strict_types=1);

namespace App\Application\Publication\Port;

use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;

/**
 * MİSAFİRİN "ŞU ANDA AÇIK MISINIZ" SORUSUNUN VERİ UCU (FF-141).
 *
 * NEDEN SAHİBİN KENDİ DEPOSU KULLANILMIYOR
 * ----------------------------------------
 * `LocationRepositoryPort::findByWorkspaceAndId` aynı haftayı zaten
 * döndürüyor ve ilk bakışta bu arayüz gereksiz görünür. Ama o kapı SAHİBİN
 * panelinin kapısıdır: şubenin adresini, posta kodunu ve `dining_tables`
 * üstünde bir sayım alt sorgusunu da beraberinde getirir. Buradaki yüzey
 * kimlik doğrulamasızdır ve her karekod okutmasında çalışır; bir şeridi
 * çizmek için masa saymak, en sıcak yolun üstüne sebepsiz bir maliyet
 * koymak olurdu.
 *
 * İkinci ve daha önemli sebep, HATANIN ANLAMI: yarım yazılmış bir hafta
 * (yedi günün altısı) `WeeklyOpeningHours::fromArray` tarafından reddedilir.
 * Sahibin ekranında bu bir HATADIR ve görünmelidir; masadaki misafirin
 * ekranında ise 500 demektir. Aynı kapıdan geçselerdi, birini susturmak
 * diğerini de susturacaktı. Bu yüzden buranın sözleşmesi ayrıdır:
 * **cevaplayamıyorsam `null` derim ve ekranda hiçbir şey çizilmez.**
 */
interface GuestOpeningHoursPort
{
    /**
     * Menünün bağlı olduğu şubenin haftası VE o şubede şu an gün/saat kaç.
     *
     * Menüden sorulmasının sebebi, misafir yolunun elinde yalnız adresin
     * işaret ettiği menünün olmasıdır (`docs/109` §7.1: adres şubeye
     * götürür, saat menüyü seçer). Şube kimliğini çağırana çıkarttırmak,
     * aynı birleştirmeyi iki denetleyicide tekrar ettirirdi.
     *
     * `$workspaceId` kimlikten türetilebilirdi ve bilerek türetilmiyor:
     * kiracı koşulu sorgunun kendisinde durursa, bir gün yanlış bir menü
     * kimliği geçirilse bile komşunun saatleri okunamaz.
     *
     * NEDEN "ŞU AN" DA BU KAPIDAN GİRİYOR
     * -----------------------------------
     * Uygulama katmanı çerçeveden bağımsızdır ve öyle kalmak zorundadır
     * (`PublicationApplicationBoundaryTest`). "Şubede şu an saat kaç"
     * sorusunun cevabı ise bir kütüphane çağrısıdır: duvar saati, tıpkı
     * veritabanı satırı gibi DIŞARIDAN gelen bir olgudur. Aynı kapıdan
     * girmesinin ikinci faydası, şubenin saat diliminin TEK bir yerde
     * okunması: iki ayrı yerde okunsaydı biri gün, diğeri dakika için farklı
     * bir ana bakabilirdi — ve bu ancak gece yarısında, yılda birkaç kez
     * yanlış cevap verirdi.
     *
     * `isoWeekday` ISO-8601'dir (1 = Pazartesi … 7 = Pazar) ve `minuteOfDay`
     * gün başından itibaren dakikadır; ikisi de ŞUBENİN saatinde. `null`
     * ise "söylenmemiş ya da okunamadı" demektir ve şerit hiç çizilmez.
     *
     * @return array{hours: WeeklyOpeningHours, isoWeekday: int, minuteOfDay: int}|null
     */
    public function forMenu(int $workspaceId, int $menuId): ?array;
}
