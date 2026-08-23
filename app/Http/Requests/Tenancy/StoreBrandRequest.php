<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;
use App\Models\Brand;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StoreBrandRequest extends FormRequest
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
            'slug' => ['sometimes', 'string', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'unique:brands,slug'],
            'locale' => ['sometimes', 'string', $this->localeRule()],
            'timezone' => ['required', 'string', $this->timezoneRule()],
            'currency' => ['required', 'string', $this->currencyRule()],
            'description' => ['sometimes', 'nullable', 'string'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspaceId = (int) $this->route('workspace');

            if (Brand::query()->where('workspace_id', $workspaceId)->exists()) {
                $validator->errors()->add('workspace_id', 'A brand already exists for this workspace.');
            }
        });
    }

    private function localeRule(): Closure|ValidationRule
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                LocaleCode::fromString((string) $value);
            } catch (InvalidArgumentException) {
                $fail('The :attribute is not a supported locale code.');
            }
        };
    }

    private function timezoneRule(): Closure|ValidationRule
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                TimezoneIdentifier::fromString((string) $value);
            } catch (InvalidArgumentException) {
                $fail('The :attribute must be a valid IANA timezone identifier.');
            }
        };
    }

    private function currencyRule(): Closure|ValidationRule
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            try {
                CurrencyCode::fromString((string) $value);
            } catch (InvalidArgumentException) {
                $fail('The :attribute must be a valid ISO-4217 currency code.');
            }
        };
    }
}
