<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * İmzalı adresten aslı verir. İmza `signed` ara katmanında doğrulanır;
 * burada yalnız kiracı-varlık eşleşmesi ve dosyanın varlığı kontrol edilir.
 * Asıl ÖZELDİR: `no-store`, tarayıcı ya da ara önbellek saklamaz.
 */
final class ServeOriginalController extends Controller
{
    public function __invoke(int $workspace, int $asset): SymfonyResponse
    {
        $row = MediaAsset::withTrashed()
            ->where('id', $asset)
            ->where('workspace_id', $workspace)
            ->first();

        if ($row === null) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists((string) $row->disk_path)) {
            abort(404);
        }

        $filename = str_replace(['"', "\r", "\n"], '', (string) $row->original_name) ?: "media-{$asset}";

        return response($disk->get((string) $row->disk_path), 200, [
            'Content-Type' => (string) $row->mime_type,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
