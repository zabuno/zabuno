<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\ValueObject;

use InvalidArgumentException;

final class LocaleCode
{
    public const string DEFAULT = 'en';

    /**
     * @var list<string>
     */
    private const array SUPPORTED = [
        'en', 'tr', 'de', 'fr', 'es', 'it', 'pt', 'ar', 'ru', 'zh',
        'ja', 'nl', 'pl', 'sv', 'da', 'no', 'fi', 'el', 'cs', 'ro',
        'hu', 'bg', 'uk', 'he', 'ko', 'vi', 'th', 'id', 'ms', 'hi',
    ];

    /**
     * Desteklenen dillerin listesi.
     *
     * Neden dışarı açılıyor: arayüz kullanıcıdan dil kodu YAZMASINI istememeli.
     * Marka formu `tr_TR` gibi bir değer bekliyordu — bu kullanıcı dili değil,
     * geliştirici kodudur. Seçenekleri sunabilmek için tek doğruluk kaynağının
     * okunabilir olması gerekiyor; ikinci bir liste tutmak, iki listenin
     * ayrışacağı bir gün yaratırdı.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    private function __construct(private readonly string $value) {}

    public static function default(): self
    {
        return new self(self::DEFAULT);
    }

    public static function fromString(string $value): self
    {
        if (! in_array($value, self::SUPPORTED, true)) {
            throw new InvalidArgumentException("Unsupported locale code: {$value}");
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
