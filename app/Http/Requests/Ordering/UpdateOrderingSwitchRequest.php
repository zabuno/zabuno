<?php

declare(strict_types=1);

namespace App\Http\Requests\Ordering;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Şalterin isteği — tek alan, ve o alan ZORUNLU.
 *
 * `nullable` olsaydı, boş gövdeyle gelen bir istek şalteri kapatırdı ve
 * ekranda yanlışlıkla gönderilen bir form, servisi sessizce durdururdu.
 * "Kapat" cümlesi açıkça söylenmelidir.
 */
final class UpdateOrderingSwitchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'acceptsOrders' => ['required', 'boolean'],
        ];
    }
}
