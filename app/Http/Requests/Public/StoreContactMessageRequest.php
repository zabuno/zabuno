<?php

declare(strict_types=1);

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

final class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'message'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            // `email` DOĞRULANIR: cevap yazılamayacak bir adres, gelen
            // mesajı sessizce cevapsız bırakırdı.
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            // Üst sınır keyfi değil: bir e-posta kadar uzun bir metin
            // formdan değil, e-postadan gelir.
            'message' => ['required', 'string', 'min:1', 'max:4000'],
            // Bal küpü: insan görmediği bir alanı doldurmaz. Doğrulama onu
            // REDDETMEZ; kontrolcü sessizce düşürür (`docs/88`).
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
