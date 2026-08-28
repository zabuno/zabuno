<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Support\Media\RenditionUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ServeRenditionController extends Controller
{
    public function __invoke(int $rendition, string $fingerprint, string $format): SymfonyResponse
    {
        $row = DB::table('media_renditions')
            ->join('media_blobs', 'media_blobs.id', '=', 'media_renditions.media_blob_id')
            ->where('media_renditions.id', $rendition)
            ->where('media_renditions.format', $format)
            ->select([
                'media_blobs.disk as disk',
                'media_blobs.storage_key as storage_key',
                'media_blobs.mime_type as mime_type',
                'media_blobs.checksum_sha256 as checksum_sha256',
            ])
            ->first();

        if ($row === null || ! RenditionUrl::matches($fingerprint, (string) $row->checksum_sha256)) {
            abort(404);
        }

        $disk = Storage::disk((string) $row->disk);

        if (! $disk->exists((string) $row->storage_key)) {
            abort(404);
        }

        return response($disk->get((string) $row->storage_key), 200, [
            'Content-Type' => (string) $row->mime_type,
            // Adres içeriğin parmak izini taşır; içerik değişirse adres de
            // değişir. Bu yüzden bir yıl ve `immutable` güvenlidir.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
