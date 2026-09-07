<?php

declare(strict_types=1);

namespace App\Application\Publication\UseCase;

use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Publication\Dto\PublicationRecord;
use App\Domain\Entitlement\Entitlement;
use App\Domain\Publication\GuestRichMedia;

/**
 * "BU MİSAFİR FOTOĞRAFLARI GÖRÜR MÜ?" — tek cevap, tek yer
 * (`docs/114` §3 Dalga 6, `docs/122` Y6).
 *
 * ═══ ÖNCE DONMUŞ HAK, YOKSA CANLI PLAN ═══
 *
 * Sıra `ShowPublicMenuController::grantsOrdering()` ile aynıdır ve aynı olmak
 * zorundadır: masadaki basılı karekod aynı kâğıttır ve sahip planını
 * düşürdüğünde o kâğıdın gösterdiği yayın değişmez. Plan değişikliği BİR
 * SONRAKİ yayında etkisini gösterir.
 *
 * BU DEPO O KUSURU BİR KEZ YAŞADI: `EloquentPublicationRepository::current()`
 * kendi `PublicationRecord`'unu kurarken donmuş hakkı taşımayı atlamıştı ve
 * misafirin gördüğü yayının planı HER ZAMAN `null` sanılıyordu — yani her
 * istek sessizce canlı plana düşüyordu. Kusur görünmezdi çünkü canlı plan
 * çoğu zaman aynı cevabı verir; yalnız plan DEĞİŞTİĞİNDE ayrışır. Bu yüzden
 * `GuestRichMediaTest` iki yönü de dondurur: düşen plan fotoğrafı almaz,
 * yükselen plan da eski yayına fotoğraf koymaz.
 *
 * `null` = bu alan eklenmeden ÖNCE yapılmış bir yayın. Geriye dönük bir hak
 * uydurmayız; okuyan taraf canlı plana düşer ve bunu açıkça yapar.
 *
 * ═══ KARARIN YERİ NEDEN BURASI ═══
 *
 * Misafirin gördüğü yayın üç yüzeyden çiziliyor (karekod, kalıcı adres, ürün
 * sayfası) ve hepsi `ResolveGuestMenuView`'dan geçiyor. Kararı oraya bağlamak,
 * dördüncü yüzey eklendiği gün sessizce unutulacak bir hatırlatmayı ortadan
 * kaldırır — dondurmanın kendisi de tam bu sebeple denetleyicilerde değil
 * deponun içinde duruyor.
 */
final class ApplyGuestRichMedia
{
    public function __construct(private readonly EntitlementRepositoryPort $entitlements) {}

    /**
     * Yayının misafire çizilecek hâli — hak yoksa fotoğrafları çıkarılmış.
     *
     * Hak varsa NESNE AYNEN döner: gereksiz bir kopya, "bir şey değişti"
     * izlenimi verirdi.
     */
    public function forPublication(PublicationRecord $publication): PublicationRecord
    {
        if ($this->grants($publication->workspaceId, $publication->entitlementKeys)) {
            return $publication;
        }

        return new PublicationRecord(
            $publication->id,
            $publication->workspaceId,
            $publication->menuId,
            $publication->locationId,
            $publication->version,
            $publication->state,
            $publication->publishedAt,
            GuestRichMedia::withoutImages($publication->snapshot),
            // Donmuş hak OLDUĞU GİBİ taşınır: fotoğrafı sakladık diye hakkın
            // kaydını da silmek, aynı yayının sipariş hakkını da kaybettirirdi.
            $publication->entitlementKeys,
        );
    }

    /**
     * Elinde yayın değil ÇIPLAK bir anlık görüntü olan yüzey için — bugün
     * yalnız taslak önizlemesi.
     *
     * Önizleme bir yayın değildir, dolayısıyla donmuş hakkı yoktur ve CANLI
     * plana bakar. Bu doğrudur ve önizlemenin var olma sebebidir: sahip
     * "yayınlasam misafir ne görecek?" diye soruyor, ve bugün yayınlarsa
     * donacak olan hak bugünün planıdır.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function forDraftSnapshot(int $workspaceId, array $snapshot): array
    {
        return $this->grants($workspaceId, null)
            ? $snapshot
            : GuestRichMedia::withoutImages($snapshot);
    }

    /** @param  list<string>|null  $frozen */
    private function grants(int $workspaceId, ?array $frozen): bool
    {
        if ($frozen !== null) {
            return in_array(Entitlement::MenuRichMedia->value, $frozen, true);
        }

        return $this->entitlements->forWorkspace($workspaceId)->grants(Entitlement::MenuRichMedia);
    }
}
