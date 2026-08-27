<?php

declare(strict_types=1);

namespace Tests\Feature\QrDestination;

use App\Domain\QrDestination\QrLayout;
use App\Domain\QrDestination\QrTheme;
use App\Infrastructure\QrDestination\Rendering\EndroidQrCodeImageExportAdapter;
use App\Models\User;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Zxing\QrReader;

/**
 * QR export answered an occasional HTTP 500 that looked like machine load and
 * was not.
 *
 * Before it hands an image over, the exporter reads its own QR back with the
 * real decoder. That read-back ran without the try-harder hint, and in that
 * mode Zxing's finder-pattern search only samples every 6th row of the image
 * (`$iSkip = (3 * height) / (4 * 57)`, see FinderPatternFinder::find()). For
 * some payloads the finder patterns fall between two sampled rows, so a
 * perfectly valid QR is reported unreadable. Which payloads land badly is
 * decided by the payload itself, so a given token fails 10 times out of 10 —
 * but tokens are random per QR code, which is why it looked intermittent
 * across suite runs. When all four candidate profiles missed, the exporter
 * threw and the controllers turned that into a 500.
 *
 * Measured on 2026-08-26 against the pre-fix exporter, over 16 000 random
 * tokens: 7.3% were rejected on the first profile and 8 were rejected on all
 * four at once (1 in 2000). A pre-fix full suite run rendered 45 distinct
 * random payloads, so roughly one run in 45 broke, on whichever test drew a
 * bad token.
 *
 * The five tokens below come from a separate harvest of 14 300 tokens at the
 * origin named in ORIGIN, and they are frozen here because they are the
 * evidence: each fails every time against the pre-fix exporter and passes
 * every time against the fixed one. None of it needs a busy machine to fail.
 *
 * Requirement IDs: QR-EXPORT-DECODEBACK-01, QR-EXPORT-DECODEBACK-02.
 */
final class QrExportDecodeBackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The exports encode url("/q/{token}"), so a frozen token only reproduces
     * the original failure together with the base URL it was harvested under.
     * Every request below is therefore made against this exact origin instead
     * of whatever APP_URL happens to be, and the decoded payload is asserted,
     * so the test cannot quietly stop reproducing anything.
     */
    private const string ORIGIN = 'http://localhost';

    /** @return list<array{0: string}> */
    public static function tokensThePlainDecoderCannotRead(): array
    {
        return [
            ['rS4uVNEkitoDSJ-oM-Mcs8RfSlQJdAJueFaJMYQmHVE'],
            ['KoNlMSMF0y_Vfi8BEVOORQbDA0-rxhJ_RdYjfJVq1HE'],
            ['brc_J5_NYsuZyR46TrNB-IYbeqDEMscNpB9IL0R8cPs'],
            ['fRoxHkhZmyB_BVR8BEr7s7hwwtRcGkceL70s8cTHjy8'],
            ['zyrJGvU4YPYjfx7oD-LxT1BgHaQYCcwp3SrKx1rDNwQ'],
        ];
    }

    // --- QR-EXPORT-DECODEBACK-01 --------------------------------------------

    /**
     * The exporter must render these payloads instead of exhausting its
     * profiles, and the bytes it returns must still decode back to the exact
     * payload — the guarantee is unchanged, only the reader's row sampling is.
     */
    #[DataProvider('tokensThePlainDecoderCannotRead')]
    public function test_the_exporter_renders_a_payload_the_plain_decoder_misses_and_it_still_decodes_back(string $token): void
    {
        $payload = self::ORIGIN.'/q/'.$token;
        $adapter = $this->app->make(EndroidQrCodeImageExportAdapter::class);

        foreach ([QrTheme::Classic, QrTheme::from('rounded')] as $theme) {
            $layout = new QrLayout($theme);

            $png = $adapter->renderPng($payload, $layout);
            self::assertSame('image/png', $png->mimeType);
            self::assertNotEmpty($png->bytes, "QR-EXPORT-DECODEBACK-01: {$theme->value} PNG gövdesi boş olmamalı.");

            self::assertSame(
                $payload,
                (new QrReader($png->bytes, QrReader::SOURCE_TYPE_BLOB))->text(['TRY_HARDER' => true]),
                "QR-EXPORT-DECODEBACK-01: {$theme->value} PNG'si tam olarak payload'ı taşımalı."
            );

            $svg = $adapter->renderSvg($payload, $layout);
            self::assertSame('image/svg+xml', $svg->mimeType);

            $document = new DOMDocument;
            self::assertTrue(@$document->loadXML($svg->bytes), "QR-EXPORT-DECODEBACK-01: {$theme->value} SVG'si geçerli XML olmalı.");
            self::assertSame('svg', $document->documentElement?->localName);
        }
    }

    /**
     * The same payload must always pick the same profile, so a retry can never
     * change the exported bytes.
     */
    #[DataProvider('tokensThePlainDecoderCannotRead')]
    public function test_the_rendered_bytes_stay_identical_across_repeated_renders(string $token): void
    {
        $payload = self::ORIGIN.'/q/'.$token;
        $adapter = $this->app->make(EndroidQrCodeImageExportAdapter::class);
        $layout = new QrLayout(QrTheme::Classic);

        self::assertSame(
            $adapter->renderPng($payload, $layout)->bytes,
            $adapter->renderPng($payload, $layout)->bytes,
            'QR-EXPORT-DECODEBACK-01: aynı payload için tekrarlanan render byte-eşit olmalı.'
        );
    }

    // --- QR-EXPORT-DECODEBACK-02 --------------------------------------------

    /**
     * The same thing end to end: a stored QR code whose token is one of the
     * frozen rejects must export as PNG, SVG and PDF, not as three 500s. On
     * failure the response body is included: a bare status code says nothing
     * about which of the two very different 500s this was.
     */
    public function test_every_export_endpoint_succeeds_for_a_token_the_plain_decoder_cannot_read(): void
    {
        $token = self::tokensThePlainDecoderCannotRead()[0][0];

        $owner = User::factory()->create(['email_verified_at' => now()]);
        [$workspaceId, $qrCodeId] = $this->qrCodeWithToken($owner, $token);

        $base = self::ORIGIN."/api/workspaces/{$workspaceId}/qr-codes/{$qrCodeId}";

        $png = $this->actingAs($owner)->get($base.'/export.png');
        $this->assertExported($png, 'export.png');

        self::assertSame(
            self::ORIGIN.'/q/'.$token,
            (new QrReader((string) $png->getContent(), QrReader::SOURCE_TYPE_BLOB))->text(['TRY_HARDER' => true]),
            'QR-EXPORT-DECODEBACK-02: dışa aktarılan PNG dondurulmuş payload’ı taşımalı — taşımıyorsa bu test artık o hatayı üretmiyor demektir.'
        );

        $this->assertExported($this->actingAs($owner)->get($base.'/export.svg?theme=rounded'), 'export.svg?theme=rounded');
        $this->assertExported($this->actingAs($owner)->get($base.'/export.pdf?paperSize=A4&orientation=portrait'), 'export.pdf');
    }

    private function assertExported(TestResponse $response, string $label): void
    {
        self::assertSame(
            200,
            $response->getStatusCode(),
            "QR-EXPORT-DECODEBACK-02: {$label} 200 dönmeli. Gövde: ".substr((string) $response->getContent(), 0, 500)
        );
        self::assertNotEmpty($response->getContent(), "QR-EXPORT-DECODEBACK-02: {$label} gövdesi boş olmamalı.");
    }

    /**
     * @return array{0: int, 1: int} [workspaceId, qrCodeId]
     */
    private function qrCodeWithToken(User $owner, string $token): array
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran', 'slug' => 'qr-decode-back', 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $workspaceId, 'name' => 'Marka', 'slug' => 'qr-decode-back-brand',
            'locale' => 'tr', 'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $workspaceId, 'brand_id' => $brandId, 'display_name' => 'Şube',
            'country_code' => 'TR', 'city' => 'İstanbul', 'address_line1' => 'Adres',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)),
            'workspace_id' => $workspaceId, 'location_id' => $locationId, 'name' => 'Ana Menü',
            'state' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $publicationId = (int) DB::table('menu_publications')->insertGetId([
            'workspace_id' => $workspaceId, 'menu_id' => $menuId, 'location_id' => $locationId,
            'version' => 1, 'state' => 'published', 'snapshot' => json_encode(['categories' => []]),
            'published_by' => $owner->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('menu_publication_current_pointers')->insert([
            'menu_id' => $menuId, 'current_publication_id' => $publicationId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

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

        return [$workspaceId, $qrCodeId];
    }
}
