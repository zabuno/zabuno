<?php

declare(strict_types=1);

namespace App\Http\Controllers\Workspace;

use App\Application\DataRights\Port\DataRequestRepositoryPort;
use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Arşivi imzalı adresten verir — FF-226, `media.original` ile aynı desen.
 *
 * OTURUM GEREKMEZ, İMZA YETKİDİR: bağlantı e-postayla gider ve sahip onu
 * başka bir cihazda açabilmeli. İmza kısa ömürlüdür ve kiracı + talep
 * kimliğini taşır.
 *
 * ARŞİVİN KENDİ ÖMRÜ İMZADAN AYRIDIR. Geçerli bir imza bile süresi dolmuş
 * bir arşivi açamaz (410): dosya artık diskte olmayabilir ve "süresi
 * doldu" cümlesi, "bulunamadı"dan daha doğrudur.
 */
final class DownloadWorkspaceDataExportController extends Controller
{
    public function __construct(private readonly DataRequestRepositoryPort $requests) {}

    public function __invoke(int $workspace, int $request): SymfonyResponse
    {
        $row = $this->requests->find($request);

        if ($row === null || $row->workspaceId !== $workspace || $row->kind !== DataRequestKind::Export) {
            abort(404);
        }

        if ($row->state === DataRequestState::Expired
            || ($row->availableUntil !== null && Carbon::parse($row->availableUntil)->isPast())) {
            abort(410);
        }

        if ($row->state !== DataRequestState::Ready) {
            abort(404);
        }

        $path = $this->requests->artifactPath($request);
        $disk = Storage::disk((string) (config('data-rights.export.disk') ?: 'local'));

        if ($path === null || ! $disk->exists($path)) {
            abort(404);
        }

        return response($disk->get($path), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
            // Bir çalışma alanının bütün verisi: hiçbir ara önbellek onu
            // saklamamalı.
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
