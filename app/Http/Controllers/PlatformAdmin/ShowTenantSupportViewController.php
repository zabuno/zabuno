<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Application\Billing\Exception\WorkspaceNotFoundException;
use App\Application\Billing\UseCase\ManageSubscriptions;
use App\Application\Support\Port\SupportAccessPort;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * DESTEK GÖRÜNÜMÜ — `docs/122` §3 boşluk 3, dalga Y7.
 *
 * Ölçülen cümle şuydu: *"Müşteri arıyor, ekranında ne var?"* sorusunun
 * cevabı yok. Bu uç o cevabı, **kiracının gözüne hiç girmeden** verir.
 *
 * BU EKRANIN VAROLUŞ SEBEBİ IMPERSONATION'I GEREKSİZ KILMAKTIR. Destek
 * çağrılarının çoğu — "menüm görünmüyor", "karekod boş sayfa açıyor",
 * "siparişler gelmiyor", "faturam ne oldu" — kiracının verisine onun
 * gözüyle bakmayı GEREKTİRMEZ; gereken şey, o verinin BUGÜNKÜ DURUMUDUR.
 * `docs/122` §5 impersonation'ı en tehlikeli süperadmin yeteneği sayar;
 * en tehlikeli yeteneği en son çare yapmanın yolu, ondan önce gelen çareyi
 * gerçekten kurmaktır.
 *
 * BULGULAR UYDURULMAZ, TÜRETİLİR. `findings` listesindeki her kod bir
 * SORGUNUN sonucudur; hiçbiri tahmin, öneri ya da olasılık taşımaz. Cevabı
 * bilinmeyen bir soru listeye hiç girmez — "belki şudur" diyen bir destek
 * ekranı, destek görevlisini yanlış yere bakmaya yollar.
 *
 * MİSAFİR ADRESİ AÇIKÇA VERİLİR ve bu bir sızıntı değildir: karekod
 * masadadır, adres zaten herkese açıktır ve destek görevlisinin misafirin
 * gördüğü sayfayı açması, oturum açmayı değil bir bağlantıya tıklamayı
 * gerektirir. "Müşterinin ekranında ne var" sorusunun en dürüst cevabı,
 * müşterinin ekranının kendisidir.
 *
 * KİRACININ İÇERİĞİ ÇIKMAZ. Ürün adları, fiyatlar, misafir yorumları ve
 * sipariş içerikleri bu cevapta YOKTUR: sorulan şey "ne var", "ne yazıyor"
 * değil. Taşınmayan alan sızmaz.
 */
final class ShowTenantSupportViewController extends Controller
{
    /** Ekranda çizilecek satır tavanı; sayımlar bundan bağımsızdır. */
    private const LIST_LIMIT = 50;

    public function __construct(
        private readonly ManageSubscriptions $subscriptions,
        private readonly SupportAccessPort $supportAccess,
    ) {}

    public function __invoke(int $workspace): JsonResponse
    {
        $row = DB::table('workspaces')->where('id', $workspace)->first(['id', 'name', 'slug', 'state']);

        if ($row === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        try {
            $subscription = $this->subscriptions->current($workspace)->toArray();
        } catch (WorkspaceNotFoundException) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $locations = $this->locations($workspace);
        $findings = $this->findings((string) $row->state, $subscription, $locations);

        return response()->json([
            'workspace' => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'slug' => (string) $row->slug,
                'state' => (string) $row->state,
            ],
            'subscription' => $subscription,
            'locations' => $locations,
            'supportRequests' => $this->supportRequests($workspace),
            'accessHistory' => array_map(
                static fn ($session): array => $session->toArray(),
                $this->supportAccess->forWorkspace($workspace, 10),
            ),
            'findings' => $findings,
        ]);
    }

    /**
     * Şube şube: misafirin ulaştığı adres, o adresin gösterdiği menü, o
     * menünün yayında olup olmadığı ve sipariş şalteri.
     *
     * @return list<array<string, mixed>>
     */
    private function locations(int $workspaceId): array
    {
        $locations = DB::table('locations')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get(['id', 'display_name', 'accepts_orders']);

        return $locations->map(function (object $location) use ($workspaceId): array {
            $locationId = (int) $location->id;

            /*
                Karekod, gösterdiği menüyle BİRLİKTE okunur. Ayrı ayrı
                okunsaydı, "karekod var ve menü yayında" cümlesi kurulabilir
                ama "karekodun gösterdiği menü yayında değil" cümlesi
                kurulamazdı — ve müşterinin şikâyeti çoğu zaman tam olarak
                ikincisidir.
            */
            $codes = DB::table('qr_codes as c')
                ->leftJoin('qr_code_current_destinations as p', 'p.qr_code_id', '=', 'c.id')
                ->leftJoin('qr_destinations as d', 'd.id', '=', 'p.qr_destination_id')
                ->leftJoin('menus as m', 'm.id', '=', 'd.menu_id')
                ->leftJoin('menu_publication_current_pointers as cp', 'cp.menu_id', '=', 'm.id')
                ->leftJoin('menu_publications as pub', 'pub.id', '=', 'cp.current_publication_id')
                ->where('c.workspace_id', $workspaceId)
                ->where('c.location_id', $locationId)
                ->orderBy('c.id')
                ->limit(self::LIST_LIMIT)
                ->get([
                    'c.id', 'c.token', 'c.state',
                    'd.destination_type', 'm.id as menu_id', 'm.name as menu_name',
                    'pub.version as published_version', 'pub.published_at',
                ])
                ->map(static fn (object $code): array => [
                    'id' => (int) $code->id,
                    // Misafirin adresi — karekod zaten masada, adres zaten açık.
                    'guestUrl' => url('/q/'.(string) $code->token),
                    'state' => (string) $code->state,
                    'destinationType' => $code->destination_type === null ? null : (string) $code->destination_type,
                    'menuId' => $code->menu_id === null ? null : (int) $code->menu_id,
                    'menuName' => $code->menu_name === null ? null : (string) $code->menu_name,
                    'publishedVersion' => $code->published_version === null ? null : (int) $code->published_version,
                    'publishedAt' => $code->published_at === null ? null : (string) $code->published_at,
                ])
                ->all();

            return [
                'id' => $locationId,
                'displayName' => (string) $location->display_name,
                'acceptsOrders' => (bool) $location->accepts_orders,
                'menuCount' => DB::table('menus')->where('location_id', $locationId)->count(),
                'qrCodes' => $codes,
            ];
        })->all();
    }

    /**
     * Bu kiracının açık destek talepleri — mesaj gövdesi YOK.
     *
     * Gövde kuyruk ekranında okunur (`GET /api/admin/support-requests`);
     * burada sorulan soru "bu restoranın açık bir talebi var mı", "ne
     * yazmış" değil.
     *
     * @return list<array<string, mixed>>
     */
    private function supportRequests(int $workspaceId): array
    {
        return DB::table('support_requests')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['reference', 'subject', 'status', 'received_at', 'first_response_at'])
            ->map(static fn (object $request): array => [
                'reference' => (string) $request->reference,
                'subject' => (string) $request->subject,
                'status' => (string) $request->status,
                'receivedAt' => (string) $request->received_at,
                'firstResponseAt' => $request->first_response_at === null ? null : (string) $request->first_response_at,
            ])
            ->all();
    }

    /**
     * "Menüm görünmüyor" sorusunun kod listesi.
     *
     * Her kod bir ÖLÇÜMDÜR. Sıra kasıtlı: en yukarıdaki bulgu, aşağıdaki
     * her şeyi anlamsız kılan bulgudur — askıya alınmış bir çalışma alanında
     * karekodun hangi menüyü gösterdiğini tartışmanın anlamı yok.
     *
     * @param  array<string, mixed>  $subscription
     * @param  list<array<string, mixed>>  $locations
     * @return list<array{code:string, locationId:?int}>
     */
    private function findings(string $state, array $subscription, array $locations): array
    {
        $findings = [];

        if ($state !== 'active' && $state !== 'onboarding') {
            $findings[] = ['code' => 'workspace_not_serving', 'locationId' => null];
        }

        if (($subscription['state'] ?? null) !== 'active') {
            $findings[] = ['code' => 'subscription_not_active', 'locationId' => null];
        }

        if ($locations === []) {
            $findings[] = ['code' => 'no_location', 'locationId' => null];

            return $findings;
        }

        foreach ($locations as $location) {
            $locationId = (int) $location['id'];
            $codes = $location['qrCodes'];

            if ($location['menuCount'] === 0) {
                $findings[] = ['code' => 'location_has_no_menu', 'locationId' => $locationId];
            }

            if ($codes === []) {
                $findings[] = ['code' => 'location_has_no_qr', 'locationId' => $locationId];

                continue;
            }

            foreach ($codes as $code) {
                if ($code['state'] !== 'active') {
                    $findings[] = ['code' => 'qr_disabled', 'locationId' => $locationId];

                    continue;
                }

                if ($code['menuId'] === null) {
                    // Karekod var, tarandığında gidecek bir yeri yok.
                    $findings[] = ['code' => 'qr_has_no_destination', 'locationId' => $locationId];

                    continue;
                }

                if ($code['publishedVersion'] === null) {
                    // Menü var, karekod onu gösteriyor, ama hiç yayına
                    // çıkmamış: masadaki karekod boş bir sayfa açar.
                    $findings[] = ['code' => 'qr_menu_never_published', 'locationId' => $locationId];
                }
            }
        }

        return $findings;
    }
}
