<?php

declare(strict_types=1);

namespace Tests\Feature\MenuCatalog\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Çoklu menü testlerinin ORTAK KURULUMU.
 *
 * Üç ayrı test dosyası aynı çalışma alanı → marka → şube zincirini kuruyor.
 * Zinciri üç kez kopyalamak, bir sütun eklendiğinde üç dosyayı birden
 * kırardı ve hangisinin kanonik olduğu belirsiz kalırdı.
 *
 * Şubenin saat dilimi burada PARAMETRE: saat bazlı menü geçişi ŞUBENİN
 * saatinde çalışmak zorunda ve bunu ancak `Europe/Istanbul` dışında bir
 * saat dilimiyle kanıtlayabiliriz.
 */
trait MultiMenuScaffold
{
    protected function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /** @return array{0:int,1:int} [workspaceId, locationId] */
    protected function workspaceWithLocation(User $owner, string $slugSeed, string $timezone = 'Europe/Istanbul'): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => $timezone,
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Kadıköy Şubesi',
            'country_code' => 'TR',
            'timezone' => $timezone,
            'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId];
    }

    /** Doğrudan satır yazar: menü kataloğu API'sinden bağımsız bir öncül kurar. */
    protected function insertMenu(int $workspaceId, int $locationId, string $name, ?string $publicKey = null, int $sortOrder = 0): int
    {
        return (int) DB::table('menus')->insertGetId([
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => $name,
            'state' => 'draft',
            'public_key' => $publicKey,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function newPublicKey(): string
    {
        return Str::lower(Str::random(10));
    }

    /** Tek kategorili, tek ürünlü bir menü doldurur ve ürünün adını döner. */
    protected function fillMenu(int $workspaceId, int $menuId, string $categoryName, string $productName, int $priceMinorAmount): void
    {
        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId, 'name' => $categoryName, 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => $productName,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => $priceMinorAmount, 'currency_code' => 'TRY',
            'position' => 0, 'is_visible' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Menüyü YAYINLAR: anlık görüntü + "şu anki yayın" işaretçisi.
     *
     * Misafir yüzeyi taslağı DEĞİL yayını okur (`docs/81`). Çoklu menüde bu
     * ayrım kritik: sahip bir gece menüsü tanımlayıp saatini verebilir ama
     * onu yayınlamamış olabilir. O saatte misafirin ne gördüğü ancak yayın
     * durumu ayrı ayrı kurulabildiğinde test edilebilir; bu yüzden yayın
     * satırları burada, menü satırlarından BAĞIMSIZ yazılır.
     *
     * @param  list<array{menuItemId:int,productName:string,priceMinorAmount:int,currencyCode:string}>  $items
     */
    protected function publishMenu(
        int $workspaceId,
        int $locationId,
        int $menuId,
        int $publishedByUserId,
        string $categoryName,
        array $items,
    ): void {
        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => json_encode(['categories' => [['name' => $categoryName, 'menuItems' => $items]]]),
            'published_by' => $publishedByUserId,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
