<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Csv\MenuCsv;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * "Menümü alıp gidebilir miyim?" — `docs/80` (P0-09).
 *
 * Cevabın evet olması, pilot restoranın kilitlenme korkusunu kaldıran şey.
 * GÖRME izni yeter: kendi verisini almak, onu değiştirme yetkisi
 * gerektirmez.
 */
final class ExportMenuCsvController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly AuthorizationPort $authorization,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): SymfonyResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menu);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $filename = 'menu-'.$menu.'-'.now()->format('Y-m-d').'.csv';

        return response(MenuCsv::fromDraftTree($tree), 200, [
            // `charset=utf-8` açıkça yazılır: Türkçe harfler bozuk
            // görünürse sahip menüsünü değil, bir hata sanır.
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
