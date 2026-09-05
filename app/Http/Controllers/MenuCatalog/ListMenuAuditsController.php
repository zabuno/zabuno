<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menü denetim izi — *"dün kebabın fiyatını kim değiştirdi?"* (FF-163).
 *
 * Medya izinin okuma ucuyla (`ListMediaAuditsController`) aynı desendir ve
 * bilerek öyle: iki kapı, aynı sırayla. Çalışma alanını hiç göremeyen için
 * **404** döner, 403 değil — 403 "böyle bir kayıt var ama sana kapalı" der
 * ve bu da bir bilgidir. Çalışma alanının üyesi olup izne sahip olmayana
 * **403** döner; onun için çalışma alanının varlığı zaten sır değildir.
 *
 * YETKİ `menu.manage`: FİYAT GEÇMİŞİ TİCARİ BİR BİLGİDİR.
 *
 * Rol tablosu (`RolePermissions`) bu izni Sahip, Yönetici ve Editör'e verir;
 * Mutfak ve (salt okunur) Üye'de yoktur. Kural tek cümleyle okunur: menüyü
 * DEĞİŞTİREBİLEN, kimin değiştirdiğini de görür. Mutfağın işi alerjen ve
 * "bugün bitti"dir (`docs/109` §6.4 — "başka bir şey görmez"); ürünün kaç
 * liradan kaça çıktığı onun kararına girmez.
 *
 * `menu.view` YETMEZDİ: Mutfak ve Üye o izni taşır ve ikisi de bu listeyi
 * görürdü. `workspace.manage` ise FAZLA DARDI: menüyü fiilen düzenleyen
 * Editör kendi değişikliğinin kaydını göremezdi ve iz, yalnız yukarıdan
 * bakılan bir şeye dönerdi.
 */
final class ListMenuAuditsController extends Controller
{
    /**
     * Bir sayfada kaç kayıt.
     *
     * SUNUCUDA SABİT, istemcinin sorusu değil: `?perPage=100000` ile gelen
     * bir istek, sayfalamanın çözdüğü yükü aynen geri getirirdi. 20, bir
     * ekran dolusu satırdır — sahip aradığı değişikliği çoğunlukla ilk
     * sayfada bulur ve bulamazsa bir sonrakine geçer.
     */
    private const PER_PAGE = 20;

    public function __construct(
        private readonly MenuAuditPort $audits,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::WorkspaceView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $page = max(1, (int) $request->query('page', '1'));
        $trail = $this->audits->recent($workspace, $page, self::PER_PAGE);

        return response()->json([
            'data' => $trail['rows'],
            'page' => $page,
            /*
                Sayfa sayısı EN AZ BİRDİR. Hiç kayıt olmayan bir çalışma
                alanında "0 sayfa" yazmak, ekranı "1 / 0" gibi bir cümle
                kurmaya zorlardı; boş iz zaten kendi cümlesini söylüyor.
            */
            'pageCount' => max(1, (int) ceil($trail['total'] / self::PER_PAGE)),
        ]);
    }
}
