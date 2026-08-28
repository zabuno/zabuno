<?php

declare(strict_types=1);

namespace App\Http\Requests\MenuCatalog;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderMenuItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Liste TAM olmalıdır; eksiksizliği depo doğrular (`docs/73`).
     *
     * Buradaki kural yalnız BİÇİMİ tutar: benzersiz tam sayılar. Aynı
     * kimliğin iki kez geçmesi, bir satırı iki yere koymak demektir ve
     * sessizce sonuncunun kazanması kullanıcıya hiçbir şey söylemezdi.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'menuItemIds' => ['required', 'array', 'min:1'],
            'menuItemIds.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
