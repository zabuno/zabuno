<?php

declare(strict_types=1);

namespace App\Domain\MenuCatalog\ValueObject;

use Brick\Money\Currency;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money as BrickMoney;
use InvalidArgumentException;

final class Money
{
    /**
     * Exception codes used by fromDecimalPriceForBrand() to name the
     * offending field, since the Domain layer cannot depend on Illuminate's
     * ValidationException (ADR-L02) — an HTTP-layer caller maps the code
     * back to its own validation error representation.
     */
    public const INVALID_FIELD_CURRENCY = 1;

    public const INVALID_FIELD_PRICE = 2;

    private function __construct(private readonly BrickMoney $money) {}

    public static function fromMinorAmount(int $minorAmount, string $currencyCode): self
    {
        if ($minorAmount < 0) {
            throw new InvalidArgumentException('Money minor amount must not be negative.');
        }

        try {
            $currency = Currency::of($currencyCode);
        } catch (UnknownCurrencyException $e) {
            throw new InvalidArgumentException("Unknown or invalid currency code [{$currencyCode}].", 0, $e);
        }

        $money = BrickMoney::ofMinor($minorAmount, $currency);

        return new self($money);
    }

    /**
     * Parses a decimal price string against a required brand currency,
     * enforcing the currency's official ISO-4217 fraction-digit count
     * without floating-point conversion. Shared by every Menu Catalog API
     * write path (create and update) so the two cannot drift.
     *
     * Framework-agnostic by design (this is the Domain layer): on rejection
     * it throws InvalidArgumentException with its exception code set to
     * self::INVALID_FIELD_CURRENCY or self::INVALID_FIELD_PRICE, so an
     * HTTP-layer caller can translate it into its own validation error
     * representation without this layer depending on Illuminate.
     *
     * @throws InvalidArgumentException
     */
    public static function fromDecimalPriceForBrand(string $price, string $currency, string $brandCurrencyCode): self
    {
        try {
            $currencyObject = Currency::of($currency);
        } catch (UnknownCurrencyException) {
            throw new InvalidArgumentException('The currency is not a known ISO-4217 currency code.', self::INVALID_FIELD_CURRENCY);
        }

        if ($currency !== $brandCurrencyCode) {
            throw new InvalidArgumentException('The currency does not match the Brand currency.', self::INVALID_FIELD_CURRENCY);
        }

        $fractionDigits = $currencyObject->getDefaultFractionDigits();
        $priceFractionDigits = str_contains($price, '.') ? strlen(explode('.', $price, 2)[1]) : 0;

        if ($priceFractionDigits > $fractionDigits) {
            throw new InvalidArgumentException("The price must not have more than {$fractionDigits} fraction digit(s) for this currency.", self::INVALID_FIELD_PRICE);
        }

        $isNegative = str_starts_with($price, '-');
        $unsignedDigits = str_replace('.', '', ltrim($price, '-'));
        $isZero = $unsignedDigits !== '' && ltrim($unsignedDigits, '0') === '';

        if ($isNegative || $isZero) {
            throw new InvalidArgumentException('The price must be greater than zero.', self::INVALID_FIELD_PRICE);
        }

        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        $fraction = str_pad($fraction, $fractionDigits, '0');

        return self::fromMinorAmount((int) ($whole.$fraction), $currency);
    }

    public function minorAmount(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    public function currencyCode(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    public function fractionDigits(): int
    {
        return $this->money->getCurrency()->getDefaultFractionDigits();
    }

    public function equals(self $other): bool
    {
        return $this->minorAmount() === $other->minorAmount()
            && $this->currencyCode() === $other->currencyCode();
    }
}
