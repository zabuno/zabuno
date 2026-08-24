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
        return [
            'file' => ['required', 'file', 'max:51199', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    $fail('The file failed magic-byte validation.');

                    return;
                }

                $info = @getimagesize($value->getRealPath());

                if ($info === false || ! in_array($info[2], self::ALLOWED_IMAGE_TYPES, true)) {
                    $fail('The file failed magic-byte validation.');
                }
            }],
            'altText' => ['required', 'string', 'max:255'],
            'slot' => ['required', 'string', 'max:255'],
        ];
    }
}
