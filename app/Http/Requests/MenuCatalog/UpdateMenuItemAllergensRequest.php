<?php

declare(strict_types=1);

namespace App\Http\Requests\MenuCatalog;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMenuItemAllergensRequest extends FormRequest
{
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
            'allergens' => ['present', 'array'],
            'allergens.*' => ['string', 'max:255'],
        ];
    }
}
