<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Url\UrlPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Bir slug, URL ad alanında zaten bir anlamı olan kelimeyi çalamaz.
 *
 * `api`, `menu`, `q`, `admin` gibi kelimeler yol öneki olarak kullanılıyor.
 * Bir işletme kendine `menu` slug'ını alırsa, o yol iki şey birden ifade
 * eder ve hangisinin kazandığı route sırasına kalır — yani bir gün yapılan
 * masum bir route düzenlemesi başka birinin menüsünü düşürür.
 *
 * Biçim kuralı (kebab-case) alan nesnesinin işidir; AD ALANI kuralı URL
 * politikasınındır. İkisi ayrı yerlerde durur çünkü ayrı sorulardır.
 */
final class NotReservedSlug implements ValidationRule
{
    public function __construct(private readonly UrlPolicy $policy) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // biçim/zorunluluk başka kuralların işi
        }

        if ($this->policy->isReservedSlug($value)) {
            $fail('The :attribute is reserved by the application and cannot be used.');
        }
    }
}
