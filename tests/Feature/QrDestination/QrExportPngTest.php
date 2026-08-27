<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Zxing\QrReader;

/**
 * S1-WP04b2 RED — validated PNG QR export contract (this file's own frozen
 * scope synthesis, matching the MASTER contract handed to this writer).
 *
 * Frozen route + contract:
 *
 *   GET /api/workspaces/{workspace}/qr-codes/{qrCode}/export.png
 *     -> 200 image/png (inline) for the workspace Owner on an active QR code
 *        belonging to that workspace.
 *     -> 200 image/png with a Content-Disposition: attachment filename when
 *        ?download=1 is present.
 *     -> 404 uniformly for: not a member, a QR code belonging to a foreign
 *        workspace, an unknown QR code id, and a disabled QR code.
 *     -> 403 for a Member who has qr.view but not qr.design.manage.
 *     -> 401 for a guest; blocked (not 200 image/png) for an unverified user.
 *
 * The response body, when 200, must be a real PNG that a real QR decoder
 * (Zxing\QrReader) decodes back to exactly url("/q/{token}") for the real
 * token of that QR code — never a placeholder/blank image.
 *
 * As of this test file:
 *   - No "export.png" route exists in routes/api.php (grep confirms only
 *     qr-codes create/list/disable routes exist), so every request below
 *     is expected to fail RED with a 404 route-not-found response, not a
 *     logic assertion failure.
 *   - The khanamiryan/qrcode-detector-decoder (Zxing\QrReader) package is
 *     not installed in this worktree (composer.json/composer.lock have no
 *     "zxing" entry and vendor/ has no matching path), so the decode
 *     assertions below explicitly assert class_exists(Zxing\QrReader::class)
 *     first and fail RED on that missing-dependency assertion rather than a
 *     fatal autoload error — this is a legitimate RED per this package's
 *     task instruction and is not a reason to touch composer.json/lock.
 *
 * Requirement IDs: QR-PNG-OWNER-01, QR-PNG-DOWNLOAD-01, QR-PNG-DECODE-01,
 * QR-PNG-IDEMPOTENT-01, QR-PNG-AUTHZ-404-01, QR-PNG-AUTHZ-403-01,
 * QR-PNG-AUTHN-01.
 */
final class QrExportPngTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The exporter accepts a candidate only if the real decoder reads the
     * exact payload back out of it with this hint set, so the assertions
     * below must read it back the same way. Without the hint Zxing samples
     * only every 6th row when hunting finder patterns and calls a valid QR
     * unreadable for 7.3% of payloads (measured over 16 000 tokens), so
     * these tests would disagree with the exporter that produced the very
     * image they are reading. What is asserted is unchanged: the decoded
     * text must still equal the resolver URL exactly.
     */
    private const array DECODER_HINTS = ['TRY_HARDER' => true];

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * @return array{0: int, 1: int, 2: int} [workspaceId, locationId, menuId]
     */
    private function workspaceWithCurrentPublication(User $owner, string $slugSeed): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$slugSeed,
            'slug' => $slugSeed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Marka '.$slugSeed,
            'slug' => $slugSeed.'-brand',
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId,
            'brand_id' => $brandId,
            'display_name' => 'Şube '.$slugSeed,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Adres '.$slugSeed,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId,
            'location_id' => $locationId,
            'name' => 'Ana Menü',
            'state' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $menuId,
            'name' => 'Starters',
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $workspaceId,
            'name' => 'Kahve',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_items')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'price_minor_amount' => 4250,
            'currency_code' => 'TRY',
            'position' => 0,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $snapshot = json_encode([
            'categories' => [
                ['name' => 'Starters', 'menuItems' => [['productName' => 'Kahve', 'priceMinorAmount' => 4250, 'currencyCode' => 'TRY']]],
            ],
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId,
            'menu_id' => $menuId,
            'location_id' => $locationId,
            'version' => 1,
            'state' => 'published',
            'snapshot' => $snapshot,
            'published_by' => $owner->id,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId,
            'current_publication_id' => $publicationId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$workspaceId, $locationId, $menuId];
    }

    private function addMember(int $workspaceId, User $member): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $member->id,
            'role' => 'member',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: string} [qrCodeId, token]
     */
    private function createActiveQrCode(User $owner, int $workspaceId, int $locationId, int $menuId): array
    {
        $created = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->postJson(
            "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}/qr-codes",
            ['menuId' => $menuId]
        )->assertStatus(201);

        return [(int) $created->json('id'), (string) $created->json('token')];
    }

    private function exportUrl(int $workspaceId, int $qrCodeId, bool $download = false): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.png";

        return $download ? $url.'?download=1' : $url;
    }

    // --- QR-PNG-OWNER-01 --------------------------------------------------

    public function test_owner_gets_a_200_inline_png_for_an_active_qr_code(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-owner-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(200, 'QR-PNG-OWNER-01: Owner aktif QR için 200 PNG almalı.');
        $response->assertHeader('Content-Type', 'image/png');

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        self::assertStringNotContainsString('attachment', $disposition, 'QR-PNG-OWNER-01: download parametresi yokken inline dönmeli, attachment olmamalı.');

        self::assertNotEmpty($response->getContent(), 'QR-PNG-OWNER-01: gövde boş olmamalı.');
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", (string) $response->getContent(), 'QR-PNG-OWNER-01: gövde gerçek bir PNG magic byte imzasıyla başlamalı.');
    }

    // --- QR-PNG-IDEMPOTENT-01 ----------------------------------------------

    public function test_repeated_exports_of_the_same_qr_code_return_identical_png_bytes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-idem-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $first = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));
        $second = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));

        $first->assertStatus(200);
        $second->assertStatus(200);
        self::assertSame($first->getContent(), $second->getContent(), 'QR-PNG-IDEMPOTENT-01: aynı QR için tekrarlanan export byte-eşit olmalı (stateless/idempotent).');
    }

    // --- QR-PNG-DOWNLOAD-01 -------------------------------------------------

    public function test_download_query_parameter_returns_the_same_png_as_an_attachment(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-download-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $inline = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));
        $download = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId, download: true));

        $download->assertStatus(200, 'QR-PNG-DOWNLOAD-01: ?download=1 ile 200 PNG almalı.');
        $download->assertHeader('Content-Type', 'image/png');

        $disposition = (string) $download->headers->get('Content-Disposition', '');
        self::assertStringContainsString('attachment', $disposition, 'QR-PNG-DOWNLOAD-01: ?download=1 attachment Content-Disposition taşımalı.');
        self::assertMatchesRegularExpression('/filename="?[^";]+\.png"?/i', $disposition, 'QR-PNG-DOWNLOAD-01: attachment gerçek bir .png filename taşımalı.');

        self::assertSame($inline->getContent(), $download->getContent(), 'QR-PNG-DOWNLOAD-01: download varyantı aynı PNG içeriğini taşımalı, sahte/ayrı bir gövde değil.');
    }

    // --- QR-PNG-DECODE-01 ---------------------------------------------------

    public function test_the_exported_png_decodes_to_exactly_the_real_resolver_url(): void
    {
        self::assertTrue(
            class_exists(QrReader::class),
            'QR-PNG-DECODE-01: Zxing\\QrReader (khanamiryan/qrcode-detector-decoder) bu worktree\'de kurulu değil — bu paket için composer.json/lock salt-okunur olduğundan bu, sahte bir syntax/bootstrap hatası değil, meşru bir RED\'dir.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-decode-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));
        $response->assertStatus(200);

        $tempPath = tempnam(sys_get_temp_dir(), 's1_wp04b2_qr_');
        self::assertNotFalse($tempPath, 'QR-PNG-DECODE-01: geçici dosya oluşturulabilmeli.');
        file_put_contents($tempPath, $response->getContent());

        try {
            $reader = new QrReader($tempPath, QrReader::SOURCE_TYPE_FILE);
            $decoded = $reader->text(self::DECODER_HINTS);

            self::assertSame(
                url("/q/{$token}"),
                $decoded,
                'QR-PNG-DECODE-01: PNG içindeki QR gerçek token için url("/q/{token}") değerini kodlamalı — sahte/placeholder içerik değil.'
            );
        } finally {
            @unlink($tempPath);
        }
    }

    // --- QR-PNG-AUTHZ-404-01 -------------------------------------------------

    public function test_nonmember_foreign_unknown_and_disabled_qr_codes_all_return_a_uniform_404(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-404-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $nonMember = $this->verifiedUser();
        $nonMemberResponse = $this->actingAs($nonMember)->get($this->exportUrl($workspaceId, $qrCodeId));
        $nonMemberResponse->assertStatus(404, 'QR-PNG-AUTHZ-404-01: workspace üyesi olmayan kullanıcı 404 almalı.');

        $otherOwner = $this->verifiedUser();
        [$otherWorkspaceId] = $this->workspaceWithCurrentPublication($otherOwner, 'qr-png-404-2');
        $foreignResponse = $this->actingAs($otherOwner)->get($this->exportUrl($otherWorkspaceId, $qrCodeId));
        $foreignResponse->assertStatus(404, 'QR-PNG-AUTHZ-404-01: başka bir workspace\'e ait QR id\'si için 404 dönmeli (leak yok).');

        $unknownResponse = $this->actingAs($owner)->get($this->exportUrl($workspaceId, 999999999));
        $unknownResponse->assertStatus(404, 'QR-PNG-AUTHZ-404-01: bilinmeyen QR id için 404 dönmeli.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        )->assertStatus(200);

        $disabledResponse = $this->actingAs($owner)->get($this->exportUrl($workspaceId, $qrCodeId));
        $disabledResponse->assertStatus(404, 'QR-PNG-AUTHZ-404-01: disable edilmiş QR export için de 404 dönmeli.');
    }

    // --- QR-PNG-AUTHZ-403-01 -------------------------------------------------

    public function test_a_member_with_qr_view_but_without_qr_design_manage_is_denied_with_403(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-403-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $member = $this->verifiedUser();
        $this->addMember($workspaceId, $member);

        $response = $this->actingAs($member)->get($this->exportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(403, 'QR-PNG-AUTHZ-403-01: Member qr.view taşır fakat qr.design.manage taşımaz, export 403 dönmeli.');
    }

    // --- QR-PNG-AUTHN-01 -----------------------------------------------------

    public function test_guest_is_401_and_unverified_user_is_blocked(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-authn-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        Auth::forgetGuards();
        $this->app['session']->flush();

        $guestResponse = $this->get($this->exportUrl($workspaceId, $qrCodeId));
        $guestResponse->assertStatus(401, 'QR-PNG-AUTHN-01: guest 401 almalı.');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedResponse = $this->actingAs($unverified)->get($this->exportUrl($workspaceId, $qrCodeId));

        self::assertNotSame(200, $unverifiedResponse->getStatusCode(), 'QR-PNG-AUTHN-01: doğrulanmamış kullanıcı gerçek PNG export alamamalı.');
    }

    // --- QR-PNG-THEME-01 (S1-WP04b6 RED, six stateless basic QR themes) -----

    /**
     * S1-WP04b6 RED — frozen theme query contract handed to this writer.
     * Today export.png accepts no ?theme query at all, so every themed
     * request below is expected to fail RED (either the theme is silently
     * ignored, producing byte-identical PNGs across themes, or the ?theme
     * query is rejected outright) rather than passing the real contract.
     */
    private function themeExportUrl(int $workspaceId, int $qrCodeId, ?string $theme = null): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.png";

        return $theme === null ? $url : $url.'?theme='.$theme;
    }

    public function test_omitted_theme_and_explicit_classic_theme_produce_byte_identical_png(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-theme-classic-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $omitted = $this->actingAs($owner)->get($this->themeExportUrl($workspaceId, $qrCodeId));
        $explicitClassic = $this->actingAs($owner)->get($this->themeExportUrl($workspaceId, $qrCodeId, 'classic'));

        $omitted->assertStatus(200, 'QR-PNG-THEME-01: theme query olmadan 200 PNG almalı.');
        $explicitClassic->assertStatus(200, 'QR-PNG-THEME-01: ?theme=classic ile 200 PNG almalı.');

        self::assertSame(
            $omitted->getContent(),
            $explicitClassic->getContent(),
            'QR-PNG-THEME-01: theme query olmadan ve ?theme=classic ile üretilen PNG byte-eşit olmalı.'
        );
    }

    public static function nonClassicThemeKeys(): array
    {
        return [
            'minimal' => ['minimal'],
            'bold' => ['bold'],
            'rounded' => ['rounded'],
            'branded' => ['branded'],
            'highContrast' => ['highContrast'],
        ];
    }

    #[DataProvider('nonClassicThemeKeys')]
    public function test_every_nonclassic_theme_real_decodes_to_the_resolver_url_and_differs_in_bytes_from_classic(string $themeKey): void
    {
        self::assertTrue(
            class_exists(QrReader::class),
            'QR-PNG-THEME-01: Zxing\\QrReader kurulu değil — bu paket için composer.json/lock salt-okunur olduğundan bu meşru bir RED\'dir.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-theme-'.strtolower($themeKey));
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $classic = $this->actingAs($owner)->get($this->themeExportUrl($workspaceId, $qrCodeId, 'classic'));
        $themed = $this->actingAs($owner)->get($this->themeExportUrl($workspaceId, $qrCodeId, $themeKey));

        $classic->assertStatus(200);
        $themed->assertStatus(200, "QR-PNG-THEME-01: ?theme={$themeKey} için 200 PNG almalı.");

        self::assertNotSame(
            $classic->getContent(),
            $themed->getContent(),
            "QR-PNG-THEME-01: {$themeKey} teması classic'ten farklı byte üretmeli (görünür palet farkı)."
        );

        $tempPath = tempnam(sys_get_temp_dir(), 's1_wp04b6_qr_theme_');
        self::assertNotFalse($tempPath);
        file_put_contents($tempPath, $themed->getContent());

        try {
            $reader = new QrReader($tempPath, QrReader::SOURCE_TYPE_FILE);
            $decoded = $reader->text(self::DECODER_HINTS);

            self::assertSame(
                url("/q/{$token}"),
                $decoded,
                "QR-PNG-THEME-01: {$themeKey} teması gerçek resolver URL'ini kodlamalı — sahte/placeholder içerik değil."
            );
        } finally {
            @unlink($tempPath);
        }
    }

    public static function invalidThemeKeys(): array
    {
        return [
            'unknown theme name' => ['classicX'],
            'wrong case (case-sensitive contract)' => ['Classic'],
            'wrong case camel key' => ['highcontrast'],
            'empty string' => [''],
        ];
    }

    #[DataProvider('invalidThemeKeys')]
    public function test_invalid_theme_returns_422_and_no_image_for_an_otherwise_authorized_request(string $invalidTheme): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-png-theme-invalid-'.strtolower(preg_replace('/[^a-z0-9]/i', '', $invalidTheme) ?: 'empty'));
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->themeExportUrl($workspaceId, $qrCodeId, $invalidTheme));

        $response->assertStatus(422, "QR-PNG-THEME-01: geçersiz theme={$invalidTheme} için 422 dönmeli.");

        $body = (string) $response->getContent();
        self::assertStringStartsNotWith("\x89PNG", $body, 'QR-PNG-THEME-01: 422 gövdesi bir PNG imzasıyla başlamamalı.');
    }
}
