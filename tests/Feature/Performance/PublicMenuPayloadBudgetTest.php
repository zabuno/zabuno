<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Tests\TestCase;

/**
 * `docs/18` §Performance — yayınlanan menünün ağırlık bütçesi.
 *
 * Menüyü açan kişi restoranın masasında, çoğu zaman zayıf bir hücresel
 * bağlantıda ve sabırsızdır. Bu yüzden bütçe bir tercih değil, ürünün
 * çalışma şartıdır: sayfa ağırlaştıkça menü açılmaz, menü açılmayınca QR'ın
 * hiçbir anlamı kalmaz.
 *
 * Bu test ölçebildiğini ölçer: sunucudan inen belge ağırlığını. p95 gecikme
 * ve LCP gerçek bir dağıtım ölçümüdür ve burada iddia EDİLMEZ (`docs/18`
 * "load test sonrası revize").
 *
 * Requirement ID'leri: PERF-MENU-PAYLOAD-01, PERF-MENU-SCALES-02.
 */
final class PublicMenuPayloadBudgetTest extends TestCase
{
    /** `docs/18` §Performance: ilk menü yükü < 100KB. */
    private const MAX_PAYLOAD_BYTES = 100 * 1024;

    /** @return array<string, mixed> */
    private function snapshot(int $categories, int $itemsPerCategory): array
    {
        $built = [];

        for ($c = 0; $c < $categories; $c++) {
            $items = [];

            for ($i = 0; $i < $itemsPerCategory; $i++) {
                $items[] = [
                    'productName' => "Zeytinyağlı Enginar Dolması {$c}-{$i}",
                    'priceMinorAmount' => 12500 + $i,
                    'currencyCode' => 'TRY',
                    'allergens' => ['süt', 'gluten', 'yumurta'],
                ];
            }

            $built[] = ['name' => "Kategori {$c}", 'menuItems' => $items];
        }

        return ['categories' => $built];
    }

    /**
     * Misafirin GERÇEKTEN indirdiği ağırlık: sıkıştırılmış belge.
     * Ham bayt sayısı, hiçbir sunucunun göndermediği bir sayıdır.
     */
    private function payloadBytes(int $categories, int $itemsPerCategory): int
    {
        $html = view('public-menu', ['snapshot' => $this->snapshot($categories, $itemsPerCategory)])->render();

        return strlen((string) gzencode($html, 6));
    }

    private function rawBytes(int $categories, int $itemsPerCategory): int
    {
        return strlen(view('public-menu', ['snapshot' => $this->snapshot($categories, $itemsPerCategory)])->render());
    }

    // --- PERF-MENU-PAYLOAD-01 ---------------------------------------------

    public function test_a_realistic_restaurant_menu_fits_in_the_budget(): void
    {
        // 12 kategori × 20 ürün: gerçek bir restoran menüsünün üst sınırına
        // yakın, uydurma biçimde küçük değil.
        $bytes = $this->payloadBytes(12, 20);

        self::assertLessThan(
            self::MAX_PAYLOAD_BYTES,
            $bytes,
            sprintf(
                'PERF-MENU-PAYLOAD-01: yayınlanan menü %d bayt (gzip) — bütçe %d bayt (`docs/18` §Performance). '
                .'Bütçeyi yükseltmek, misafirin bekleme süresini uzatmaktır ve owner kararıdır.',
                $bytes,
                self::MAX_PAYLOAD_BYTES,
            )
        );
    }

    public function test_the_budget_depends_on_compression_and_says_so(): void
    {
        // Sıkıştırma açık değilse aynı menü onlarca kat ağırdır. Bu, ölçümün
        // saklanacak değil, YAZILACAK bir şartıdır: dağıtımda gzip/brotli
        // kapalıysa bütçe tutmaz.
        $raw = $this->rawBytes(12, 20);
        $compressed = $this->payloadBytes(12, 20);

        self::assertGreaterThan($compressed, $raw);

        // Sıkıştırmasız ağırlık da başıboş bırakılmaz: ürün başına satır içi
        // stil eklemek gibi bir değişiklik burada yakalanır.
        self::assertLessThan(
            512 * 1024,
            $raw,
            'PERF-MENU-PAYLOAD-01: sıkıştırmasız işaretleme şişiyor — sıkıştırmanın kapalı olduğu bir host\'ta menü açılmaz.'
        );
    }

    // --- PERF-MENU-SCALES-02 ----------------------------------------------

    public function test_the_page_weight_grows_with_the_menu_and_not_with_the_framework(): void
    {
        $small = $this->payloadBytes(1, 1);
        $large = $this->payloadBytes(12, 20);

        // Sabit yük (stil + script) makul olmalı: boş bir menüde bile
        // taşınan ağırlık, bütçenin yarısını yiyorsa içerik için yer kalmaz.
        self::assertLessThan(
            self::MAX_PAYLOAD_BYTES / 2,
            $small,
            'PERF-MENU-SCALES-02: içeriksiz sayfa bile bütçenin yarısını tüketiyor.'
        );

        self::assertGreaterThan(
            $small,
            $large,
            'PERF-MENU-SCALES-02: ölçüm gerçek içerikle değişmeli; sabit bir sayı ölçüm değildir.'
        );
    }
}
