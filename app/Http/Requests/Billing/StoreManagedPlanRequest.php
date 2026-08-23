<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

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
            'entitlements.*' => ['required', 'string', 'min:1'],
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
