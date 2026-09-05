<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Domain\Entitlement\Entitlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManagedPlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'version' => ['required', 'integer', 'min:1'],
            'entitlements' => ['required', 'array', 'min:1'],
            /*
                LİSTE ENUM'A BAĞLIDIR, SERBEST METİN DEĞİLDİR (`docs/113` §10.2).

                Burada `string|min:1` yazıyordu. `Entitlement::tryFromKey()`
                tanımadığı anahtarı doğru biçimde yok sayıyor — ama
                superadmin yazım hatasını EKRANDA GÖREMİYORDU: plan 201
                dönüyor, satırda yetenek yazıyor, restoran onu hiç almıyordu.
                Sessiz doğru davranış, görünmeyen bir hatadan iyi değildir.
            */
            'entitlements.*' => ['required', 'string', Rule::in(Entitlement::keys())],
            'amount_minor' => ['nullable', 'required_with:currency', 'integer', 'min:0'],
            'currency' => ['nullable', 'required_with:amount_minor', 'string', 'regex:/^[A-Z]{3}$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'code' => is_string($this->input('code')) ? trim($this->input('code')) : $this->input('code'),
        ]);
    }
}
