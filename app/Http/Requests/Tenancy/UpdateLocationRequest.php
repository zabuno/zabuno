<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Domain\Tenancy\ValueObject\OpeningHoursDay;
use App\Domain\Tenancy\ValueObject\WeeklyOpeningHours;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

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
            /*
                ÇALIŞMA SAATLERİ (`docs/109` §6.4).

                Alan GÖNDERİLMEZSE kayıtlı saatlere dokunulmaz; boş dizi ya
                da `null` onları siler; dolu geldiyse HAFTANIN TAMAMI
                beklenir (`max:7` üstündeki asıl kural aşağıdaki `after`
                bloğundadır — çünkü "yedi ayrı gün" bir sayı kuralı değil,
                bir bütünlük kuralıdır).

                Buradaki kurallar yalnız BİÇİMİ süzer: sayı mı, aralıkta mı,
                mantıklı mı. Gerçek yasağı (kapanış açılıştan önce olamaz,
                bir gün 24 saati aşamaz, gün tekrar edemez) alan modeli
                koyar — böylece kural, ekranların değil şubenin kuralıdır.
            */
            'opening_hours' => ['sometimes', 'nullable', 'array', 'max:7'],
            'opening_hours.*.day' => ['required', 'integer', 'between:1,7'],
            'opening_hours.*.closed' => ['required', 'boolean'],
            'opening_hours.*.opens_minute' => [
                'nullable',
                'integer',
                'between:0,'.(OpeningHoursDay::MINUTES_PER_DAY - 1),
            ],
            // Kapanış gece yarısını AŞABİLİR: "18:00–02:00" → 1560. Üst sınır
            // bu yüzden 1440 değil, iki günün toplamıdır.
            'opening_hours.*.closes_minute' => [
                'nullable',
                'integer',
                'between:1,'.(2 * OpeningHoursDay::MINUTES_PER_DAY),
            ],
        ];
    }

    /**
     * Haftanın BÜTÜNLÜĞÜ burada denetlenir.
     *
     * Kuralın kendisi alan modelinde (`WeeklyOpeningHours`) durur ve
     * buradan yalnız ÇAĞRILIR. İki yerde ayrı ayrı yazılsaydı, biri
     * gevşediğinde ekran ile veritabanı farklı şeylere "geçerli" derdi;
     * o an da fark edilmezdi, çünkü ikisi de kendi içinde tutarlı olurdu.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $hours = $this->input('opening_hours');

            if (! is_array($hours) || $hours === []) {
                return;
            }

            try {
                WeeklyOpeningHours::fromArray($hours);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('opening_hours', $exception->getMessage());
            }
        });
    }
}
