<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Application\QrDestination\Dto\QrRenderedImage;
use App\Application\QrDestination\Port\QrCodeImageExportPort;
use App\Application\QrDestination\Port\QrCodePdfExportPort;
use App\Infrastructure\QrDestination\Rendering\EndroidQrCodeImageExportAdapter;
use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mpdf\Mpdf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S1-WP01A — first real server-side single-QR A4 portrait PDF export
 * (frozen MASTER contract handed to this writer). Originally authored RED,
 * before the export.pdf route, QrCodePdfExportPort and mpdf/mpdf existed in
 * this worktree; against the current production snapshot the targeted
 * assertions below are GREEN (see "As of this test file" below for the
 * current-snapshot status of each historical RED condition).
 *
 * Frozen route + contract:
 *
 *   GET /api/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf
 *     -> 200 application/pdf (inline) for the workspace Owner on an active
 *        QR code belonging to that workspace: a deterministic, nonempty PDF
 *        starting with the "%PDF-" magic header and containing a trailing
 *        "%%EOF" marker, with exactly one page sized A4 portrait and at
 *        least one embedded image object (the rendered QR raster) — never a
 *        placeholder/blank document.
 *     -> 200 application/pdf with a Content-Disposition: attachment;
 *        filename="qr-{token}.pdf" (the real stored token) header, and
 *        byte-identical body to the inline response, when ?download=1 is
 *        present.
 *     -> The controller must pass the exact url("/q/{token}") payload (the
 *        same real per-token resolver URL already proven through PNG/SVG
 *        export) to QrCodeImageExportPort::renderPng(), then pass the exact
 *        bytes that call returns, together with the exact stored token, into
 *        the future QrCodePdfExportPort::renderPdf() step — never a
 *        different or fabricated value. This test never asserts the token
 *        appears as plaintext inside the PDF body: a real PDF page embeds a
 *        QR raster as an XObject image, not text, so a plaintext-token
 *        requirement would be a false contract this task explicitly forbids.
 *     -> 404 uniformly for: not a member, a QR code belonging to a foreign
 *        workspace, an unknown QR code id, and a disabled QR code.
 *     -> 403 for a Member who has qr.view but not qr.design.manage.
 *     -> 401 for a guest; blocked (not 200 application/pdf) for an
 *        unverified user.
 *
 * As of this test file (current production snapshot):
 *   - The "export.pdf" route now exists in routes/api.php and is exercised
 *     directly by the tests below (no route-not-found fallback).
 *   - App\Application\QrDestination\Port\QrCodePdfExportPort now exists in
 *     this worktree; the payload-capture test below still asserts
 *     interface_exists(QrCodePdfExportPort::class) first as a guard, and
 *     this guard now passes, so the test proceeds to bind the real recorder
 *     doubles and exercise the real endpoint.
 *   - mpdf/mpdf is now installed in this worktree at the exact required
 *     runtime version 8.3.1. The dependency assertion below only checks
 *     class_exists(\Mpdf\Mpdf::class) and the Composer-reported installed
 *     version string via Composer\InstalledVersions — it never accesses
 *     Mpdf::$format or any other instance/property, since mPDF does not
 *     expose a public $format property (accessing one would be an
 *     assertion against a nonexistent contract, not this package's real
 *     behavior). A4 portrait is proven only through the real endpoint's raw
 *     MediaBox bytes, exactly like the PNG/SVG structural assertions.
 *   - No optional PDF-structure parser (e.g. FPDI) is used by this file:
 *     setasign/fpdi transitively requires the separate global FPDF base
 *     class, which is neither installed nor required by mPDF, and this
 *     writer may not add fpdf or any further dependency. Page count/size
 *     structural proof (QR-PDF-A4PORTRAIT-01, QR-PDF-IMAGE-01) instead comes
 *     entirely from regex assertions against the real endpoint's raw PDF
 *     bytes.
 *
 * Requirement IDs: QR-PDF-OWNER-01, QR-PDF-A4PORTRAIT-01, QR-PDF-IMAGE-01,
 * QR-PDF-DOWNLOAD-01, QR-PDF-IDEMPOTENT-01, QR-PDF-MPDF-DEP-01,
 * QR-PDF-PORT-PAYLOAD-01, QR-PDF-AUTHZ-404-01, QR-PDF-AUTHZ-403-01,
 * QR-PDF-AUTHN-01.
 *
 * S1-WP04b5 extension — real ISO 216 paper size + orientation PDF export
 * (frozen MASTER contract for this RED writer). The route stays
 * GET .../export.pdf; it now additionally accepts optional ?paperSize= (one
 * of A4,B4,A5,B5,A6,B6,A7,B7, default A4) and ?orientation= (portrait|
 * landscape, default portrait) query parameters. The no-query request must
 * stay exactly A4 portrait (proven above). Every one of the 16 authorized
 * paperSize x orientation combinations must return a single-page PDF whose
 * /MediaBox matches the ISO mm size converted to points (landscape swaps
 * width/height), with only the tolerance a real mPDF rounding pass
 * introduces. An invalid paperSize or orientation must return 422 with no
 * PDF body, for an otherwise fully authorized real QR code. The controller's
 * payload contract into the future two-argument-compatible
 * QrCodePdfExportPort::renderPdf() step must forward the exact selected
 * paperSize/orientation once production accepts them.
 *
 * Requirement IDs: QR-PDF-SIZE-MATRIX-01, QR-PDF-SIZE-DEFAULT-01,
 * QR-PDF-SIZE-IDEMPOTENT-01, QR-PDF-SIZE-INVALID-01,
 * QR-PDF-SIZE-PORT-PAYLOAD-01.
 */
final class QrExportPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ISO 216 mm width/height per paper size, frozen by the MASTER contract.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const array ISO_MM_SIZES = [
        'A4' => [210.0, 297.0],
        'B4' => [250.0, 353.0],
        'A5' => [148.0, 210.0],
        'B5' => [176.0, 250.0],
        'A6' => [105.0, 148.0],
        'B6' => [125.0, 176.0],
        'A7' => [74.0, 105.0],
        'B7' => [88.0, 125.0],
    ];

    private const float MM_TO_PT = 72.0 / 25.4;

    private const float MEDIABOX_TOLERANCE_PT = 3.0;

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

    private function pdfExportUrl(int $workspaceId, int $qrCodeId, bool $download = false): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.pdf";

        return $download ? $url.'?download=1' : $url;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function pdfExportUrlWithQuery(int $workspaceId, int $qrCodeId, array $query = []): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.pdf";

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function extractSingleMediaBox(string $body): array
    {
        self::assertMatchesRegularExpression(
            '/\/Type\s*\/Page(?!s)/',
            $body,
            'QR-PDF-SIZE-MATRIX-01: gövde en az bir /Type /Page nesnesi içermeli.'
        );

        preg_match_all('/\/Type\s*\/Page(?!s)/', $body, $pageMatches);
        self::assertCount(
            1,
            $pageMatches[0],
            'QR-PDF-SIZE-MATRIX-01: PDF tam olarak tek bir sayfa içermeli.'
        );

        self::assertMatchesRegularExpression(
            '/\/Subtype\s*\/Image/',
            $body,
            'QR-PDF-SIZE-MATRIX-01: PDF içinde en az bir gömülü görüntü (QR raster) nesnesi bulunmalı.'
        );

        self::assertTrue(
            preg_match('/\/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]/', $body, $matches) === 1,
            'QR-PDF-SIZE-MATRIX-01: gövde ayrıştırılabilir bir /MediaBox içermeli.'
        );

        return [(float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4]];
    }

    private function assertMediaBoxMatchesIsoSize(string $body, string $paperSize, string $orientation): void
    {
        [$mmWidth, $mmHeight] = self::ISO_MM_SIZES[$paperSize];

        $ptWidth = $mmWidth * self::MM_TO_PT;
        $ptHeight = $mmHeight * self::MM_TO_PT;

        if ($orientation === 'landscape') {
            [$ptWidth, $ptHeight] = [$ptHeight, $ptWidth];
        }

        [$x0, $y0, $x1, $y1] = $this->extractSingleMediaBox($body);

        self::assertEqualsWithDelta(0.0, $x0, self::MEDIABOX_TOLERANCE_PT, 'QR-PDF-SIZE-MATRIX-01: MediaBox x0 sıfır olmalı.');
        self::assertEqualsWithDelta(0.0, $y0, self::MEDIABOX_TOLERANCE_PT, 'QR-PDF-SIZE-MATRIX-01: MediaBox y0 sıfır olmalı.');

        self::assertEqualsWithDelta(
            $ptWidth,
            $x1 - $x0,
            self::MEDIABOX_TOLERANCE_PT,
            "QR-PDF-SIZE-MATRIX-01: {$paperSize}/{$orientation} MediaBox genişliği ISO 216 mm boyutunun pt karşılığıyla eşleşmeli."
        );
        self::assertEqualsWithDelta(
            $ptHeight,
            $y1 - $y0,
            self::MEDIABOX_TOLERANCE_PT,
            "QR-PDF-SIZE-MATRIX-01: {$paperSize}/{$orientation} MediaBox yüksekliği ISO 216 mm boyutunun pt karşılığıyla eşleşmeli."
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function paperSizeOrientationCombinations(): array
    {
        $cases = [];

        foreach (array_keys(self::ISO_MM_SIZES) as $paperSize) {
            foreach (['portrait', 'landscape'] as $orientation) {
                $cases["{$paperSize}-{$orientation}"] = [$paperSize, $orientation];
            }
        }

        return $cases;
    }

    private function assertSingleA4PortraitPageWithEmbeddedImage(string $body): void
    {
        self::assertMatchesRegularExpression(
            '/\/Type\s*\/Page(?!s)/',
            $body,
            'QR-PDF-A4PORTRAIT-01: gövde en az bir /Type /Page nesnesi içermeli.'
        );

        preg_match_all('/\/Type\s*\/Page(?!s)/', $body, $pageMatches);
        self::assertCount(
            1,
            $pageMatches[0],
            'QR-PDF-A4PORTRAIT-01: PDF tam olarak tek bir sayfa içermeli, birden fazla değil.'
        );

        self::assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+59[0-9](?:\.\d+)?\s+84[0-9](?:\.\d+)?\s*\]/',
            $body,
            'QR-PDF-A4PORTRAIT-01: sayfa A4 portrait (yaklaşık 595x842pt, genişlik < yükseklik) MediaBox taşımalı.'
        );

        self::assertMatchesRegularExpression(
            '/\/Subtype\s*\/Image/',
            $body,
            'QR-PDF-IMAGE-01: PDF içinde en az bir gömülü görüntü (QR raster) nesnesi bulunmalı.'
        );
    }

    // --- QR-PDF-OWNER-01 / QR-PDF-A4PORTRAIT-01 / QR-PDF-IMAGE-01 ------------

    public function test_owner_gets_a_200_inline_pdf_for_an_active_qr_code_with_one_a4_portrait_page_and_an_embedded_image(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-owner-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(200, 'QR-PDF-OWNER-01: Owner aktif QR için 200 application/pdf almalı.');
        $response->assertHeader('Content-Type', 'application/pdf');

        $disposition = (string) $response->headers->get('Content-Disposition', '');
        self::assertStringNotContainsString('attachment', $disposition, 'QR-PDF-OWNER-01: download parametresi yokken inline dönmeli, attachment olmamalı.');

        $body = (string) $response->getContent();
        self::assertNotEmpty($body, 'QR-PDF-OWNER-01: gövde boş olmamalı.');
        self::assertStringStartsWith('%PDF-', $body, 'QR-PDF-OWNER-01: gövde gerçek bir PDF magic header ile başlamalı.');
        self::assertStringContainsString('%%EOF', $body, 'QR-PDF-OWNER-01: gövde bir %%EOF sonlandırıcısı içermeli.');

        $this->assertSingleA4PortraitPageWithEmbeddedImage($body);
    }

    // --- QR-PDF-IDEMPOTENT-01 -------------------------------------------------

    public function test_repeated_exports_of_the_same_qr_code_return_identical_pdf_bytes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-idem-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $first = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $second = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));

        $first->assertStatus(200);
        $second->assertStatus(200);
        self::assertSame($first->getContent(), $second->getContent(), 'QR-PDF-IDEMPOTENT-01: aynı QR için tekrarlanan export byte-eşit olmalı (stateless/idempotent).');
    }

    // --- QR-PDF-DOWNLOAD-01 ---------------------------------------------------

    public function test_download_query_parameter_returns_the_same_pdf_as_an_attachment_with_real_token_filename(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-download-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $inline = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $download = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId, download: true));

        $download->assertStatus(200, 'QR-PDF-DOWNLOAD-01: ?download=1 ile 200 application/pdf almalı.');
        $download->assertHeader('Content-Type', 'application/pdf');

        $disposition = (string) $download->headers->get('Content-Disposition', '');
        self::assertStringContainsString('attachment', $disposition, 'QR-PDF-DOWNLOAD-01: ?download=1 attachment Content-Disposition taşımalı.');
        self::assertStringContainsString(
            'filename="qr-'.$token.'.pdf"',
            $disposition,
            'QR-PDF-DOWNLOAD-01: attachment gerçek stored token ile qr-{token}.pdf filename taşımalı.'
        );

        self::assertSame($inline->getContent(), $download->getContent(), 'QR-PDF-DOWNLOAD-01: download varyantı aynı PDF içeriğini taşımalı, sahte/ayrı bir gövde değil.');
    }

    // --- QR-PDF-MPDF-DEP-01 ---------------------------------------------------

    public function test_the_real_pdf_adapter_uses_the_exact_required_mpdf_8_3_1_runtime_dependency(): void
    {
        self::assertTrue(
            class_exists(Mpdf::class),
            'QR-PDF-MPDF-DEP-01: mpdf/mpdf bu worktree\'de kurulu olmalı.'
        );

        self::assertSame(
            'v8.3.1',
            InstalledVersions::getPrettyVersion('mpdf/mpdf'),
            'QR-PDF-MPDF-DEP-01: kurulu mpdf/mpdf sürümü tam olarak v8.3.1 olmalı.'
        );
    }

    // --- QR-PDF-PORT-PAYLOAD-01 -----------------------------------------------

    /**
     * QR-PDF-PORT-PAYLOAD-01 proves the controller's payload contract
     * honestly, mirroring the SVG cross-decode proof used for export.svg:
     *   1. A recording double bound to QrCodeImageExportPort in the
     *      container — implementing renderPng() by delegating to the real
     *      adapter while capturing its exact $data argument and returning
     *      value — is used to observe what the export.pdf controller passes
     *      into the PNG rendering step.
     *   2. The real export.pdf route is requested and the captured
     *      renderPng() input is asserted to be exactly url("/q/{token}") for
     *      the real stored token — the same resolver payload already proven
     *      through PNG/SVG export, not a different or fabricated value.
     *   3. The exact PNG bytes renderPng() returned, together with the real
     *      stored token, must be exactly what is passed into the future
     *      QrCodePdfExportPort::renderPdf() step. This test never asserts
     *      the token appears as plaintext inside the PDF body.
     *
     * As of this test file, QrCodePdfExportPort and the export.pdf route
     * both exist in the current production snapshot, so this test exercises
     * the real production contract end to end; it never asserts a
     * fabricated plaintext-in-PDF requirement.
     */
    public function test_the_controller_captures_the_real_png_bytes_and_stored_token_for_the_future_pdf_port(): void
    {
        self::assertTrue(
            interface_exists(QrCodePdfExportPort::class),
            'QR-PDF-PORT-PAYLOAD-01: App\\Application\\QrDestination\\Port\\QrCodePdfExportPort bu worktree\'de tanımlı olmalı.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-port-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $expectedResolverUrl = url("/q/{$token}");

        $realImageAdapter = $this->app->make(EndroidQrCodeImageExportAdapter::class);

        $imageRecorder = new class($realImageAdapter) implements QrCodeImageExportPort
        {
            public ?string $capturedPngData = null;

            public ?string $returnedPngBytes = null;

            public function __construct(private readonly QrCodeImageExportPort $realAdapter) {}

            public function renderPng(string $data, mixed $layout = null): QrRenderedImage
            {
                $this->capturedPngData = $data;
                $rendered = $layout === null ? $this->realAdapter->renderPng($data) : $this->realAdapter->renderPng($data, $layout);
                $this->returnedPngBytes = $rendered->bytes;

                return $rendered;
            }

            public function renderSvg(string $data, mixed $layout = null): QrRenderedImage
            {
                return $layout === null ? $this->realAdapter->renderSvg($data) : $this->realAdapter->renderSvg($data, $layout);
            }
        };

        $this->app->instance(QrCodeImageExportPort::class, $imageRecorder);

        $pdfRecorder = new class implements QrCodePdfExportPort
        {
            public ?string $capturedPngBytes = null;

            public ?string $capturedToken = null;

            public function renderPdf(string $pngBytes, string $token, ?string $paperSize = null, ?string $orientation = null, mixed $layout = null): QrRenderedImage
            {
                $this->capturedPngBytes = $pngBytes;
                $this->capturedToken = $token;

                return new QrRenderedImage("%PDF-1.7\n%%EOF", 'application/pdf');
            }
        };

        $this->app->instance(QrCodePdfExportPort::class, $pdfRecorder);

        $response = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $response->assertStatus(200);

        self::assertSame(
            $expectedResolverUrl,
            $imageRecorder->capturedPngData,
            'QR-PDF-PORT-PAYLOAD-01: export.pdf denetleyicisi, PNG/SVG yolu ile aynı gerçek resolver URL\'sini renderPng() içine geçirmeli.'
        );

        self::assertNotNull($imageRecorder->returnedPngBytes);
        self::assertSame(
            $imageRecorder->returnedPngBytes,
            $pdfRecorder->capturedPngBytes,
            'QR-PDF-PORT-PAYLOAD-01: renderPng() tarafından döndürülen tam PNG byte\'ları, renderPdf() içine değişmeden geçirilmeli.'
        );

        self::assertSame(
            $token,
            $pdfRecorder->capturedToken,
            'QR-PDF-PORT-PAYLOAD-01: gerçek stored token, renderPdf() içine değişmeden geçirilmeli.'
        );
    }

    // --- QR-PDF-AUTHZ-404-01 --------------------------------------------------

    public function test_nonmember_foreign_unknown_and_disabled_qr_codes_all_return_a_uniform_404(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-404-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $nonMember = $this->verifiedUser();
        $nonMemberResponse = $this->actingAs($nonMember)->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $nonMemberResponse->assertStatus(404, 'QR-PDF-AUTHZ-404-01: workspace üyesi olmayan kullanıcı 404 almalı.');

        $otherOwner = $this->verifiedUser();
        [$otherWorkspaceId] = $this->workspaceWithCurrentPublication($otherOwner, 'qr-pdf-404-2');
        $foreignResponse = $this->actingAs($otherOwner)->get($this->pdfExportUrl($otherWorkspaceId, $qrCodeId));
        $foreignResponse->assertStatus(404, 'QR-PDF-AUTHZ-404-01: başka bir workspace\'e ait QR id\'si için 404 dönmeli (leak yok).');

        $unknownResponse = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, 999999999));
        $unknownResponse->assertStatus(404, 'QR-PDF-AUTHZ-404-01: bilinmeyen QR id için 404 dönmeli.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())->putJson(
            "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/disable"
        )->assertStatus(200);

        $disabledResponse = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $disabledResponse->assertStatus(404, 'QR-PDF-AUTHZ-404-01: disable edilmiş QR export için de 404 dönmeli.');
    }

    // --- QR-PDF-AUTHZ-403-01 --------------------------------------------------

    public function test_a_member_with_qr_view_but_without_qr_design_manage_is_denied_with_403(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-403-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $member = $this->verifiedUser();
        $this->addMember($workspaceId, $member);

        $response = $this->actingAs($member)->get($this->pdfExportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(403, 'QR-PDF-AUTHZ-403-01: Member qr.view taşır fakat qr.design.manage taşımaz, export 403 dönmeli.');
    }

    // --- QR-PDF-AUTHN-01 --------------------------------------------------------

    public function test_guest_is_401_and_unverified_user_is_blocked(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-authn-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        Auth::forgetGuards();
        $this->app['session']->flush();

        $guestResponse = $this->get($this->pdfExportUrl($workspaceId, $qrCodeId));
        $guestResponse->assertStatus(401, 'QR-PDF-AUTHN-01: guest 401 almalı.');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $unverifiedResponse = $this->actingAs($unverified)->get($this->pdfExportUrl($workspaceId, $qrCodeId));

        self::assertNotSame(200, $unverifiedResponse->getStatusCode(), 'QR-PDF-AUTHN-01: doğrulanmamış kullanıcı gerçek PDF export alamamalı.');
    }

    // --- QR-PDF-SIZE-DEFAULT-01 -------------------------------------------------

    public function test_no_query_export_stays_exactly_a4_portrait(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-size-default-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->pdfExportUrl($workspaceId, $qrCodeId));

        $response->assertStatus(200, 'QR-PDF-SIZE-DEFAULT-01: query yokken 200 application/pdf almalı.');

        $this->assertMediaBoxMatchesIsoSize((string) $response->getContent(), 'A4', 'portrait');
    }

    /**
     * 500 döndüğünde ASIL sebebi assertion mesajına taşır.
     *
     * Bu test CI'da iki kez 500 ile düştü ve aynı commit yeniden
     * çalıştırılınca geçti; yerelde hiç üretilemedi. Laravel test istemcisi
     * istisnayı yutup yalnız durum kodunu gösterdiği için elimizde "500"
     * dışında hiçbir şey yoktu ve bellek varsayımı bu körlük yüzünden
     * kanıtlanmadan denendi (ve çürüdü).
     *
     * Tekrar düşerse artık sebebini kendisi söyleyecek.
     */
    private function assertPdfResponseOk(TestResponse $response, string $context): void
    {
        if ($response->getStatusCode() !== 200) {
            $body = (string) $response->getContent();
            $decoded = json_decode($body, true);
            $detail = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['exception'] ?? '')
                : mb_substr(strip_tags($body), 0, 400);

            self::fail(sprintf(
                '%s — beklenen 200, alınan %d. Sunucu tarafı sebep: %s',
                $context,
                $response->getStatusCode(),
                $detail === '' ? '(gövde boş; APP_DEBUG kapalı olabilir)' : $detail,
            ));
        }

        $response->assertStatus(200, $context);
    }

    // --- QR-PDF-SIZE-MATRIX-01 ---------------------------------------------------

    #[DataProvider('paperSizeOrientationCombinations')]
    public function test_every_authorized_paper_size_and_orientation_combination_returns_one_page_matching_iso_216_mediabox(string $paperSize, string $orientation): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-matrix-'.strtolower($paperSize.'-'.$orientation));
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->pdfExportUrlWithQuery($workspaceId, $qrCodeId, [
            'paperSize' => $paperSize,
            'orientation' => $orientation,
        ]));

        $this->assertPdfResponseOk($response, "QR-PDF-SIZE-MATRIX-01: {$paperSize}/{$orientation} için Owner 200 application/pdf almalı.");
        $response->assertHeader('Content-Type', 'application/pdf');

        $body = (string) $response->getContent();
        self::assertStringStartsWith('%PDF-', $body, 'QR-PDF-SIZE-MATRIX-01: gövde gerçek bir PDF magic header ile başlamalı.');
        self::assertStringContainsString('%%EOF', $body, 'QR-PDF-SIZE-MATRIX-01: gövde bir %%EOF sonlandırıcısı içermeli.');

        $this->assertMediaBoxMatchesIsoSize($body, $paperSize, $orientation);
    }

    // --- QR-PDF-SIZE-IDEMPOTENT-01 ------------------------------------------------

    public function test_repeated_exports_of_a_nondefault_paper_size_and_orientation_return_identical_pdf_bytes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-size-idem-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $url = $this->pdfExportUrlWithQuery($workspaceId, $qrCodeId, [
            'paperSize' => 'B7',
            'orientation' => 'landscape',
        ]);

        $first = $this->actingAs($owner)->get($url);
        $second = $this->actingAs($owner)->get($url);

        $first->assertStatus(200);
        $second->assertStatus(200);
        self::assertSame(
            $first->getContent(),
            $second->getContent(),
            'QR-PDF-SIZE-IDEMPOTENT-01: aynı paperSize/orientation için tekrarlanan export byte-eşit olmalı.'
        );
    }

    // --- QR-PDF-SIZE-INVALID-01 ---------------------------------------------------

    public static function invalidPaperSizeOrOrientation(): array
    {
        return [
            'invalid paperSize' => ['paperSize' => 'C4', 'orientation' => 'portrait'],
            'invalid orientation' => ['paperSize' => 'A4', 'orientation' => 'diagonal'],
            'invalid both' => ['paperSize' => 'Letter', 'orientation' => 'sideways'],
            'lowercase paperSize is rejected (case-sensitive contract)' => ['paperSize' => 'a4', 'orientation' => 'portrait'],
        ];
    }

    #[DataProvider('invalidPaperSizeOrOrientation')]
    public function test_invalid_paper_size_or_orientation_returns_422_and_no_pdf_for_an_otherwise_authorized_qr(string $paperSize, string $orientation): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-size-invalid-'.strtolower(preg_replace('/[^a-z0-9]/i', '', $paperSize.$orientation)));
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $response = $this->actingAs($owner)->get($this->pdfExportUrlWithQuery($workspaceId, $qrCodeId, [
            'paperSize' => $paperSize,
            'orientation' => $orientation,
        ]));

        $response->assertStatus(422, "QR-PDF-SIZE-INVALID-01: geçersiz paperSize={$paperSize}/orientation={$orientation} için 422 dönmeli.");

        $body = (string) $response->getContent();
        self::assertStringNotContainsString('%PDF-', $body, 'QR-PDF-SIZE-INVALID-01: 422 gövdesi bir PDF magic header taşımamalı.');
    }

    // --- QR-PDF-SIZE-PORT-PAYLOAD-01 -----------------------------------------------

    /**
     * Mirrors QR-PDF-PORT-PAYLOAD-01, but proves the selected paperSize and
     * orientation are forwarded, unchanged, into the future extended
     * QrCodePdfExportPort::renderPdf() step. The recorder below declares its
     * renderPdf() with two additional optional parameters so the current
     * two-argument interface does not fatal at discovery time; once
     * production extends the interface and the controller to pass the
     * selected values, this test asserts they were forwarded exactly.
     */
    public function test_the_controller_forwards_the_exact_selected_paper_size_and_orientation_into_the_future_pdf_port(): void
    {
        self::assertTrue(
            interface_exists(QrCodePdfExportPort::class),
            'QR-PDF-SIZE-PORT-PAYLOAD-01: App\\Application\\QrDestination\\Port\\QrCodePdfExportPort bu worktree\'de tanımlı olmalı.'
        );

        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-size-port-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $pdfRecorder = new class implements QrCodePdfExportPort
        {
            public ?string $capturedPngBytes = null;

            public ?string $capturedToken = null;

            public ?string $capturedPaperSize = null;

            public ?string $capturedOrientation = null;

            public function renderPdf(string $pngBytes, string $token, ?string $paperSize = null, ?string $orientation = null, mixed $layout = null): QrRenderedImage
            {
                $this->capturedPngBytes = $pngBytes;
                $this->capturedToken = $token;
                $this->capturedPaperSize = $paperSize;
                $this->capturedOrientation = $orientation;

                return new QrRenderedImage("%PDF-1.7\n%%EOF", 'application/pdf');
            }
        };

        $this->app->instance(QrCodePdfExportPort::class, $pdfRecorder);

        $response = $this->actingAs($owner)->get($this->pdfExportUrlWithQuery($workspaceId, $qrCodeId, [
            'paperSize' => 'B7',
            'orientation' => 'landscape',
        ]));

        $response->assertStatus(200);

        self::assertNotNull($pdfRecorder->capturedPngBytes, 'QR-PDF-SIZE-PORT-PAYLOAD-01: gerçek PNG byte\'ları renderPdf() içine geçirilmeli.');
        self::assertSame($token, $pdfRecorder->capturedToken, 'QR-PDF-SIZE-PORT-PAYLOAD-01: gerçek stored token renderPdf() içine geçirilmeli.');
        self::assertSame(
            'B7',
            $pdfRecorder->capturedPaperSize,
            'QR-PDF-SIZE-PORT-PAYLOAD-01: seçilen paperSize=B7 renderPdf() içine değişmeden geçirilmeli.'
        );
        self::assertSame(
            'landscape',
            $pdfRecorder->capturedOrientation,
            'QR-PDF-SIZE-PORT-PAYLOAD-01: seçilen orientation=landscape renderPdf() içine değişmeden geçirilmeli.'
        );
    }

    // --- QR-PDF-THEME-01 (S1-WP04b6 RED, six stateless basic QR themes) -----

    private function themedPdfExportUrl(int $workspaceId, int $qrCodeId, ?string $theme): string
    {
        $url = "/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}/export.pdf";

        return $theme === null ? $url : $url.'?theme='.$theme;
    }

    /**
     * S1-WP04b6 RED — the themed PDF must stay deterministic and exactly
     * one page, and produce different bytes across themes. (Exact themed
     * PNG forwarding into the PDF port is proven separately, by recorder
     * identity, in
     * test_the_controller_forwards_the_exact_themed_png_bytes_into_the_pdf_port
     * below — mPDF may re-encode/compress the embedded image stream, so a
     * raw base64-substring search against the final PDF bytes is not a
     * valid way to prove exact PNG forwarding and is intentionally not
     * asserted here.) Today export.pdf accepts no ?theme query at all, so
     * this fails RED (the theme is silently ignored — PDF bytes identical
     * across themes) rather than the real contract.
     */
    public function test_pdf_stays_one_page_deterministic_and_changes_bytes_across_themes(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-theme-1');
        [$qrCodeId] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $firstPdf = $this->actingAs($owner)->get($this->themedPdfExportUrl($workspaceId, $qrCodeId, 'bold'));
        $secondPdf = $this->actingAs($owner)->get($this->themedPdfExportUrl($workspaceId, $qrCodeId, 'bold'));
        $classicPdf = $this->actingAs($owner)->get($this->themedPdfExportUrl($workspaceId, $qrCodeId, 'classic'));

        $firstPdf->assertStatus(200, 'QR-PDF-THEME-01: ?theme=bold için 200 application/pdf almalı.');
        $secondPdf->assertStatus(200);
        $classicPdf->assertStatus(200);

        $firstBody = (string) $firstPdf->getContent();
        self::assertStringStartsWith('%PDF-', $firstBody, 'QR-PDF-THEME-01: gövde gerçek bir PDF magic header ile başlamalı.');
        self::assertStringContainsString('%%EOF', $firstBody, 'QR-PDF-THEME-01: gövde bir %%EOF sonlandırıcısı içermeli.');
        self::assertSame(
            1,
            preg_match_all('/\/Type\s*\/Page(?!s)/', $firstBody),
            'QR-PDF-THEME-01: temalı PDF tam olarak tek sayfa içermeli.'
        );

        self::assertSame(
            $firstPdf->getContent(),
            $secondPdf->getContent(),
            'QR-PDF-THEME-01: aynı tema için tekrarlanan PDF export byte-eşit (deterministik) olmalı.'
        );

        self::assertNotSame(
            $classicPdf->getContent(),
            $firstPdf->getContent(),
            'QR-PDF-THEME-01: bold teması classic\'ten farklı PDF byte\'ları üretmeli.'
        );
    }

    /**
     * S1-WP04b6 RED — mirrors QR-PDF-PORT-PAYLOAD-01: the exact selected
     * theme's PNG bytes (not the classic default) must be the ones forwarded
     * into QrCodePdfExportPort::renderPdf(), unchanged, and the exact same
     * QrLayout object instance built for the image step must be the one
     * forwarded into the PDF step — proven by recorder identity, not by
     * searching for base64 PNG data inside the final (possibly
     * re-encoded/compressed) mPDF bytes.
     */
    public function test_the_controller_forwards_the_exact_themed_png_bytes_and_shared_layout_object_into_the_pdf_port(): void
    {
        $owner = $this->verifiedUser();
        [$workspaceId, $locationId, $menuId] = $this->workspaceWithCurrentPublication($owner, 'qr-pdf-theme-port-1');
        [$qrCodeId, $token] = $this->createActiveQrCode($owner, $workspaceId, $locationId, $menuId);

        $realImageAdapter = $this->app->make(EndroidQrCodeImageExportAdapter::class);

        $imageRecorder = new class($realImageAdapter) implements QrCodeImageExportPort
        {
            public ?string $returnedPngBytes = null;

            public mixed $capturedImageLayout = null;

            public function __construct(private readonly QrCodeImageExportPort $realAdapter) {}

            public function renderPng(string $data, mixed $layout = null): QrRenderedImage
            {
                $this->capturedImageLayout = $layout;
                $rendered = $layout === null ? $this->realAdapter->renderPng($data) : $this->realAdapter->renderPng($data, $layout);
                $this->returnedPngBytes = $rendered->bytes;

                return $rendered;
            }

            public function renderSvg(string $data, mixed $layout = null): QrRenderedImage
            {
                return $layout === null ? $this->realAdapter->renderSvg($data) : $this->realAdapter->renderSvg($data, $layout);
            }
        };

        $this->app->instance(QrCodeImageExportPort::class, $imageRecorder);

        $pdfRecorder = new class implements QrCodePdfExportPort
        {
            public ?string $capturedPngBytes = null;

            public mixed $capturedPdfLayout = null;

            public function renderPdf(string $pngBytes, string $token, ?string $paperSize = null, ?string $orientation = null, mixed $layout = null): QrRenderedImage
            {
                $this->capturedPngBytes = $pngBytes;
                $this->capturedPdfLayout = $layout;

                return new QrRenderedImage("%PDF-1.7\n%%EOF", 'application/pdf');
            }
        };

        $this->app->instance(QrCodePdfExportPort::class, $pdfRecorder);

        $response = $this->actingAs($owner)->get($this->themedPdfExportUrl($workspaceId, $qrCodeId, 'highContrast'));
        $response->assertStatus(200);

        self::assertNotNull($imageRecorder->returnedPngBytes, 'QR-PDF-THEME-01: highContrast temalı PNG byte\'ları üretilmeli.');
        self::assertSame(
            $imageRecorder->returnedPngBytes,
            $pdfRecorder->capturedPngBytes,
            'QR-PDF-THEME-01: renderPng() tarafından döndürülen highContrast temalı PNG byte\'ları, renderPdf() içine değişmeden geçirilmeli.'
        );

        self::assertNotNull(
            $imageRecorder->capturedImageLayout,
            'QR-PDF-THEME-01: highContrast QrLayout nesnesi renderPng() içine geçirilmeli.'
        );
        self::assertNotNull(
            $pdfRecorder->capturedPdfLayout,
            'QR-PDF-THEME-01: highContrast QrLayout nesnesi renderPdf() içine geçirilmeli.'
        );
        self::assertSame(
            $imageRecorder->capturedImageLayout,
            $pdfRecorder->capturedPdfLayout,
            'QR-PDF-THEME-01: PNG ve PDF adımlarına geçirilen QrLayout tam olarak aynı nesne (identity) olmalı, kopya/yeniden inşa edilmiş değil.'
        );

        $encodedLayout = json_decode(json_encode($imageRecorder->capturedImageLayout), true);
        self::assertSame(
            'highContrast',
            $encodedLayout['theme'] ?? null,
            'QR-PDF-THEME-01: paylaşılan QrLayout JSON serileştirmesinde theme alanı tam olarak highContrast olmalı.'
        );
    }
}
