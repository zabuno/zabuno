<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\QrDestination;

use Tests\TestCase;

/**
 * S1-WP04b1 RED — proves the not-yet-implemented
 * App\Domain\QrDestination\QrCodeState enum: a created QR code is
 * immediately Active, and MVP disable is strictly one-way (Active ->
 * Disabled, never Disabled -> Active). Today App\Domain\QrDestination\*
 * does not exist at all, so every test below fails RED with a
 * class-not-found error, not a logic assertion failure.
 *
 * Requirement IDs: QR-STATE-01, QR-STATE-ONEWAY-01.
 */
final class QrCodeStateTest extends TestCase
{
    public function test_state_enum_declares_exactly_active_and_disabled(): void
    {
        $enumClass = \App\Domain\QrDestination\QrCodeState::class;

        self::assertTrue(enum_exists($enumClass), 'App\\Domain\\QrDestination\\QrCodeState enum mevcut değil.');

        $names = array_map(static fn ($case) => $case->name, $enumClass::cases());

        self::assertCount(2, $names, 'QrCodeState tam olarak iki state taşımalı (MVP: repoint/archive yok).');
        self::assertContains('Active', $names);
        self::assertContains('Disabled', $names);
    }

    public function test_create_operation_state_is_active(): void
    {
        self::assertSame('active', \App\Domain\QrDestination\QrCodeState::Active->value);
    }

    public function test_active_may_transition_to_disabled(): void
    {
        $state = \App\Domain\QrDestination\QrCodeState::Active;

        self::assertTrue($state->canTransitionTo(\App\Domain\QrDestination\QrCodeState::Disabled));
    }

    public function test_disabled_is_terminal_mvp_disable_is_one_way(): void
    {
        $state = \App\Domain\QrDestination\QrCodeState::Disabled;

        foreach (\App\Domain\QrDestination\QrCodeState::cases() as $target) {
            self::assertFalse($state->canTransitionTo($target), "QR-STATE-ONEWAY-01: Disabled -> {$target->name} geçersiz olmalı, MVP disable geri alınamaz.");
        }
    }

    public function test_a_state_never_transitions_to_itself(): void
    {
        foreach (\App\Domain\QrDestination\QrCodeState::cases() as $state) {
            self::assertFalse($state->canTransitionTo($state), "{$state->name} kendine geçiş yapamaz.");
        }
    }
}
