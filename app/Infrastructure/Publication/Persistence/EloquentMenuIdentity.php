<?php

declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Application\Publication\Port\MenuIdentityPort;
use App\Domain\Publication\MenuIdentity;
use Illuminate\Support\Facades\DB;

final class EloquentMenuIdentity implements MenuIdentityPort
{
    public function brandIdForMenu(int $workspaceId, int $menuId): ?int
    {
        $id = DB::table('menus')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->where('menus.id', $menuId)
            ->where('menus.workspace_id', $workspaceId)
            ->value('locations.brand_id');

        return $id === null ? null : (int) $id;
    }

    public function forMenu(int $workspaceId, int $menuId): ?MenuIdentity
    {
        // Sütunlar açıkça adlandırılır: `menus.name`, `brands.name` ve
        // `locations.display_name` bir arada seçilir ve hangisinin
        // kazandığı sürücüye bırakılamaz.
        $row = DB::table('menus')
            ->join('locations', 'locations.id', '=', 'menus.location_id')
            ->join('brands', 'brands.id', '=', 'locations.brand_id')
            ->where('menus.id', $menuId)
            // Kiracı sınırı burada da geçerlidir: menü kimliği, menünün
            // ait olduğu çalışma alanından okunur.
            ->where('menus.workspace_id', $workspaceId)
            ->select([
                'brands.name as brand_name',
                'brands.contact_phone as contact_phone',
                'brands.primary_color as primary_color',
                'brands.secondary_color as secondary_color',
                'locations.display_name as display_name',
                'locations.address_line1 as address_line1',
                'locations.address_line2 as address_line2',
                'locations.postal_code as postal_code',
                'locations.city as city',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return MenuIdentity::fromParts(
            brandName: (string) ($row->brand_name ?? ''),
            locationName: (string) ($row->display_name ?? ''),
            addressLine1: $row->address_line1 === null ? null : (string) $row->address_line1,
            addressLine2: $row->address_line2 === null ? null : (string) $row->address_line2,
            postalCode: $row->postal_code === null ? null : (string) $row->postal_code,
            city: $row->city === null ? null : (string) $row->city,
            phone: $row->contact_phone === null ? null : (string) $row->contact_phone,
            primaryColor: $row->primary_color === null ? null : (string) $row->primary_color,
            secondaryColor: $row->secondary_color === null ? null : (string) $row->secondary_color,
        );
    }
}
