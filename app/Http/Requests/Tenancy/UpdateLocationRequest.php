<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLocationRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            /*
                IANA kimliği ve YALNIZ listeden. `timezone_identifiers_list()`
                kuralın kaynağıdır; `UTC+3` gibi sabit offsetler ve `ISTANBUL`
                gibi serbest metinler burada reddedilir, çünkü ikisi de sivil
                saat kurallarını (yaz saati, tarihsel değişiklikler) taşımaz.
            */
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'city' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
