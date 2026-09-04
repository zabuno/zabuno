<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CARD-EXPORT-01 — FF-120, sahibin talebi (2026-09-04).
 *
 * "Menümü masalarda pleksiglas içinde göstermek istiyorum, printout
 * alabilmeliyim. Fakat her restoranın marka kimliği ayrı."
 *
 * Kart, tek kodun eski `export.pdf` ucundan AYRIDIR: o, A4'ün ortasına konan
 * çıplak bir karedir (duvara asılacak afiş); bu ise kesilip pleksiglasa
 * girecek, marka kimliği taşıyan bir karttır.
 */
final class QrCardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_card_carries_the_restaurants_own_identity(): void
    {
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode('#1B4332');

        $response = $this->actingAs($owner)->get($this->cardUrl($workspaceId, $qrCodeId, 'svg'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/svg+xml');

        $svg = (string) $response->getContent();

        self::assertStringContainsString('Kebapçı Ali', $svg, 'CARD-EXPORT-01: kartta restoranın adı yazmalı.');
        self::assertStringContainsString('#1B4332', $svg, 'CARD-EXPORT-01: kart markanın rengini kullanmalı.');
        self::assertStringContainsString('Menü için okutun', $svg);
        // Kod HER ZAMAN siyah: taranabilirlik pazarlık konusu değil.
        self::assertStringContainsString('<path fill="#000000"', $svg);
    }

    public function test_the_card_is_measured_in_real_millimetres(): void
    {
        // Pleksiglas standın ölçüsü bellidir; kart o ölçüde basılmazsa içine
        // girmez.
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode();

        $svg = (string) $this->actingAs($owner)
            ->get($this->cardUrl($workspaceId, $qrCodeId, 'svg').'?size=A5&orientation=landscape')
            ->getContent();

        self::assertStringContainsString('width="210mm"', $svg);
        self::assertStringContainsString('height="148mm"', $svg);
    }

    public function test_the_pdf_page_is_the_card_itself_not_a_card_on_a_sheet(): void
    {
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode();

        $response = $this->actingAs($owner)->get($this->cardUrl($workspaceId, $qrCodeId, 'pdf').'?size=A6');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $body = (string) $response->getContent();
        self::assertStringStartsWith('%PDF-', $body);

        // A6 dikey = 105 × 148 mm = 297.6 × 419.5 pt.
        self::assertMatchesRegularExpression('/MediaBox\s*\[\s*0\s+0\s+29[0-9](\.\d+)?\s+4[12][0-9](\.\d+)?/', $body);
    }

    public function test_the_owner_can_write_their_own_sentence(): void
    {
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode();

        $svg = (string) $this->actingAs($owner)
            ->get($this->cardUrl($workspaceId, $qrCodeId, 'svg').'?headline='.urlencode('Menüyü telefonunuzdan açın'))
            ->getContent();

        self::assertStringContainsString('Menüyü telefonunuzdan açın', $svg);
        self::assertStringNotContainsString('Menü için okutun', $svg);
    }

    public function test_an_unreadable_brand_colour_is_refused_on_the_card_too(): void
    {
        // Beyaz üstünde okunmayan bir renk, kartın başlığında da okunmaz.
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode('#FFE066');

        $svg = (string) $this->actingAs($owner)
            ->get($this->cardUrl($workspaceId, $qrCodeId, 'svg').'?cardTheme=banner')
            ->getContent();

        self::assertStringNotContainsString('#FFE066', $svg);
    }

    public function test_an_invalid_size_is_refused_rather_than_guessed(): void
    {
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode();

        $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($this->cardUrl($workspaceId, $qrCodeId, 'svg').'?size=A9')
            ->assertStatus(422);
    }

    public function test_a_foreign_workspace_code_is_a_404(): void
    {
        [, $workspaceId, $qrCodeId] = $this->publishedQrCode();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)->get($this->cardUrl($workspaceId, $qrCodeId, 'svg'))->assertStatus(404);
    }

    public function test_only_svg_and_pdf_exist_and_png_is_not_pretended(): void
    {
        /*
            PNG SUNULMAZ ve bu bir eksiklik değil bir karar: raster bir görsel
            4 cm'lik bir karekodda modül kenarlarını bulanıklaştırır, ve PNG'yi
            ayrı bir bestecinin çizmesi gerekirdi — iki besteci bir gün iki
            farklı kart üretir. Var olmayan bir biçim için 404 dönmek, boş bir
            dosya vermekten dürüsttür.
        */
        [$owner, $workspaceId, $qrCodeId] = $this->publishedQrCode();

        $this->actingAs($owner)->get($this->cardUrl($workspaceId, $qrCodeId, 'png'))->assertStatus(404);
    }

    private function cardUrl(int $workspaceId, int $qrCodeId, string $format): string
    {
        return "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/card.{$format}";
    }

    /** @return array{0: User, 1: int, 2: int} */
    private function publishedQrCode(?string $brandColor = null): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kebapçı', 'slug' => 'card-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Kebapçı Ali', 'slug' => 'kebapci-'.uniqid(),
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'primary_color' => $brandColor,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Merkez',
            'country_code' => 'TR', 'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Adres', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = str_repeat('a', 43);

        $qrCodeId = (int) DB::table('qr_codes')->insertGetId([
            'workspace_id' => $workspaceId, 'location_id' => $locationId, 'token' => $token,
            'state' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $destinationId = (int) DB::table('qr_destinations')->insertGetId([
            'qr_code_id' => $qrCodeId, 'destination_type' => 'published_menu', 'menu_id' => $menuId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('qr_code_current_destinations')->insert([
            'qr_code_id' => $qrCodeId, 'qr_destination_id' => $destinationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId, $qrCodeId];
    }
}
