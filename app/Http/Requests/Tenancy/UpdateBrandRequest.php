<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

final class UpdateBrandRequest extends FormRequest
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
            'locale' => ['sometimes', 'string', $this->localeRule()],
            'timezone' => ['required', 'string', $this->timezoneRule()],
            'currency' => ['required', 'string', $this->currencyRule()],
            'description' => ['sometimes', 'nullable', 'string'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
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
