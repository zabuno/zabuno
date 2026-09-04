<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\Publication\BusinessType;
use Tests\TestCase;

/**
 * TENANT-URL-RESERVED-01 — FF-116, `docs/105` §4.4.
 *
 * Adresin başındaki tür segmenti bir işletmenin slug'ı olamaz. Olsaydı
 * `/restoran/restoran/menu/ab12cd34ef` gibi çözülemeyen adresler doğardı ve
 * hangi segmentin ne olduğu isteğe göre değişirdi.
 */
final class ReservedSegmentTest extends TestCase
{
    public function test_every_business_type_segment_is_reserved(): void
    {
        /** @var list<string> $reserved */
        $reserved = config('url-policy.reserved_slugs');

        foreach (BusinessType::allSegments() as $segment) {
            self::assertContains(
                $segment,
                $reserved,
                "TENANT-URL-RESERVED-01: '{$segment}' rezerve listesinde yok — bir işletme bu slug'ı alırsa adres çözülemez.",
            );
        }
    }

    public function test_the_segments_cover_both_written_languages(): void
    {
        // Yeni bir dil eklendiğinde bu test, rezerve listesinin
        // güncellenmediğini söyler.
        self::assertContains('restoran', BusinessType::allSegments());
        self::assertContains('restaurant', BusinessType::allSegments());
        self::assertContains('urun', BusinessType::allSegments());
        self::assertContains('dish', BusinessType::allSegments());
    }
}
