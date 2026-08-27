<?php

declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Application\Publication\Port\PublicMenuAddressPort;
use App\Domain\Publication\MenuPublicAddress;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentPublicMenuAddress implements PublicMenuAddressPort
{
    /** @return array{key: string, slug: string, menu_id: int, workspace_id: int, brand_name: string, locale: string}|null */
    public function findByQrToken(string $token): ?array
    {
        // Bağ, mevcut QR deposunun kullandığı yolun aynısıdır: kod →
        // güncel hedef → menü. Şubeden kısayol yapmak, aynı şubede birden
        // çok menü olduğu gün sessizce yanlış menüyü gösterirdi.
        $row = $this->baseQuery()
            ->join('qr_destinations', 'qr_destinations.menu_id', '=', 'menus.id')
            ->join('qr_code_current_destinations as cur', 'cur.qr_destination_id', '=', 'qr_destinations.id')
            ->join('qr_codes', 'qr_codes.id', '=', 'cur.qr_code_id')
            ->where('qr_codes.token', $token)
            ->where('qr_codes.state', 'active')
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array{key: string, slug: string, menu_id: int, workspace_id: int, brand_name: string, locale: string}|null */
    public function findByPublicKey(string $key): ?array
    {
        $row = $this->baseQuery()->where('menus.public_key', $key)->first();

        return $row === null ? null : $this->hydrate($row);
    }

    /** @return list<array{key: string, slug: string, published_at: string}> */
    public function indexableMenus(): array
    {
        return $this->baseQuery()
            // Yalnız YAYINLANMIŞ ve indekslenebilir işaretli menüler. Boş
            // veya deneme bir menüyü aramaya açmak, alan adının kalitesini
            // düşürür ve restorana da bir faydası olmaz.
            ->where('menus.is_indexable', true)
            ->join('menu_publication_current_pointers as ptr', 'ptr.menu_id', '=', 'menus.id')
            ->join('menu_publications as pub', 'pub.id', '=', 'ptr.current_publication_id')
            ->where('pub.state', 'published')
            ->addSelect(['pub.published_at as published_at'])
            ->orderBy('menus.public_key')
            ->get()
            ->map(fn (object $row): array => [
                'key' => (string) $row->public_key,
                'slug' => MenuPublicAddress::slugFor($this->displayNameOf($row)),
                'published_at' => (string) $row->published_at,
            ])
            ->all();
    }

    private function baseQuery(): Builder
    {
        // Sütunlar açıkça seçilir: `menus.name` ile `brands.name` aynı adı
        // taşır ve seçilmeyen bir birleştirmede hangisinin kazandığı
        // sürücüye kalır.
        return DB::table('menus')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->join('brands', 'brands.id', '=', 'locations.brand_id')
            ->select([
                'menus.id as id',
                'menus.workspace_id as workspace_id',
                'menus.public_key as public_key',
                'brands.name as brand_name',
                'brands.locale as brand_locale',
                'locations.display_name as display_name',
            ]);
    }

    /** @return array{key: string, slug: string, menu_id: int, workspace_id: int, brand_name: string, locale: string} */
    private function hydrate(object $row): array
    {
        return [
            'key' => (string) $row->public_key,
            'slug' => MenuPublicAddress::slugFor($this->displayNameOf($row)),
            'menu_id' => (int) $row->id,
            'workspace_id' => (int) $row->workspace_id,
            'brand_name' => trim((string) ($row->brand_name ?? '')),
            // Menü içeriğinin dili UYGULAMANIN dili değil, RESTORANIN
            // dilidir: ürün adlarını restoran kendi dilinde yazar.
            'locale' => (string) ($row->brand_locale ?? ''),
        ];
    }

    /** Adres metni markanın ve şubenin adından kurulur. */
    private function displayNameOf(object $row): string
    {
        $brand = trim((string) ($row->brand_name ?? ''));
        $location = trim((string) ($row->display_name ?? ''));

        return trim($brand.' '.$location);
    }
}
