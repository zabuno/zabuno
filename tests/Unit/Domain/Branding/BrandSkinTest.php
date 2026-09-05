<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Branding;

use App\Domain\Branding\BrandRampRole;
use App\Domain\Branding\BrandSkin;
use App\Domain\Branding\SkinSurface;
use App\Domain\Branding\SkinVariant;
use App\Domain\Branding\SrgbColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FF-174 — kiracı bir TON verir, ürün RAMPAYI TÜRETİR ve KONTRASTI ÖLÇER.
 *
 * Bu depo bugüne kadar kiracı rengini bilerek 4 px'lik bir şeride hapsetti
 * ve gerekçesini şablona yazdı: *"kontrastı biz garanti edemeyiz."* Doğru
 * bir gerekçeydi ve kısıt kaldırılmıyor — ÖLÇÜYE çevriliyor. Bu testin işi
 * o ölçünün gerçekten yapıldığını kanıtlamaktır: aşağıdaki hiçbir satır
 * "herhalde geçer" demez, oranı hesaplatır ve eşikle karşılaştırır.
 */
final class BrandSkinTest extends TestCase
{
    /**
     * Ürünün korktuğu tam örnek: açık sarı. Beyaz üstünde 1.3:1'dir, yani
     * okunmaz. Kiracı onu seçebilmeli ama misafir yazıyı okuyabilmelidir.
     */
    public function test_a_pale_yellow_seed_is_darkened_until_it_reads_on_the_canvas(): void
    {
        $seed = SrgbColor::fromHex('#ffe066');
        $canvas = SrgbColor::fromHex(SkinSurface::Light->canvasHex());

        self::assertLessThan(
            4.5,
            $seed->contrastRatio($canvas),
            'Bu test yanlış tohumla kurulmuş: seçilen sarı zaten AA geçiyorsa ayarlama kanıtlanamaz.',
        );

        $ink = BrandSkin::derive('#ffe066', SkinVariant::A)
            ->ramp(SkinSurface::Light)
            ->role(BrandRampRole::AccentInk);

        self::assertTrue($ink->adjusted, 'Okunmayan bir ton ayarlanmadan yayına çıkamaz.');
        self::assertGreaterThanOrEqual(4.5, $ink->ratio);
        self::assertSame(4.5, $ink->floor);

        // Ölçüm İDDİA DEĞİL: donan oran, donan renkten yeniden hesaplanınca
        // aynı çıkmak zorundadır. Aksi hâlde kanıt kendi rengini anlatmıyor
        // demektir.
        self::assertEqualsWithDelta(
            SrgbColor::fromHex($ink->hex)->contrastRatio(SrgbColor::fromHex($ink->againstHex)),
            $ink->ratio,
            0.01,
        );
    }

    /** Kiracının tonu "yaklaşık" korunur: bordo bordo kalır, yalnız koyulaşır. */
    public function test_the_tenant_hue_survives_the_adjustment(): void
    {
        $seed = SrgbColor::fromHex('#ffe066');
        $skin = BrandSkin::derive('#ffe066', SkinVariant::A);

        foreach ([SkinSurface::Light, SkinSurface::Dark] as $surface) {
            foreach ([BrandRampRole::AccentSurface, BrandRampRole::AccentInk, BrandRampRole::AccentBorder] as $role) {
                $derived = SrgbColor::fromHex($skin->ramp($surface)->role($role)->hex);

                self::assertEqualsWithDelta(
                    $seed->hue(),
                    $derived->hue(),
                    2.0,
                    "{$surface->value}/{$role->value}: kiracının tonu kaybolmuş.",
                );
            }
        }
    }

    /**
     * Asıl garanti burada. Renk çemberi boyunca ve iki uçta (siyah, beyaz)
     * TÜREYEN HER ÇİFT kendi eşiğini geçmek zorundadır — açık ve koyu temada
     * ayrı ayrı, çünkü misafirin telefonu hangisini seçeceğini biz bilmeyiz.
     */
    #[DataProvider('seedTones')]
    public function test_every_derived_pair_meets_its_own_floor_in_both_themes(string $seedHex): void
    {
        $skin = BrandSkin::derive($seedHex, SkinVariant::A);

        foreach (SkinSurface::cases() as $surface) {
            foreach ($skin->ramp($surface)->values() as $value) {
                $measured = SrgbColor::fromHex($value->hex)
                    ->contrastRatio(SrgbColor::fromHex($value->againstHex));

                self::assertGreaterThanOrEqual(
                    $value->floor,
                    $measured,
                    sprintf(
                        '%s tohumu %s/%s çiftinde %.2f verdi; eşik %.1f. Okunabilirlik pazarlık konusu değildir.',
                        $seedHex,
                        $surface->value,
                        $value->role->value,
                        $measured,
                        $value->floor,
                    ),
                );

                self::assertEqualsWithDelta($measured, $value->ratio, 0.01, 'Donan oran ölçülen orandan farklı.');
            }
        }
    }

    /** @return array<string, array{string}> */
    public static function seedTones(): array
    {
        return [
            'açık sarı' => ['#ffe066'],
            'bordo' => ['#7b1e3a'],
            'kırmızı' => ['#c8102e'],
            'turuncu' => ['#ff7a00'],
            'limon' => ['#d4ff00'],
            'çimen yeşili' => ['#2e7d32'],
            'zümrüt' => ['#00c48c'],
            'turkuaz' => ['#00e5ff'],
            'lacivert' => ['#1e3a8a'],
            'mor' => ['#7c3aed'],
            'pembe' => ['#ff4d8d'],
            'kahve' => ['#5b3a29'],
            'saf beyaz' => ['#ffffff'],
            'saf siyah' => ['#000000'],
            'orta gri' => ['#808080'],
        ];
    }

    /** Zaten okunan bir ton BOŞUNA oynatılmaz — dürüstlük iki yönlüdür. */
    public function test_a_tone_that_already_reads_is_left_alone(): void
    {
        $ink = BrandSkin::derive('#7b1e3a', SkinVariant::A)
            ->ramp(SkinSurface::Light)
            ->role(BrandRampRole::AccentInk);

        self::assertFalse($ink->adjusted);
        self::assertSame('#7b1e3a', $ink->hex);
    }

    /** Metin, üstünde durduğu zemine göre seçilir — koyu zemine koyu yazı yazılmaz. */
    public function test_the_label_on_the_accent_fill_is_chosen_not_assumed(): void
    {
        $onDarkFill = BrandSkin::derive('#1e3a8a', SkinVariant::A)
            ->ramp(SkinSurface::Light)
            ->role(BrandRampRole::OnAccentSurface);

        $onLightFill = BrandSkin::derive('#ffe066', SkinVariant::A)
            ->ramp(SkinSurface::Light)
            ->role(BrandRampRole::OnAccentSurface);

        self::assertNotSame(
            $onDarkFill->hex,
            $onLightFill->hex,
            'Koyu ve açık marka dolgusu aynı yazı rengini alamaz.',
        );
    }

    public function test_the_form_axis_is_carried_alongside_the_colour_axis(): void
    {
        $skin = BrandSkin::derive('#c8102e', SkinVariant::D);

        self::assertSame(SkinVariant::D, $skin->variant);
        self::assertSame('d', $skin->toSnapshot()['variant']);
    }

    public function test_the_ramp_is_published_as_tokens_never_as_loose_colour(): void
    {
        $properties = BrandSkin::derive('#c8102e', SkinVariant::A)
            ->cssCustomProperties(SkinSurface::Light);

        self::assertNotSame([], $properties);

        foreach ($properties as $token => $value) {
            self::assertStringStartsWith('--aep-', $token, 'Rampa tasarım sisteminin kendi rolüne yazılır.');
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $value);
        }
    }

    public function test_the_frozen_evidence_survives_a_round_trip(): void
    {
        $skin = BrandSkin::derive('#ffe066', SkinVariant::C);

        self::assertEquals($skin, BrandSkin::fromSnapshot($skin->toSnapshot()));
    }

    /**
     * YAYIN DONAR — kuralın kendisi değişse bile.
     *
     * Ocak'ta yayınlanan bir menü, Mart'ta eşik yükseltilse de Ocak'taki
     * hâliyle görünür. Bu yüzden `fromSnapshot` yeniden HESAPLAMAZ, okur.
     * Aksi hâlde misafirin gördüğü renk, sahibin onaylamadığı bir renk
     * olurdu.
     */
    public function test_reading_a_publication_never_recomputes_the_ramp(): void
    {
        $frozen = BrandSkin::derive('#ffe066', SkinVariant::A)->toSnapshot();
        $frozen['surfaces']['light']['roles']['accent-ink']['hex'] = '#123456';
        $frozen['surfaces']['light']['roles']['accent-ink']['ratio'] = 9.99;

        $reread = BrandSkin::fromSnapshot($frozen);

        self::assertNotNull($reread);
        self::assertSame('#123456', $reread->ramp(SkinSurface::Light)->role(BrandRampRole::AccentInk)->hex);
        self::assertSame(9.99, $reread->ramp(SkinSurface::Light)->role(BrandRampRole::AccentInk)->ratio);
    }

    /** Renk seçmemiş bir yayın da vardır ve o boşluk bir hata değildir. */
    public function test_a_publication_without_a_skin_block_reads_back_as_nothing(): void
    {
        self::assertNull(BrandSkin::fromSnapshot([]));
        self::assertNull(BrandSkin::fromSnapshot(['variant' => 'z']));
    }
}
