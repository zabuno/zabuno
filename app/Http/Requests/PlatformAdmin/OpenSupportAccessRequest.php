<?php

declare(strict_types=1);

namespace App\Http\Requests\PlatformAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Sebep ZORUNLUDUR — `docs/122` §5, `docs/133` §1.
 *
 * Üç kural üst üste durur ve üçü de "sebep alanını isteğe bağlı ya da
 * varsayılan değerli yapmak" kolaylığını kapatır:
 *
 *  1. `required` — alan hiç gönderilemez değil, GÖNDERİLMEK ZORUNDA.
 *  2. `prepareForValidation` boşlukları kırpar; kırpıldıktan sonra boş
 *     kalan bir sebep `required`'ı geçemez. Beş boşluk bir sebep değildir.
 *  3. `min:12` — "test", "bakıyorum", "ok" bir sebep değildir. Sayı bir
 *     kalite ölçüsü değil, bir NİYET eşiğidir: on iki karakter yazan biri
 *     alanı geçmek için değil, doldurmak için yazmıştır. Sebebi kiracı
 *     okuyacak; ona anlamsız bir dize göstermek, hiç sebep göstermemekten
 *     iyi değildir.
 *
 * VARSAYILAN DEĞER YOKTUR ve olmayacak: bir varsayılan, sebebi soran
 * kutuyu bir onay kutusuna çevirirdi.
 */
final class OpenSupportAccessRequest extends FormRequest
{
    /** Bir sebebin en az taşıması gereken karakter sayısı. */
    public const REASON_MIN = 12;

    /** Sütun sınırı; sebep bir cümledir, bir dosya değil. */
    public const REASON_MAX = 500;

    public function authorize(): bool
    {
        // Yetki kapısı rota ara katmanındadır (`EnsurePlatformSuperAdmin`);
        // burada ikinci bir kopyası yaşamaz.
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.self::REASON_MIN, 'max:'.self::REASON_MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required before looking at a tenant account.',
            'reason.min' => 'Write the reason the tenant will read, not a placeholder.',
        ];
    }
}
