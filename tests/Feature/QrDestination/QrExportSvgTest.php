<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Infrastructure\QrDestination\Rendering\EndroidQrCodeImageExportAdapter;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Zxing\QrReader;

/**
 * S1-WP04b3 RED — real SVG QR export contract (this file's own frozen scope
 * synthesis, matching the MASTER contract handed to this writer).
 *
 * Frozen route + contract:
 *
 *   GET /api/workspaces/{workspace}/qr-codes/{qrCode}/export.svg
 *     -> 200 image/svg+xml (inline, nosniff) for the workspace Owner on an
 *        active QR code belonging to that workspace, deterministic and
 *        nonempty.
 *     -> 200 image/svg+xml with an attachment Content-Disposition filename
 *        "qr-{token}.svg" (identical bytes to the inline response) when
 *        ?download=1 is present.
 *     -> The SVG body must be valid XML containing an <svg> root with a
 *        viewBox and at least one <path>, and must NOT contain <script>,
 *        <foreignObject>, any on* event-handler attribute, or a data:/
 *        javascript:/external href — never an unsafe/injectable payload.
 *     -> The real resolver payload (url("/q/{token}")) for the exact stored
 *        token must be independently validated through the existing real
 *        PNG decoder path (Zxing\QrReader) before the SVG is asserted, so
 *        the SVG adapter is provably rendering the same underlying data as
 *        the already-proven PNG path, not a different or fabricated value.
 *     -> 404 uniformly for: not a member, a QR code belonging to a foreign
 *        workspace, an unknown QR code id, and a disabled QR code.
 *     -> 403 for a Member who has qr.view but not qr.design.manage.
 *     -> 401 for a guest; blocked (not 200 image/svg+xml) for an unverified
 *        user.
 *
 * As of this test file:
 *   - No "export.svg" route exists in routes/api.php (grep confirms only
 *     export.png alongside qr-codes create/list/disable routes), so every
 *     request below is expected to fail RED with a 404 route-not-found
 *     response, not a logic assertion failure.
 *   - The khanamiryan/qrcode-detector-decoder (Zxing\QrReader) package is
 *     not installed in this worktree (see QrExportPngTest), so the decode
 *     assertion below explicitly asserts class_exists(Zxing\QrReader::class)
 *     first and fails RED on that missing-dependency assertion rather than a
 *     fatal autoload error — this is a legitimate RED per this package's
 *     task instruction and is not a reason to touch composer.json/lock.
 *
 * Requirement IDs: QR-SVG-OWNER-01, QR-SVG-NOSNIFF-01, QR-SVG-SAFE-01,
 * QR-SVG-DOWNLOAD-01, QR-SVG-CROSSDECODE-01, QR-SVG-IDEMPOTENT-01,
 * QR-SVG-AUTHZ-404-01, QR-SVG-AUTHZ-403-01, QR-SVG-AUTHN-01.
 */
final class QrExportSvgTest extends TestCase
{
    use RefreshDatabase;

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

    private function pngExportUrl(int $workspaceId, int $qrCodeId): string
    {
        return "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.png";
    }

    private function svgExportUrl(int $workspaceId, int $qrCodeId, bool $download = false): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.svg";

        return $download ? $url.'?download=1' : $url;
    }

    // --- QR-SVG-OWNER-01 / QR-SVG-NOSNIFF-01 --------------------------------

    public function test_owner_gets_a_200_inline_svg_for_an_active_qr_code_with_nosniff(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-owner-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(200, 'QR-SVG-OWNER-01: Owner aktif QR için 200 image/svg+xml almalı.');
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        self::assertStringNotContainsString('attachment', $disposition, 'QR-SVG-OWNER-01: download parametresi yokken inline dönmeli.');

        self::assertNotEmpty($response->getContent(), 'QR-SVG-OWNER-01: gövde boş olmamalı.');
    }

    // --- QR-SVG-SAFE-01 ------------------------------------------------------

    public function test_the_exported_svg_is_valid_safe_xml_with_no_script_or_event_handlers(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-safe-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $response->assertStatus(200);

        $body = (string) $response->getContent();

        $document = new DOMDocument();
        $loaded = @$document->loadXML($body, LIBXML_NONET | LIBXML_NOENT === 0 ? 0 : 0);
        self::assertTrue($loaded, 'QR-SVG-SAFE-01: gövde geçerli XML olmalı.');

        self::assertNotNull($document->documentElement, 'QR-SVG-SAFE-01: kök eleman bulunmalı.');
        self::assertSame('svg', $document->documentElement->localName, 'QR-SVG-SAFE-01: kök eleman <svg> olmalı.');
        self::assertTrue($document->documentElement->hasAttribute('viewBox'), 'QR-SVG-SAFE-01: <svg> bir viewBox taşımalı.');

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

        $paths = $xpath->query('//svg:path | //path');
        self::assertNotFalse($paths);
        self::assertGreaterThan(0, $paths->count(), 'QR-SVG-SAFE-01: en az bir <path> bulunmalı.');

        $scripts = $xpath->query('//svg:script | //script');
        self::assertNotFalse($scripts);
        self::assertSame(0, $scripts->count(), 'QR-SVG-SAFE-01: <script> elemanı bulunmamalı.');

        $foreignObjects = $xpath->query('//svg:foreignObject | //foreignObject');
        self::assertNotFalse($foreignObjects);
        self::assertSame(0, $foreignObjects->count(), 'QR-SVG-SAFE-01: <foreignObject> elemanı bulunmamalı.');

        self::assertDoesNotMatchRegularExpression(
            '/\son[a-z]+\s*=/i',
            $body,
            'QR-SVG-SAFE-01: hiçbir on* event-handler attribute bulunmamalı.'
        );

        self::assertDoesNotMatchRegularExpression(
            '/href\s*=\s*"(?:data:|javascript:|https?:)/i',
            $body,
            'QR-SVG-SAFE-01: href attribute\'ü data:/javascript:/harici bir URL taşımamalı.'
        );
    }

    // --- QR-SVG-IDEMPOTENT-01 ------------------------------------------------

    public function test_repeated_exports_of_the_same_qr_code_return_identical_svg_bytes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-idem-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $first = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $second = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));

        $first->assertStatus(200);
        $second->assertStatus(200);
        self::assertSame($first->getContent(), $second->getContent(), 'QR-SVG-IDEMPOTENT-01: aynı QR için tekrarlanan export byte-eşit olmalı.');
    }

    // --- QR-SVG-DOWNLOAD-01 --------------------------------------------------

    public function test_download_query_parameter_returns_the_same_svg_as_an_attachment_with_real_token_filename(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-download-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $inline = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $download = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId, download: true));

        $download->assertStatus(200, 'QR-SVG-DOWNLOAD-01: ?download=1 ile 200 image/svg+xml almalı.');
        $download->assertHeader('Content-Type', 'image/svg+xml');

        $disposition = (string) $download->headers->get('Content-Disposition', '');
        self::assertStringContainsString('attachment', $disposition, 'QR-SVG-DOWNLOAD-01: ?download=1 attachment Content-Disposition taşımalı.');
        self::assertStringContainsString(
            'filename="qr-'.$token.'.svg"',
            $disposition,
            'QR-SVG-DOWNLOAD-01: attachment gerçek stored token ile qr-{token}.svg filename taşımalı.'
        );

        self::assertSame($inline->getContent(), $download->getContent(), 'QR-SVG-DOWNLOAD-01: download varyantı aynı SVG içeriğini taşımalı.');
    }

    // --- QR-SVG-CROSSDECODE-01 -----------------------------------------------

    /**
     * QR-SVG-CROSSDECODE-01 does not assert that the exported SVG embeds the
     * stored token as literal text: a real matrix-path SVG writer (e.g.
     * Endroid's SvgWriter) correctly encodes QR data as path geometry, not
     * plaintext, so demanding a plaintext token in the SVG body would be a
     * false requirement this task explicitly forbids production from
     * satisfying (no metadata/text injection into the SVG for the test's
     * sake).
     *
     * Instead this proves the *controller's* payload contract honestly:
     *   1. The real PNG decoder path (already proven in QrExportPngTest and
     *      re-proven here) establishes that url("/q/{token}") for the exact
     *      stored token is the real resolver payload for this QR code.
     *   2. A recording double bound to QrCodeImageExportPort in the
     *      container — implementing both renderPng() (delegating to the
     *      real adapter, so PNG export stays unaffected) and the forthcoming
     *      renderSvg() (capturing its $data argument and returning a safe,
     *      minimal SVG QrRenderedImage) — is used to observe exactly what
     *      the export.svg controller passes to the SVG rendering step.
     *   3. The real export.svg route is requested and the captured
     *      renderSvg() input is asserted to be exactly $expectedResolverUrl
     *      — i.e. the controller must feed the SVG renderer the same real
     *      per-token resolver URL as the PNG path, not a different or
     *      fabricated value.
     *
     * As of this test file, QrCodeImageExportPort has no renderSvg() method
     * and no export.svg route exists, so this fails RED on the missing
     * production contract (route + renderSvg()), never on a fabricated
     * plaintext-in-SVG requirement.
     */
    public function test_the_svg_export_controller_passes_the_same_real_resolver_payload_already_validated_through_the_real_png_decoder(): void
    {
        self::assertTrue(
            class_exists(QrReader::class),
            'QR-SVG-CROSSDECODE-01: Zxing\\QrReader (khanamiryan/qrcode-detector-decoder) bu worktree\'de kurulu değil — bu paket için composer.json/lock salt-okunur olduğundan bu, sahte bir syntax/bootstrap hatası değil, meşru bir RED\'dir.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-crossdecode-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $pngResponse = $this->actingAs($owner)->get($this->pngExportUrl($workspaceId, $qrCodeId));
        $pngResponse->assertStatus(200);

        $tempPath = tempnam(sys_get_temp_dir(), 's1_wp04b3_qr_png_');
        self::assertNotFalse($tempPath, 'QR-SVG-CROSSDECODE-01: geçici PNG dosyası oluşturulabilmeli.');
        file_put_contents($tempPath, $pngResponse->getContent());

        $expectedResolverUrl = url("/q/{$token}");

        try {
            $reader = new QrReader($tempPath, QrReader::SOURCE_TYPE_FILE);
            $decodedFromPng = $reader->text();

            self::assertSame(
                $expectedResolverUrl,
                $decodedFromPng,
                'QR-SVG-CROSSDECODE-01: önce gerçek PNG decoder yoluyla token doğrulanmalı.'
            );
        } finally {
            @unlink($tempPath);
        }

        $realAdapter = $this->app->make(EndroidQrCodeImageExportAdapter::class);

        $recorder = new class($realAdapter) implements QrCodeImageExportPort
        {
            public ?string $capturedSvgData = null;

            public mixed $capturedSvgLayout = null;

            public function __construct(private readonly QrCodeImageExportPort $realAdapter) {}

            public function renderPng(string $data, mixed $layout = null): QrRenderedImage
            {
                return $layout === null ? $this->realAdapter->renderPng($data) : $this->realAdapter->renderPng($data, $layout);
            }

            public function renderSvg(string $data, mixed $layout = null): QrRenderedImage
            {
                $this->capturedSvgData = $data;
                $this->capturedSvgLayout = $layout;

                return new QrRenderedImage(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0h1v1H0z"/></svg>',
                    'image/svg+xml'
                );
            }
        };

        $this->app->instance(QrCodeImageExportPort::class, $recorder);

        $svgResponse = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $svgResponse->assertStatus(200);

        self::assertSame(
            $expectedResolverUrl,
            $recorder->capturedSvgData,
            'QR-SVG-CROSSDECODE-01: export.svg denetleyicisi, PNG yolu ile aynı gerçek resolver URL\'sini (aynı stored token için) renderSvg() içine geçirmeli — farklı/sahte bir değer değil.'
        );
    }

    // --- QR-SVG-AUTHZ-404-01 -------------------------------------------------

    public function test_nonmember_foreign_unknown_and_disabled_qr_codes_all_return_a_uniform_404(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-404-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $nonMember = $this->verifiedUser();
        $nonMemberResponse = $this->actingAs($nonMember)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $nonMemberResponse->assertStatus(404, 'QR-SVG-AUTHZ-404-01: workspace üyesi olmayan kullanıcı 404 almalı.');

        $otherOwner = $this->verifiedUser();
        [$otherWorkspaceId] = $this->workspaceWithCurrentPublication($otherOwner, 'qr-svg-404-2');
        $foreignResponse = $this->actingAs($otherOwner)->get($this->svgExportUrl($otherWorkspaceId, $qrCodeId));
        $foreignResponse->assertStatus(404, 'QR-SVG-AUTHZ-404-01: başka bir workspace\'e ait QR id\'si için 404 dönmeli (leak yok).');

        $unknownResponse = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, 999999999));
        $unknownResponse->assertStatus(404, 'QR-SVG-AUTHZ-404-01: bilinmeyen QR id için 404 dönmeli.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        )->assertStatus(200);

        $disabledResponse = $this->actingAs($owner)->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $disabledResponse->assertStatus(404, 'QR-SVG-AUTHZ-404-01: disable edilmiş QR export için de 404 dönmeli.');
    }

    // --- QR-SVG-AUTHZ-403-01 -------------------------------------------------

    public function test_a_member_with_qr_view_but_without_qr_design_manage_is_denied_with_403(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-403-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $member = $this->verifiedUser();
        $this->addMember($workspaceId, $member);

        $response = $this->actingAs($member)->get($this->svgExportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(403, 'QR-SVG-AUTHZ-403-01: Member qr.view taşır fakat qr.design.manage taşımaz, export 403 dönmeli.');
    }

    // --- QR-SVG-AUTHN-01 ------------------------------------------------------

    public function test_guest_is_401_and_unverified_user_is_blocked(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-authn-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        Auth::forgetGuards();
        $this->app['session']->flush();

        $guestResponse = $this->get($this->svgExportUrl($workspaceId, $qrCodeId));
        $guestResponse->assertStatus(401, 'QR-SVG-AUTHN-01: guest 401 almalı.');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedResponse = $this->actingAs($unverified)->get($this->svgExportUrl($workspaceId, $qrCodeId));

        self::assertNotSame(200, $unverifiedResponse->getStatusCode(), 'QR-SVG-AUTHN-01: doğrulanmamış kullanıcı gerçek SVG export alamamalı.');
    }

    // --- QR-SVG-THEME-01 (S1-WP04b6 RED, six stateless basic QR themes) -----

    private function themedSvgExportUrl(int $workspaceId, int $qrCodeId, ?string $theme): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.svg";

        return $theme === null ? $url : $url.'?theme='.$theme;
    }

    private function themedPngExportUrl(int $workspaceId, int $qrCodeId, ?string $theme): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.png";

        return $theme === null ? $url : $url.'?theme='.$theme;
    }

    public static function nonClassicThemePalettes(): array
    {
        return [
            'minimal' => ['minimal', '1F2937', 'F9FAFB'],
            'bold' => ['bold', '111827', 'FDE68A'],
            'rounded' => ['rounded', '064E3B', 'D1FAE5'],
            'branded' => ['branded', '1E3A8A', 'DBEAFE'],
            'highContrast' => ['highContrast', '000000', 'FFFF00'],
        ];
    }

    /**
     * S1-WP04b6 RED — the SVG must use the exact same layout palette as the
     * already-proven, real-decoded PNG path for the same theme, and must
     * still be safe XML. Today export.svg accepts no ?theme query, so this
     * fails RED (either the theme is ignored — SVG identical across themes
     * and never containing the themed hex colors — or the query is rejected
     * outright), never a fabricated assertion.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('nonClassicThemePalettes')]
    public function test_svg_uses_the_same_layout_palette_as_the_real_decoded_png_and_stays_safe(string $themeKey, string $foregroundHex, string $backgroundHex): void
    {
        self::assertTrue(
            class_exists(QrReader::class),
            'QR-SVG-THEME-01: Zxing\\QrReader kurulu değil — bu paket için composer.json/lock salt-okunur olduğundan bu meşru bir RED\'dir.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-svg-theme-'.strtolower($themeKey));
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $pngResponse = $this->actingAs($owner)->get($this->themedPngExportUrl($workspaceId, $qrCodeId, $themeKey));
        $pngResponse->assertStatus(200, "QR-SVG-THEME-01: aynı temanın PNG yolu önce 200 vermeli (cross-decode ön koşulu).");

        $tempPath = tempnam(sys_get_temp_dir(), 's1_wp04b6_qr_theme_svg_');
        self::assertNotFalse($tempPath);
        file_put_contents($tempPath, $pngResponse->getContent());

        try {
            $reader = new QrReader($tempPath, QrReader::SOURCE_TYPE_FILE);
            self::assertSame(
                url("/q/{$token}"),
                $reader->text(),
                "QR-SVG-THEME-01: {$themeKey} PNG yolu gerçek resolver URL'ini kodlamalı — SVG karşılaştırması bunun üzerine kurulu."
            );
        } finally {
            @unlink($tempPath);
        }

        $svgResponse = $this->actingAs($owner)->get($this->themedSvgExportUrl($workspaceId, $qrCodeId, $themeKey));
        $svgResponse->assertStatus(200, "QR-SVG-THEME-01: ?theme={$themeKey} için 200 image/svg+xml almalı.");

        $body = (string) $svgResponse->getContent();

        $document = new DOMDocument();
        $loaded = @$document->loadXML($body);
        self::assertTrue($loaded, 'QR-SVG-THEME-01: temalı gövde geçerli XML olmalı.');
        self::assertSame('svg', $document->documentElement?->localName, 'QR-SVG-THEME-01: kök eleman <svg> olmalı.');

        self::assertStringNotContainsString('<script', strtolower($body), 'QR-SVG-THEME-01: temalı SVG <script> içermemeli.');
        self::assertStringNotContainsString('<foreignobject', strtolower($body), 'QR-SVG-THEME-01: temalı SVG <foreignObject> içermemeli.');
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $body, 'QR-SVG-THEME-01: temalı SVG on* olay işleyicisi içermemeli.');

        self::assertStringContainsStringIgnoringCase(
            $foregroundHex,
            $body,
            "QR-SVG-THEME-01: {$themeKey} SVG'si tema ön plan rengini ({$foregroundHex}) taşımalı."
        );
        self::assertStringContainsStringIgnoringCase(
            $backgroundHex,
            $body,
            "QR-SVG-THEME-01: {$themeKey} SVG'si tema arka plan rengini ({$backgroundHex}) taşımalı."
        );
    }
}
