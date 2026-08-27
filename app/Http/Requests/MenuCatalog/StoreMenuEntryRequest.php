<?php

declare(strict_types=1);

namespace App\Http\Requests\MenuCatalog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Menüye bir ürün eklemenin TEK isteği.
 *
 * Alerjen isteğe bağlıdır ve bilerek öyledir: kullanıcı ürünü eklemek için
 * alerjen bilmek zorunda değildir. Zorunlu kılmak, hızlı olması gereken bir
 * işi ilk üründe durdururdu.
 */
final class StoreMenuEntryRequest extends FormRequest
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
            'productName' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'allergens' => ['sometimes', 'array', 'max:50'],
            'allergens.*' => ['string', 'min:1', 'max:100'],
        ];
    }
}
