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
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'productName' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
