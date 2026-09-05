<?php

declare(strict_types=1);

namespace App\Application\MenuCatalog\Port;

use App\Application\MenuCatalog\Dto\MenuScheduleEntry;
use App\Application\MenuCatalog\Exception\LastMenuForLocationException;
use App\Application\MenuCatalog\Exception\MenuCatalogTenantMismatchException;

/**
 * Bir şubenin menüleri ve günün hangi dakikasının hangi menüye ait olduğu.
 *
 * Sahibin 2026-09-05 kararı (`docs/109` §7.1). Yazma yolları BİLEREK
 * dardır: gün yalnız bu kapıdan bölünür, böylece "boşluk yok / çakışma
 * yok" kuralı tek bir yerde durur ve her yeni ekran onu yeniden keşfetmek
 * zorunda kalmaz.
 */
interface MenuSchedulePort
{
    /**
     * Şubenin menüleri, hap sırasında.
     *
     * @return list<MenuScheduleEntry>
     */
    public function forLocation(int $workspaceId, int $locationId): array;

    /**
     * Şubenin geçiş anları (ham). Misafir yolunun okuduğu tek şey budur.
     *
     * @return list<array{menuId:int,startMinute:int}>
     */
    public function switchesForLocation(int $locationId): array;

    /**
     * ÇIPA MENÜ: şubenin kalıcı genel adresini (`menus.public_key`) taşıyan
     * menü. Hiç geçiş tanımlanmamışsa günün tamamı ona aittir.
     */
    public function anchorMenuId(int $locationId): ?int;

    public function locationIdForMenu(int $menuId): ?int;

    /** Şubenin KENDİ saat dilimi; sunucununki değil (`locations.timezone`). */
    public function timezoneForLocation(int $locationId): string;

    /**
     * Menüye bir servis aralığı verir ve onu rotasyona sokar.
     *
     * `$endMinute === $startMinute` "TÜM GÜN" demektir.
     *
     * Bitiş, menünün kendi sütununa yazılmaz: bitiş anına, O ANI DAHA ÖNCE
     * KAPLAYAN menü için bir geçiş yazılır. "Kahvaltı 07:00–11:00" demek,
     * "11:00'de eskiden ne varsa o geri gelsin" demektir — böylece bitiş
     * bir boşluk açmaz.
     *
     * @throws MenuCatalogTenantMismatchException
     */
    public function setServiceWindow(int $workspaceId, int $menuId, int $startMinute, int $endMinute): void;

    /**
     * Menüyü rotasyondan çıkarır ("Ramazan kapalı").
     *
     * Bıraktığı saatler, kendisinden önce gelen menüye geri döner; delik
     * açılmaz.
     *
     * @throws MenuCatalogTenantMismatchException
     */
    public function clearServiceWindow(int $workspaceId, int $menuId): void;

    /**
     * @throws MenuCatalogTenantMismatchException
     */
    public function rename(int $workspaceId, int $menuId, string $name): MenuScheduleEntry;

    /**
     * Menüyü ve ona bağlı her şeyi siler.
     *
     * Şubenin SON menüsü silinemez: silinebilseydi karekodu okutan misafir
     * boş bir sayfa görürdü. Silinen menü şubenin adres çıpasıysa genel
     * adres hayatta kalan menüye TAŞINIR — basılı kod ölmez.
     *
     * @throws LastMenuForLocationException
     * @throws MenuCatalogTenantMismatchException
     */
    public function delete(int $workspaceId, int $menuId): void;
}
