<?php

declare(strict_types=1);

namespace App\Http\Controllers\PlatformAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kullanıcı görünürlüğü — `docs/122` §3 boşluk 2, dalga Y2.
 *
 * Destek çağrısı hep aynı cümleyle başlar: *"Giremiyorum."* Bugün süperadmin
 * bu cümleye bakacak hiçbir yere sahip değil: kullanıcının hangi çalışma
 * alanlarında olduğu, hangi rolle, e-postasını doğrulamış mı, açık bir
 * oturumu var mı — hiçbiri okunabilir değil.
 *
 * GÖRÜNÜRLÜK, MÜDAHALE DEĞİL. Bu uçta parola sıfırlama/değiştirme,
 * kilitleme ve rol verme YOKTUR. Bir destek aracının ilk sürümüne konan
 * yazma fiili, geri alınamayan ilk kazayı da beraberinde getirir; okumak
 * çağrıların çoğunu zaten kapatır.
 *
 * OLMAYAN ALAN UYDURULMAZ (`docs/109` §8.3/§8.4). Bu üründe bugün bir
 * kullanıcı KİLİDİ kavramı yok — ne `users` tablosunda bir sütun, ne bir
 * yasaklama kaydı. Bu yüzden burada "kilitli değil" diye bir alan da yok:
 * o cümle, ölçülmemiş bir güvenceyi ölçülmüş gibi gösterirdi. Kilit bir gün
 * gerçekten modellenirse alan o zaman doğar.
 *
 * OTURUM OLGUSU KOŞULLUDUR. Oturumlar veritabanında tutulmuyorsa (`file`,
 * `array`, `redis` sürücüleri) `sessions` tablosu boş kalır. O boşluğa bakıp
 * "hiç kimse açık değil" demek, destek görevlisini yanlış yola sokardı; bu
 * yüzden sürücü `database` değilken cevap `known: false` der ve sayı hiç
 * taşınmaz.
 *
 * TAŞINMAYAN ALAN SIZMAZ: parola özeti, `remember_token`, oturum yükü, IP
 * ve tarayıcı imzası bu sorgulara hiç girmez.
 */
final class ListManagedUsersController extends Controller
{
    private const LIMIT = 100;

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('query', ''));

        $builder = DB::table('users')
            ->orderBy('id')
            ->limit(self::LIMIT);

        if ($query !== '') {
            $needle = '%'.mb_strtolower($query).'%';

            $builder->where(static function ($inner) use ($needle): void {
                $inner->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }

        $users = $builder->get(['id', 'name', 'email', 'email_verified_at', 'created_at']);
        $ids = $users->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $memberships = $this->membershipsByUser($ids);
        $platformRoles = $this->platformRolesByUser($ids);
        $sessions = $this->sessionsByUser($ids);

        return response()->json([
            'users' => $users->map(static function (object $user) use ($memberships, $platformRoles, $sessions): array {
                $id = (int) $user->id;

                return [
                    'id' => $id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    // Doğrulanmamış adres `null` kalır. "Doğrulanmadı" gibi
                    // bir cümle yazmak yerine yokluğu yokluk olarak taşımak,
                    // ekranın onu nasıl anlatacağını ekrana bırakır.
                    'emailVerifiedAt' => $user->email_verified_at === null ? null : (string) $user->email_verified_at,
                    'createdAt' => $user->created_at === null ? null : (string) $user->created_at,
                    'platformRoles' => $platformRoles[$id] ?? [],
                    'memberships' => $memberships[$id] ?? [],
                    'sessions' => $sessions[$id] ?? ['known' => false],
                ];
            })->all(),
            'truncated' => $users->count() >= self::LIMIT,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{workspaceId:int, workspaceName:string, workspaceSlug:string, workspaceState:string, role:string}>>
     */
    private function membershipsByUser(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('workspace_memberships as wm')
            ->join('workspaces as w', 'w.id', '=', 'wm.workspace_id')
            ->whereIn('wm.user_id', $ids)
            ->orderBy('w.name')
            ->get(['wm.user_id', 'wm.role', 'w.id', 'w.name', 'w.slug', 'w.state']);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->user_id][] = [
                'workspaceId' => (int) $row->id,
                'workspaceName' => (string) $row->name,
                'workspaceSlug' => (string) $row->slug,
                'workspaceState' => (string) $row->state,
                'role' => (string) $row->role,
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<string>>
     */
    private function platformRolesByUser(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $grouped = [];

        foreach (DB::table('platform_role_assignments')->whereIn('user_id', $ids)->get(['user_id', 'role']) as $row) {
            $grouped[(int) $row->user_id][] = (string) $row->role;
        }

        return $grouped;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{known:bool, active?:int, lastActivity?:int|null}>
     */
    private function sessionsByUser(array $ids): array
    {
        /*
            Sürücü sorusu ÖNCE sorulur. `sessions` tablosu her kurulumda
            migration'la yaratılır ama yalnız `database` sürücüsü onu
            doldurur; tabloya bakmak, sürücüyü sormadan, boş bir tabloyu
            "kimse giriş yapmamış" diye okumak olurdu.
        */
        if ($ids === [] || config('session.driver') !== 'database') {
            return [];
        }

        $table = (string) config('session.table', 'sessions');

        $rows = DB::table($table)
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->get([
                'user_id',
                DB::raw('COUNT(*) as open_count'),
                DB::raw('MAX(last_activity) as latest_activity'),
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->user_id] = [
                'known' => true,
                'active' => (int) $row->open_count,
                // Son etkinlik ham zaman damgasıdır; biçimlendirme ekranın
                // ve okuyanın saat diliminin işidir.
                'lastActivity' => (int) $row->latest_activity,
            ];
        }

        /*
            Sürücü `database` iken hiç satırı olmayan kullanıcı için "0 açık
            oturum" DOĞRU bir ölçümdür ve bilinmeyenle karıştırılmaz.
        */
        foreach ($ids as $id) {
            $grouped[$id] ??= ['known' => true, 'active' => 0, 'lastActivity' => null];
        }

        return $grouped;
    }
}
