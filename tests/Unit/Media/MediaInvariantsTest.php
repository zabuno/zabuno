<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use App\Domain\Media\LifecycleStatus;
use App\Domain\Media\MediaSurface;
use App\Domain\Media\ProcessingStatus;
use App\Domain\Media\SlotCatalogue;
use App\Domain\Media\Visibility;
use PHPUnit\Framework\TestCase;

/**
 * INV-01..INV-07 — medya motorunun tartışılmaz kuralları.
 *
 * Kaynak: `docs/design-corpus/media-engine/50-faz6-image-engine.html`.
 * Frappe/pyvips için yazılmıştı; kuralların kendisi yığın-bağımsızdır.
 *
 * Bu dosya kuralları BURADA, alan katmanında sınar. Görüntü motoru henüz
 * yok; o geldiğinde aynı adlar altında piksel düzeyinde de sınanacak.
 * Bir kuralın adı hiçbir testte geçmiyorsa, o kural yoktur.
 */
final class MediaInvariantsTest extends TestCase
{
    private function catalogue(): SlotCatalogue
    {
        return SlotCatalogue::fromArray([
            'itemImage' => [
                'surface' => MediaSurface::Menu,
                'min_width' => 1000, 'min_height' => 1000,
                'aspect' => '1:1',
                'formats' => ['jpeg', 'png', 'webp'],
                'transparency' => 'flatten',
                'renditions' => [320, 480, 640, 960],
                'alt_required' => true,
            ],
            'logo' => [
                'surface' => MediaSurface::Menu,
                'min_width' => 512, 'min_height' => 512,
                'aspect' => null,
                'formats' => ['png', 'svg'],
                'transparency' => 'preserve',
                'renditions' => [64, 128, 256, 512],
                'alt_required' => true,
            ],
            'hero' => [
                'surface' => MediaSurface::Marketing,
                'min_width' => 1920, 'min_height' => 1080,
                'aspect' => '16:9',
                'formats' => ['jpeg', 'webp'],
                'transparency' => 'flatten',
                'renditions' => [320, 1920],
                'alt_required' => true,
            ],
        ]);
    }

    // --- INV-01 — upscale YASAK -------------------------------------------

    public function test_inv01_a_source_smaller_than_the_largest_rendition_is_refused(): void
    {
        $policy = $this->catalogue()->get('itemImage');

        // En büyük rendition 960; minimum 1000. Daha küçük bir giriş
        // büyütülmez, dolayısıyla menüde bulanık görünür.
        self::assertGreaterThanOrEqual($policy->largestRendition(), $policy->minWidth);
        self::assertFalse($policy->acceptsDimensions(800, 800));
        self::assertTrue($policy->acceptsDimensions(1000, 1000));
    }

    // --- INV-03 — oran toleransı, kesin eşitlik değil ----------------------

    public function test_inv03_an_aspect_within_two_percent_is_accepted(): void
    {
        $policy = $this->catalogue()->get('itemImage');

        // 1000×1001 bir kullanıcı için 1:1'dir. Reddetmek, kırpma aracı
        // zaten varken anlamsız bir engel olurdu.
        self::assertTrue($policy->acceptsAspect(1000, 1001));
        self::assertFalse($policy->acceptsAspect(1600, 900));
    }

    public function test_inv03_a_slot_without_a_fixed_aspect_accepts_anything(): void
    {
        // Logo kırpılmaz; oran dayatmak markayı bozardı.
        self::assertTrue($this->catalogue()->get('logo')->acceptsAspect(1024, 300));
    }

    // --- INV-04 — biçim allowlist'i ---------------------------------------

    public function test_inv04_only_listed_formats_are_accepted(): void
    {
        $policy = $this->catalogue()->get('itemImage');

        self::assertTrue($policy->acceptsFormat('JPEG'));
        self::assertFalse($policy->acceptsFormat('svg'));
    }

    public function test_inv04_svg_is_never_accepted_for_a_photographic_slot(): void
    {
        // SVG doğrudan servis edilmez: içinden script çalışabilir ve dış
        // kaynak çağırabilir. Fotoğraf slotunda hiç kabul edilmez.
        self::assertFalse($this->catalogue()->get('itemImage')->acceptsFormat('svg'));
    }

    // --- INV-07 — alfa korunur --------------------------------------------

    public function test_inv07_a_transparent_slot_never_flattens_to_white(): void
    {
        self::assertSame('preserve', $this->catalogue()->get('logo')->transparency);
        self::assertSame('flatten', $this->catalogue()->get('itemImage')->transparency);
    }

    // --- Yüzey ayrımı ------------------------------------------------------

    public function test_the_restaurant_panel_never_sees_marketing_slots(): void
    {
        $menu = $this->catalogue()->forSurface(MediaSurface::Menu);

        self::assertArrayHasKey('itemImage', $menu);
        self::assertArrayNotHasKey('hero', $menu);
    }

    // --- Üç durum ekseni ---------------------------------------------------

    public function test_only_a_ready_asset_can_be_published(): void
    {
        foreach (ProcessingStatus::cases() as $status) {
            self::assertSame(
                $status === ProcessingStatus::Ready,
                $status->isPublishable(),
                "Yalnız READY yayınlanabilir; {$status->value} için farklı davrandı."
            );
        }
    }

    public function test_the_three_axes_are_independent(): void
    {
        // Bir varlık aynı anda "işlenmesi bitti", "aktif" ve "yalnız
        // tenant'a açık" olabilir. Tek bir `status` sütunu bu üçünü aynı
        // yere doldurup hiçbirini güvenilir cevaplayamıyordu.
        self::assertTrue(ProcessingStatus::Ready->isPublishable());
        self::assertTrue(LifecycleStatus::Active->isUsable());
        self::assertSame('tenant', Visibility::Tenant->value);

        // Çöp kutusundaki bir varlığın işlenmesi bitmiş OLABİLİR.
        self::assertTrue(ProcessingStatus::Ready->isPublishable());
        self::assertFalse(LifecycleStatus::Trashed->isUsable());
    }
}
