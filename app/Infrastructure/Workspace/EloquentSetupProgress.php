<?php

declare(strict_types=1);

namespace App\Infrastructure\Workspace;

use App\Application\Workspace\Dto\SetupProgress;
use App\Application\Workspace\Port\SetupProgressPort;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kurulum ilerlemesi VAR OLAN kayıtlardan okunur; yeni tablo yok.
 *
 * "İlk yayına kadar geçen süre" iki damganın farkıdır ve ikisi de zaten
 * yazılıyor: `workspaces.created_at` ve en eski `menu_publications.
 * published_at`. Fark SUNUCUDA alınır — iki damgayı ekrana gönderip
 * tarayıcıda çıkarmak, sonucu kullanıcının kendi saat ayarına bağlardı
 * (`docs/112` §4.1'deki gerekçe; orada da damga değil süre gönderiliyor).
 *
 * Dakika AŞAĞI yuvarlanır ve sıfırın altına inmez: saat farkı ya da elle
 * düzeltilmiş bir damga yüzünden eksi çıkan bir süre, "yayından önce
 * kurulmuş" gibi bir yalanı rapora sokardı.
 */
final class EloquentSetupProgress implements SetupProgressPort
{
    public function forWorkspace(int $workspaceId): SetupProgress
    {
        $brandDone = DB::table('brands')->where('workspace_id', $workspaceId)->exists();
        $locationDone = DB::table('locations')->where('workspace_id', $workspaceId)->exists();

        /*
            Menü adımı ÜRÜN sayar, menü değil (`docs/70` §2.1). Ürün satırı
            menüye kategori üzerinden bağlı; çalışma alanı kimliği menüde.
        */
        $menuItemCount = DB::table('menu_items')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->join('menus', 'menus.id', '=', 'menu_categories.menu_id')
            ->where('menus.workspace_id', $workspaceId)
            ->count();

        $latest = DB::table('menu_publications')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('id')
            ->first(['id', 'version']);

        $firstPublishedAt = DB::table('menu_publications')
            ->where('workspace_id', $workspaceId)
            ->min('published_at');

        $workspaceCreatedAt = DB::table('workspaces')->where('id', $workspaceId)->value('created_at');

        $firstPublishedAfterMinutes = null;

        if (is_string($firstPublishedAt) && is_string($workspaceCreatedAt)) {
            $elapsed = Carbon::parse($workspaceCreatedAt)->diffInMinutes(Carbon::parse($firstPublishedAt), false);
            $firstPublishedAfterMinutes = max(0, (int) floor($elapsed));
        }

        $activeQrCount = DB::table('qr_codes')
            ->where('workspace_id', $workspaceId)
            ->where('state', 'active')
            ->count();

        return new SetupProgress(
            brandDone: $brandDone,
            locationDone: $locationDone,
            menuItemCount: $menuItemCount,
            latestPublicationId: $latest === null ? null : (int) $latest->id,
            latestPublicationVersion: $latest === null ? null : (int) $latest->version,
            activeQrCount: $activeQrCount,
            firstPublishedAfterMinutes: $firstPublishedAfterMinutes,
        );
    }
}
