<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManualPaymentRequest extends FormRequest
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
            'plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'ends_at' => ['required', 'date', 'after_or_equal:today'],
            'payment_note' => ['required', 'string', 'max:2000'],
            'document_reference' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_note' => is_string($this->input('payment_note')) ? trim($this->input('payment_note')) : $this->input('payment_note'),
            'document_reference' => is_string($this->input('document_reference')) ? trim($this->input('document_reference')) : $this->input('document_reference'),
        ]);
    }
}
