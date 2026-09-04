<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Support\Media\RenditionUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class ServeRenditionController extends Controller
{
    public function __invoke(Request $request, int $rendition, string $fingerprint, string $format): SymfonyResponse
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

        // ETag sağlama toplamının kendisidir (`docs/49` Faz 6 madde 1):
        // içerik aynıysa gövde yeniden gönderilmez — telefonda ikinci
        // açılışta menü fotoğrafları için 304, sıfır bayt.
        $etag = '"'.substr((string) $row->checksum_sha256, 0, 32).'"';

        if (in_array($etag, array_map('trim', explode(',', (string) $request->headers->get('If-None-Match', ''))), true)) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        $disk = Storage::disk((string) $row->disk);

        if (! $disk->exists((string) $row->storage_key)) {
            abort(404);
        }

        $headers = [
            'Content-Type' => (string) $row->mime_type,
            // Adres içeriğin parmak izini taşır; içerik değişirse adres de
            // değişir. Bu yüzden bir yıl ve `immutable` güvenlidir.
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];

        return response($disk->get((string) $row->storage_key), 200, $headers + $this->documentSafetyHeaders((string) $row->mime_type));
    }

    /**
     * SVG için ikinci savunma hattı.
     *
     * Bir PNG tarayıcıda yalnız piksel olur. Bir SVG, kendi adresinden
     * açıldığında BELGE olur: içindeki betik çalışır, dış kaynağa bağlanır,
     * çerez okuyabilir. Alım kapısındaki temizleyici bunu zaten engelliyor
     * (`App\Domain\Media\SvgSanitizer`), ama tek hatta yaslanılmaz: bir gün
     * temizleyicide bir boşluk çıkarsa CSP hâlâ ayaktadır.
     *
     *   - `default-src 'none'` — betik yok, ağ yok, çerçeve yok.
     *   - `style-src 'unsafe-inline'` — SVG'nin kendi içindeki stil
     *     çalışsın diye; dış stil zaten temizleyicide düşüyor.
     *   - `sandbox` — belge kendi köken yetkilerinden yoksun açılır.
     *   - `inline` disposition — menüdeki `<img>` etiketi onu göstersin;
     *     `attachment` olsaydı logo hiç görünmezdi.
     *
     * @return array<string, string>
     */
    private function documentSafetyHeaders(string $mimeType): array
    {
        if (! str_contains(strtolower($mimeType), 'svg')) {
            // Raster türevler her istekte milyonlarca kez gider; onlara
            // gereksiz bayt eklenmez.
            return [];
        }

        return [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Content-Disposition' => 'inline',
        ];
    }
}
