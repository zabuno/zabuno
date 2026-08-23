<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrDestination;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S1-WP04b1 RED — proves the not-yet-implemented App\Domain\QrDestination\
 * QrToken value object (delivery contract §"Token"): 256-bit-entropy CSPRNG
 * token, base64url-without-padding-encoded, exactly 43 chars matching
 * [A-Za-z0-9_-]{43}, never sequential/DB-id/slug-derived, with bounded
 * collision-retry generation. Today App\Domain\QrDestination\* does not
 * exist at all, so every test below fails RED with a class-not-found error,
 * not a logic assertion failure.
 *
 * Frozen contract (this file's own invented contract, consistent with the
 * MASTER-synthesized QR Destination scope):
 *   - QrToken::generate(): self returns a fresh CSPRNG token.
 *   - QrToken::fromString(string $value): self validates the frozen shape
 *     and throws \InvalidArgumentException on anything else.
 *   - QrToken::value(): string returns the raw 43-char token.
 *   - QrToken::generateAvoiding(callable $isTaken, int $maxAttempts = 5):
 *     self retries generation while $isTaken($candidate) is true, and
 *     throws a bounded-collision exception (\RuntimeException or a domain
 *     subclass) once $maxAttempts is exhausted — it never loops forever or
 *     falls back to a shorter/weaker token.
 *
 * Requirement IDs: QR-TOKEN-FORMAT-01, QR-TOKEN-ENTROPY-01,
 * QR-TOKEN-NOT-DERIVED-01, QR-TOKEN-VALIDATION-01, QR-TOKEN-COLLISION-01.
 */
final class QrTokenTest extends TestCase
{
    private const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    // --- QR-TOKEN-FORMAT-01 -------------------------------------------------

    public function test_generate_returns_a_43_char_base64url_token_matching_the_frozen_pattern(): void
    {
        self::assertTrue(class_exists(\App\Domain\QrDestination\QrToken::class), 'App\\Domain\\QrDestination\\QrToken sınıfı mevcut değil.');

        $token = \App\Domain\QrDestination\QrToken::generate();

        self::assertSame(43, strlen($token->value()), 'QR-TOKEN-FORMAT-01: token tam 43 karakter olmalı.');
        self::assertMatchesRegularExpression(self::TOKEN_PATTERN, $token->value(), 'QR-TOKEN-FORMAT-01: token [A-Za-z0-9_-]{43} desenine uymalı.');
        self::assertStringNotContainsString('=', $token->value(), 'QR-TOKEN-FORMAT-01: base64url padding karakteri (=) taşımamalı.');
        self::assertStringNotContainsString('+', $token->value(), 'QR-TOKEN-FORMAT-01: standart base64 (+) karakteri taşımamalı, base64url olmalı.');
        self::assertStringNotContainsString('/', $token->value(), 'QR-TOKEN-FORMAT-01: standart base64 (/) karakteri taşımamalı, base64url olmalı.');
    }

    // --- QR-TOKEN-ENTROPY-01 -------------------------------------------------

    public function test_generate_produces_cryptographically_unique_tokens_across_many_calls(): void
    {
        $tokens = [];
        for ($i = 0; $i < 500; $i++) {
            $tokens[] = \App\Domain\QrDestination\QrToken::generate()->value();
        }

        self::assertCount(500, array_unique($tokens), 'QR-TOKEN-ENTROPY-01: 500 üretimde çakışma olmamalı (256-bit CSPRNG entropi).');
    }

    // --- QR-TOKEN-NOT-DERIVED-01 ---------------------------------------------

    public function test_generate_never_produces_a_sequential_or_predictable_token(): void
    {
        $first = \App\Domain\QrDestination\QrToken::generate()->value();
        $second = \App\Domain\QrDestination\QrToken::generate()->value();

        self::assertNotSame($first, $second);

        $sharedPrefixLength = 0;
        $max = min(strlen($first), strlen($second));
        for ($i = 0; $i < $max; $i++) {
            if ($first[$i] !== $second[$i]) {
                break;
            }
            $sharedPrefixLength++;
        }

        self::assertLessThan(8, $sharedPrefixLength, 'QR-TOKEN-NOT-DERIVED-01: ardışık üretimler arasında öngörülebilir ortak önek olmamalı.');
    }

    // --- QR-TOKEN-VALIDATION-01 -----------------------------------------------

    public function test_from_string_accepts_a_well_formed_43_char_token(): void
    {
        $raw = str_repeat('a', 43);

        $token = \App\Domain\QrDestination\QrToken::fromString($raw);

        self::assertSame($raw, $token->value());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedTokenProvider(): iterable
    {
        yield 'too short' => [str_repeat('a', 42)];
        yield 'too long' => [str_repeat('a', 44)];
        yield 'contains padding' => [str_repeat('a', 42).'='];
        yield 'contains plus' => [str_repeat('a', 42).'+'];
        yield 'contains slash' => [str_repeat('a', 42).'/'];
        yield 'contains space' => [str_repeat('a', 42).' '];
        yield 'empty string' => [''];
        yield 'sql-looking value' => ["' OR '1'='1"];
    }

    #[DataProvider('malformedTokenProvider')]
    public function test_from_string_rejects_any_malformed_token(string $malformed): void
    {
        $this->expectException(\InvalidArgumentException::class);

        \App\Domain\QrDestination\QrToken::fromString($malformed);
    }

    // --- QR-TOKEN-COLLISION-01 ------------------------------------------------

    public function test_generate_avoiding_retries_while_the_candidate_is_taken_and_returns_a_fresh_token(): void
    {
        $seen = [];
        $isTaken = function (string $candidate) use (&$seen): bool {
            $seen[] = $candidate;

            return count($seen) < 3;
        };

        $token = \App\Domain\QrDestination\QrToken::generateAvoiding($isTaken, 5);

        self::assertCount(3, $seen, 'QR-TOKEN-COLLISION-01: tam olarak taken dönmeyen ilk adaya kadar denemeli.');
        self::assertSame($seen[2], $token->value(), 'QR-TOKEN-COLLISION-01: sonunda kabul edilen aday döndürülmeli.');
    }

    public function test_generate_avoiding_fails_safely_after_the_bounded_attempt_ceiling_is_exhausted(): void
    {
        $alwaysTaken = static fn (string $candidate): bool => true;

        $this->expectException(\RuntimeException::class);

        \App\Domain\QrDestination\QrToken::generateAvoiding($alwaysTaken, 5);
    }
}
