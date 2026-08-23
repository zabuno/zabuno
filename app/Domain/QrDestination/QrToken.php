<?php

declare(strict_types=1);

namespace App\Domain\QrDestination;

use InvalidArgumentException;
use RuntimeException;

final class QrToken
{
    private const PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    private function __construct(private readonly string $value) {}

    public static function generate(): self
    {
        $encoded = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return new self($encoded);
    }

    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Invalid QR token format.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * @param  callable(string): bool  $isTaken
     */
    public static function generateAvoiding(callable $isTaken, int $maxAttempts = 5): self
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = self::generate();

            if (! $isTaken($candidate->value())) {
                return $candidate;
            }
        }

        throw new RuntimeException('Exhausted bounded QR token collision-retry attempts.');
    }
}
