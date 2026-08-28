<?php

declare(strict_types=1);

namespace App\Http\Requests\MenuCatalog;

use Illuminate\Foundation\Http\FormRequest;

final class RenameMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('productName'))) {
            $this->merge(['productName' => trim((string) $this->input('productName'))]);
        }

        if (is_string($this->input('description'))) {
            // Boş bir açıklama, "açıklama yok" demektir. Boş dizeyi
            // saklamak, misafir sayfasında boş bir satır açardı.
            $trimmed = trim((string) $this->input('description'));
            $this->merge(['description' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'productName' => ['required', 'string', 'min:1', 'max:255'],
            // Kısa, düz metin. Uzunluk sınırı keyfi değil: misafir masada
            // paragraf okumaz, menü de yükünü taşıyamaz (`docs/06` bütçesi).
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
