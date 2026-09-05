<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenancy;

use App\Domain\Branding\SkinVariant;
use App\Domain\Tenancy\ValueObject\CurrencyCode;
use App\Domain\Tenancy\ValueObject\LocaleCode;
use App\Domain\Tenancy\ValueObject\TimezoneIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            /*
                Marka renkleri: `#rrggbb`. Kısa biçim (`#abc`) ve ad (`red`)
                kabul edilmez — depolanan değer tek biçimde olmalı ki menü
                şablonu onu doğrudan kullanabilsin.
            */
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            /*
                BİÇİM EKSENİ: kiracı bir DEĞER değil, bir SEÇENEK seçer
                (FF-174, `docs/113` §5.3). Liste token katmanından gelir
                (`resources/css/aep/tokens/variants.css`) ve
                `SkinVariantMatchesTokenLayerTest` iki tarafın ayrılmasını
                engeller. Serbest bırakılsaydı kiracı, tarayıcıda hiçbir şey
                yapmayan bir varyant seçerdi.
            */
            'skin_variant' => ['sometimes', 'nullable', 'string', Rule::enum(SkinVariant::class)],
        ];
    }

    /**
     * İstek marka GÖRÜNÜMÜNE dokunuyor mu?
     *
     * Yalnız adını düzelten bir istek plan kapısına takılmamalıdır: kapı
     * ek yeteneği korur, temel yolculuğu değil (`Entitlement` kapsam kuralı).
     */
    public function touchesBranding(): bool
    {
        foreach (['primary_color', 'secondary_color', 'skin_variant'] as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
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
