<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\UseCase\ManageSubscriptions;
use App\Application\Workspace\Port\WorkspaceAuditTrailPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Kiracı ayrıntısı — `docs/122` §3 boşluk 1, dalga Y2.
 *
 * Ölçülen durum: `/platform` bir çalışma alanı LİSTESİ çiziyordu ve satıra
 * tıklayınca hiçbir şey olmuyordu. Süperadminin ilk günkü sorusu ise tek
 * satırlık değildir — *"kaç şubesi var, hangi menüleri var, aboneliği ne
 * durumda, dün orada ne oldu?"* — ve bugün bu dört soru dört ayrı tabloya
 * elle SQL atmakla cevaplanıyor.
 *
 * YENİ VERİ ÜRETMEZ. Buradaki her sayı ve her satır zaten yazılan bir
 * kayıttır; eksik olan tek şey onları bir arada okuyan bir yerdi.
 *
 * SALT OKUNUR, KAPISIZ. Bu yüzeyde kiracı verisini değiştiren bir fiil ve
 * kiracı olarak oturum açan bir kapı YOKTUR. İkincisi bilerek: `docs/122`
 * §5, impersonation'ı en tehlikeli süperadmin yeteneği sayar ve Y7'ye
 * bırakır — kolay bir impersonation, bir gün kimsenin hatırlamadığı bir
 * erişim olur.
 *
 * LİSTE KIRPILIR, SAYI KIRPILMAZ. Elli şubeli bir zincirde ekranın elli
 * satır çizmesi gerekmez; ama "kaç şube var" sorusuna listenin uzunluğuyla
 * cevap vermek yalan olurdu. Bu yüzden `usage` ayrı SAYILIR ve kırpılan her
 * liste `listsTruncated` altında kendini bildirir (`docs/109` §8.3).
 *
 * OLAY AKIŞI ÜRETİLMEZ, OKUNUR. Var olan `WorkspaceAuditTrailPort`
 * çağrılır; ikinci bir birleştirici yazmak, bir gün iki farklı "son olaylar"
 * listesi üretirdi.
 */
final class ShowManagedWorkspaceController extends Controller
{
    /** Ekranda çizilecek satır sayısı tavanı; sayımlar bundan bağımsızdır. */
    private const LIST_LIMIT = 50;

    /** Kiracı zaman çizgisinde gösterilecek son olay sayısı. */
    private const EVENT_LIMIT = 20;

    public function __construct(
        private readonly ManageSubscriptions $subscriptions,
        private readonly WorkspaceAuditTrailPort $auditTrail,
    ) {}

    public function __invoke(int $workspace): JsonResponse
    {
        $row = DB::table('workspaces')
            ->where('id', $workspace)
            ->first(['id', 'name', 'slug', 'state', 'created_at']);

        if ($row === null) {
            // Enumeration-safe: var olmayan kiracı ile yetkisiz erişim aynı
            // cevabı verir (`EnsurePlatformSuperAdmin` ile aynı dil).
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $subscription = $this->subscriptions->current($workspace)->toArray();
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $brand = DB::table('brands')
            ->where('workspace_id', $workspace)
            ->first(['name', 'slug', 'locale', 'currency']);

        $locations = DB::table('locations')
            ->where('workspace_id', $workspace)
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'display_name', 'city', 'country_code', 'timezone'])
            ->map(static fn (object $location): array => [
                'id' => (int) $location->id,
                'displayName' => (string) $location->display_name,
                'city' => (string) $location->city,
                'countryCode' => (string) $location->country_code,
                'timezone' => $location->timezone === null ? null : (string) $location->timezone,
            ])
            ->all();

        /*
            Menünün ŞUBESİ menünün YANINDA durur. Üç şubeli bir işletmede
            "Ana Menü" tek başına hangi menünün değiştiğini söylemez; adres
            çubuğuna bakmadan cevaplanamayan bir liste, destek çağrısını
            kısaltmaz.
        */
        $menus = DB::table('menus as m')
            ->leftJoin('locations as l', 'l.id', '=', 'm.location_id')
            ->where('m.workspace_id', $workspace)
            ->orderBy('m.id')
            ->limit(self::LIST_LIMIT)
            ->get(['m.id', 'm.name', 'm.state', 'm.location_id', 'l.display_name'])
            ->map(static fn (object $menu): array => [
                'id' => (int) $menu->id,
                'name' => (string) $menu->name,
                'state' => (string) $menu->state,
                'locationId' => $menu->location_id === null ? null : (int) $menu->location_id,
                'locationName' => $menu->display_name === null ? null : (string) $menu->display_name,
            ])
            ->all();

        /*
            Ekip: rol ve E-POSTA birlikte. Bir ekipte iki "Mehmet" olabilir
            ve "Mehmet sahibi" cümlesi hiçbir destek çağrısını kapatmaz.
            Parola özeti, jeton ya da oturum bilgisi bu sorguya HİÇ girmez —
            taşınmayan alan sızmaz.
        */
        $members = DB::table('workspace_memberships as wm')
            ->join('users as u', 'u.id', '=', 'wm.user_id')
            ->where('wm.workspace_id', $workspace)
            ->orderBy('wm.id')
            ->limit(self::LIST_LIMIT)
            ->get(['u.id', 'u.name', 'u.email', 'wm.role', 'wm.created_at'])
            ->map(static fn (object $member): array => [
                'userId' => (int) $member->id,
                'name' => (string) $member->name,
                'email' => (string) $member->email,
                'role' => (string) $member->role,
                'since' => $member->created_at === null ? null : (string) $member->created_at,
            ])
            ->all();

        $usage = [
            'locations' => DB::table('locations')->where('workspace_id', $workspace)->count(),
            'menus' => DB::table('menus')->where('workspace_id', $workspace)->count(),
            'products' => DB::table('products')->where('workspace_id', $workspace)->count(),
            'mediaAssets' => DB::table('media_assets')->where('workspace_id', $workspace)->whereNull('deleted_at')->count(),
            'members' => DB::table('workspace_memberships')->where('workspace_id', $workspace)->count(),
        ];

        return response()->json([
            'workspace' => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'state' => (string) $row->state,
                'createdAt' => $row->created_at === null ? null : (string) $row->created_at,
            ],
            // Markası olmayan bir çalışma alanı vardır (kurulum yarım
            // kalmıştır) ve bu bir hata değildir; boş bir kart uydurmak yerine
            // alan `null` bırakılır.
            'brand' => $brand === null ? null : [
                'name' => (string) $brand->name,
                'slug' => (string) $brand->slug,
                'locale' => (string) $brand->locale,
                'currency' => (string) $brand->currency,
            ],
            'subscription' => $subscription,
            'usage' => $usage,
            'locations' => $locations,
            'menus' => $menus,
            'members' => $members,
            'listsTruncated' => [
                'locations' => $usage['locations'] > self::LIST_LIMIT,
                'menus' => $usage['menus'] > self::LIST_LIMIT,
                'members' => $usage['members'] > self::LIST_LIMIT,
            ],
            'recentEvents' => $this->auditTrail->recent($workspace, self::EVENT_LIMIT),
        ]);
    }
}
