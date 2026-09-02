<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            // Boş bir ad bir NİYETTİR: kullanıcı Kaydet'e bastı. Sessizce
            // eski adı korumak, düğmeye basılıp hiçbir şey olmaması demektir
            // (`docs/47` Kural 5).
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
