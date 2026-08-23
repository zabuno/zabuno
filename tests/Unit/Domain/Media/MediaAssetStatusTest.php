<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\MediaAssetStatus;
use PHPUnit\Framework\TestCase;

/**
 * RED unit candidate for the S1-WP03a Media Quarantine Intake bounded
 * slice's Domain-layer status concept. Correction: MediaAssetStatus as a
 * domain enum may legitimately grow future accepted/processing/ready
 * cases once later slices add background processing — this bounded
 * intake package only constrains the *intake flow itself*, which must
 * stop at Quarantined or Rejected and never claim acceptance, background
 * processing, or readiness for use. This test therefore asserts the
 * meaningful Quarantined/Rejected contract only and does not forbid any
 * enum case globally. App\Domain\Media\MediaAssetStatus does not exist
 * yet (no app/Domain/Media directory today), so every assertion below
 * fails RED with a class-not-found error, not a logic assertion failure.
 *
 * Placed under App\Domain\Media to stay the innermost Onion layer
 * (docs/03 ADR-L02, enforced by tests/Unit/Architecture/OnionBoundaryTest):
 * no Illuminate import, plain PHPUnit\Framework\TestCase, strict_types.
 *
 * Requirement ID: MEDIA-DOMAIN-STATUS-01.
 */
final class MediaAssetStatusTest extends TestCase
{
    public function test_quarantined_is_a_valid_status(): void
    {
        $status = MediaAssetStatus::Quarantined;

        self::assertSame('quarantined', $status->value);
    }

    public function test_rejected_is_a_valid_terminal_status_for_invalid_intake(): void
    {
        $status = MediaAssetStatus::Rejected;

        self::assertSame('rejected', $status->value);
    }
}
