<?php

declare(strict_types=1);

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Yetki ROTADA (`EnsurePlatformSuperAdmin`), burada yalnız biçim.
 *
 * BOŞLUKTAN İBARET BİR GÖVDE BİR CEVAP DEĞİLDİR: `'   '` kabul edilirse
 * müşteriye boş bir e-posta çıkar ve talep cevaplanmış sayılır. Bu yüzden
 * kırpma DOĞRULAMADAN ÖNCE yapılır (`prepareForValidation`), `required`
 * o kırpılmış değeri görür ve hata doğru alanda durur.
 *
 * ÜST SINIR AÇIK: bir destek cevabı bir kitap değildir ve sınırsız bir
 * gövde, tek POST'la posta sağlayıcısına megabaytlık bir mesaj yazmanın
 * ucuz yolu olurdu.
 */
final class ReplyToSupportRequestRequest extends FormRequest
{
    /** Karakter üst sınırı — uzun bir cevap için bol, bir yük için az. */
    public const MAX_BODY_LENGTH = 5000;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        if (is_string($body)) {
            $this->merge(['body' => trim($body)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ];
    }
}
