<?php

declare(strict_types=1);

namespace App\Http\Requests\Ordering;

use App\Domain\Ordering\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Panelden istenebilecek durumlar — ve istenemeyecekler (`docs/115` §2).
 *
 * Liste `OrderStatus::cases()` DEĞİLDİR ve olmamalı. İki durum bilerek
 * dışarıda:
 *
 * - `pending` — siparişin başlangıcıdır, bir hedef değil. Geri döndürmek,
 *   onaylanmış bir işi mutfaktan sessizce geri çekmek olurdu.
 * - `cancelled` — İPTAL MİSAFİRİN VAZGEÇMESİDİR. Restoran reddeder ve
 *   sebebini yazar. Panele iptal düğmesi koymak, misafirin ekranında kendi
 *   vazgeçtiği siparişle reddedilen siparişi aynı cümleye çevirirdi.
 *
 * Yetki kararı burada VERİLMEZ — hangi rolün hangi hedefe hakkı olduğu
 * denetleyicidedir. Burada donan şey, isteğin ŞEKLİdir.
 */
final class ChangeOrderStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:'.implode(',', [
                    OrderStatus::Confirmed->value,
                    OrderStatus::Rejected->value,
                    OrderStatus::Preparing->value,
                    OrderStatus::Ready->value,
                    OrderStatus::Delivered->value,
                ]),
            ],
            /*
                SEBEP RETTE ZORUNLU (`docs/115` G3): misafirin ekranında
                görünür. Uzunluk sütunun genişliğiyle AYNI (280): burada
                kabul edilip veritabanında reddedilen bir cümle, garsona
                sebebini yazdırdıktan sonra işi yaptırmamak olurdu.
            */
            'reason' => ['required_if:status,'.OrderStatus::Rejected->value, 'nullable', 'string', 'max:280'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'A rejection needs a reason: the guest reads it on their screen.',
        ];
    }
}
