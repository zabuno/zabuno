<?php

declare(strict_types=1);

namespace App\Http\Controllers\MenuCatalog;

use App\Application\Authorization\Port\AuthorizationPort;
use App\Application\MenuCatalog\Csv\MenuCsvImport;
use App\Application\MenuCatalog\Dto\MenuAuditEntry;
use App\Application\MenuCatalog\Port\MenuAuditPort;
use App\Application\MenuCatalog\Port\MenuCatalogRepositoryPort;
use App\Domain\Authorization\Permission;
use App\Domain\MenuCatalog\MenuAuditAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 60 kalemlik menü, 60 form gönderimi değil — `docs/80` (P0-05 CSV yolu).
 *
 * Aktarım TASLAĞA yazar. Misafirin gördüğü, sahip Yayınla'ya basana kadar
 * değişmez: hiçbir aktarım yolu onay olmadan yayına dokunmaz.
 */
final class ImportMenuCsvController extends Controller
{
    public function __construct(
        private readonly MenuCatalogRepositoryPort $menuCatalog,
        private readonly AuthorizationPort $authorization,
        private readonly MenuAuditPort $audit,
    ) {}

    public function __invoke(Request $request, int $workspace, int $menu): JsonResponse
    {
        $userId = (int) $request->user()->getKey();

        if (! $this->authorization->can($userId, Permission::MenuView, $workspace)) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (! $this->authorization->can($userId, Permission::MenuManage, $workspace)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $tree = $this->menuCatalog->getDraftTree($workspace, $menu);

        if ($tree === null) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $contents = @file_get_contents((string) $request->file('file')->getRealPath());

        if ($contents === false) {
            return response()->json(['message' => 'Dosya okunamadı.'], 422);
        }

        $parsed = MenuCsvImport::parse($contents);

        if ($parsed->rows === [] && $parsed->rejected !== []) {
            // Tek satır bile geçmediyse bu bir "kısmi başarı" değil, bir
            // reddir; 200 dönmek sahibe iş bitti izlenimi verirdi.
            return response()->json([
                'message' => 'Dosyadaki hiçbir satır okunamadı.',
                'importedCategories' => 0,
                'importedItems' => 0,
                'rejectedRows' => $parsed->rejected,
            ], 422);
        }

        $result = $this->menuCatalog->importDraftRows($workspace, $menu, $parsed->rows);

        /*
            DENETİM İZİ (FF-154) — TEK ÖZET SATIRI.

            Aktarım, menünün her fiyatını tek bir dosyayla değiştirebilen
            yoldur; izsiz bırakılsaydı "fiyatı kim değiştirdi" sorusu
            buradan sessizce kaçardı. Öte yandan satır başına kayıt, 60
            kalemlik bir menüde izi tek başına doldurur ve tek tek yapılmış
            fiyat değişikliklerini görünmez kılardı. Kayıt bu yüzden "ne
            kadar" der, "hangi satırlar" demez; ayrıntı, aktarılan dosyanın
            kendisindedir.

            Reddedilen satırlar sayılmaz: yazılmamış bir şey bir değişiklik
            değildir.
        */
        $this->audit->record(MenuAuditEntry::forMenu(
            $workspace,
            $menu,
            $tree->name,
            MenuAuditAction::MenuImported,
            null,
            $result['categories'].' kategori · '.$result['items'].' ürün',
            $userId,
        ));

        return response()->json([
            'importedCategories' => $result['categories'],
            'importedItems' => $result['items'],
            'rejectedRows' => $parsed->rejected,
        ]);
    }
}
