<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use InvalidArgumentException;

/**
 * KDV ayrımı — YALNIZ bir oran yapılandırıldığında (docs/130 §K5).
 *
 * Oran yoksa ayrım da yoktur (`none()`): sıfır yazmak "KDV yok" demek
 * olurdu, oysa doğru cümle "bilinmiyor"dur. Bu depoda ölçülmüş bir oran
 * kaynağı yok; oran `config/billing.php#invoice.vat_rate_basis_points`
 * boş gelir ve sahibin muhasebecisi doldurana kadar boş kalır.
 *
 * Hesap TAMSAYI aritmetiğidir: kayan noktalı bir bölme, kuruşu bir
 * yukarı ya da bir aşağı kaydırıp toplamı tutmayan bir belge üretebilir.
 * Tutar KDV DAHİL kabul edilir (oranı girmek bu beyandır), net aşağı
 * yuvarlamayla değil yarım-yukarı yuvarlamayla bulunur ve KDV artıktan
 * gelir — böylece `net + kdv` her zaman tahsil edilen tutara eşittir.
 */
final readonly class VatBreakdown
{
    private function __construct(
        public ?int $rateBasisPoints,
        public ?int $netMinor,
        public ?int $vatMinor,
    ) {}

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public static function fromGrossAmount(int $grossMinor, ?int $rateBasisPoints): self
    {
        if ($rateBasisPoints === null) {
            return self::none();
        }

        if ($rateBasisPoints < 0 || $rateBasisPoints > 10000) {
            throw new InvalidArgumentException('VAT rate must be between 0 and 10000 basis points.');
        }

        if ($grossMinor < 0) {
            throw new InvalidArgumentException('Gross amount must not be negative.');
        }

        $divisor = 10000 + $rateBasisPoints;
        $net = intdiv($grossMinor * 10000 + intdiv($divisor, 2), $divisor);

        return new self($rateBasisPoints, $net, $grossMinor - $net);
    }

    public function isKnown(): bool
    {
        return $this->rateBasisPoints !== null;
    }
}
