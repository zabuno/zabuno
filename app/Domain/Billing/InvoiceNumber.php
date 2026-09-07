<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use InvalidArgumentException;

/**
 * Belge numarasının tek biçimlendirme sahibi (docs/130 §K2).
 *
 * Biçim: `[ÖNEK-]SERİ-NNNNNN` — örneğin `2026-000001`, sahip bir seri
 * harfi girdiyse `ZBN-2026-000001`. Seri takvim yılıdır ve numara o seri
 * içinde 1'den başlar.
 *
 * Bu numara Zabuno'nun KENDİ kayıt numarasıdır; GİB'in e-arşiv seri
 * biçiminin taklidi DEĞİLDİR ve öyle sunulmaz. Önek varsayılanı yoktur:
 * uydurulmuş üç harf, belgeyi olmadığı bir şeye benzetirdi.
 */
final readonly class InvoiceNumber
{
    private const PAD = 6;

    public function __construct(
        public string $series,
        public int $number,
        public ?string $prefix = null,
    ) {
        if ($series === '') {
            throw new InvalidArgumentException('Invoice series must not be empty.');
        }

        if ($number < 1) {
            throw new InvalidArgumentException('Invoice number must start at 1.');
        }
    }

    public function toString(): string
    {
        $prefix = $this->prefix === null || trim($this->prefix) === '' ? '' : trim($this->prefix).'-';

        return $prefix.$this->series.'-'.str_pad((string) $this->number, self::PAD, '0', STR_PAD_LEFT);
    }
}
