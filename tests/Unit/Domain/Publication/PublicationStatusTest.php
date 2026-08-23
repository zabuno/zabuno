<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Publication;

use Tests\TestCase;

/**
 * S1-WP04a RED — proves the not-yet-implemented
 * App\Domain\Publication\PublicationStatus enum (states pending, generating,
 * published, failed, superseded per modules/publication.md "States") plus a
 * valid-transition guard. Today App\Domain\Publication\* does not exist at
 * all, so every test below fails RED with a class-not-found error, not a
 * logic assertion failure.
 *
 * Frozen transition contract (this file's own invented contract, consistent
 * with modules/publication.md "pending -> generating -> published -> failed
 * -> superseded"):
 *   pending -> generating -> published -> superseded (next publish)
 *   generating -> failed (persistence/current-pointer failure)
 *   failed is terminal for that attempt (a fresh publish starts a new pending)
 *   published -> failed is NOT a valid transition (an already-succeeded
 *     snapshot never regresses to failed)
 *   superseded is terminal
 */
final class PublicationStatusTest extends TestCase
{
    public function test_status_enum_declares_the_five_frozen_states(): void
    {
        $enumClass = \App\Domain\Publication\PublicationStatus::class;

        self::assertTrue(enum_exists($enumClass), 'App\\Domain\\Publication\\PublicationStatus enum mevcut değil.');

        $names = array_map(static fn ($case) => $case->name, $enumClass::cases());

        self::assertCount(5, $names, 'PublicationStatus tam olarak beş state taşımalı.');
        self::assertContains('Pending', $names);
        self::assertContains('Generating', $names);
        self::assertContains('Published', $names);
        self::assertContains('Failed', $names);
        self::assertContains('Superseded', $names);
    }

    public function test_pending_may_transition_to_generating(): void
    {
        $status = \App\Domain\Publication\PublicationStatus::Pending;

        self::assertTrue($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Generating));
    }

    public function test_generating_may_transition_to_published_or_failed(): void
    {
        $status = \App\Domain\Publication\PublicationStatus::Generating;

        self::assertTrue($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Published));
        self::assertTrue($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Failed));
    }

    public function test_published_may_transition_to_superseded_only(): void
    {
        $status = \App\Domain\Publication\PublicationStatus::Published;

        self::assertTrue($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Superseded));
        self::assertFalse($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Failed));
        self::assertFalse($status->canTransitionTo(\App\Domain\Publication\PublicationStatus::Pending));
    }

    public function test_failed_and_superseded_are_terminal(): void
    {
        $failed = \App\Domain\Publication\PublicationStatus::Failed;
        $superseded = \App\Domain\Publication\PublicationStatus::Superseded;

        foreach (\App\Domain\Publication\PublicationStatus::cases() as $target) {
            self::assertFalse($failed->canTransitionTo($target), "Failed -> {$target->name} geçersiz olmalı (terminal).");
            self::assertFalse($superseded->canTransitionTo($target), "Superseded -> {$target->name} geçersiz olmalı (terminal).");
        }
    }

    public function test_a_state_never_transitions_to_itself(): void
    {
        foreach (\App\Domain\Publication\PublicationStatus::cases() as $status) {
            self::assertFalse($status->canTransitionTo($status), "{$status->name} kendine geçiş yapamaz.");
        }
    }
}
