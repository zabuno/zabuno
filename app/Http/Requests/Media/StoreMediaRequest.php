<?php

declare(strict_types=1);

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StoreMediaRequest extends FormRequest
{
    /**
     * Real, decode-free magic-byte check for images — reads the file's own
     * bytes rather than its client extension or reported MIME type, so a
     * spoofed extension (e.g. a PHP payload renamed to .jpg) is rejected
     * before it ever reaches storage (MEDIA-INTAKE-MIME-SPOOF-REJECT-01).
     *
     * SVG BİLEREK YOK (`docs/49` Faz 2 madde 6): bir SVG betik ve dış kaynak
     * taşıyabilir; sanitize eden bir katman olmadan kabul etmek, misafir
     * menüsüne bir betik koymaktır. Slot politikalarında `svg` da bu yüzden
     * yok — kabul edilmeyen bir biçimi "izin verilen" diye listelemek yalan
     * olurdu.
     */
    private const ALLOWED_IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Sınırlar config'den (`media-slots.limits`) — 2026-09-04'e kadar
        // burada sabit `max:51199` (50 MB) yazıyordu, config ise 30 MB
        // diyordu ve `max_megapixels` HİÇ uygulanmıyordu (`docs/98` FF-68).
        $maxKilobytes = (int) ceil(((int) config('media-slots.limits.max_bytes', 30 * 1024 * 1024)) / 1024);
        $maxPixels = ((int) config('media-slots.limits.max_megapixels', 40)) * 1_000_000;

        return [
            'file' => ['required', 'file', 'max:'.$maxKilobytes, function (string $attribute, mixed $value, \Closure $fail) use ($maxPixels): void {
                if (! $value instanceof UploadedFile) {
                    $fail('The file failed magic-byte validation.');

                    return;
                }

                $info = @getimagesize($value->getRealPath());

                if ($info === false || ! in_array($info[2], self::ALLOWED_IMAGE_TYPES, true)) {
                    $fail('The file failed magic-byte validation.');

                    return;
                }

                /*
                    DECOMPRESSION BOMB burada durur — DECODE EDİLMEDEN.

                    `getimagesize` başlıktan okur; 100000×100000 iddia eden
                    bir PNG birkaç yüz bayttır ama açılırsa 40 GB bellek
                    ister. Piksel tavanı, dosyayı çözmeye kalkmadan önce
                    başlıktaki iddiaya bakar (`docs/49` Faz 2, `config/
                    media-slots.php` limits).
                */
                $width = (int) ($info[0] ?? 0);
                $height = (int) ($info[1] ?? 0);

                if ($width < 1 || $height < 1 || $width * $height > $maxPixels) {
                    $fail('The image claims more pixels than this service accepts.');
                }
            }],
            'altText' => ['required', 'string', 'max:255'],
            'slot' => ['required', 'string', 'max:255'],
        ];
    }
}
