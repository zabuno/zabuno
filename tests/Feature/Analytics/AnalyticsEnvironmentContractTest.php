<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use PHPUnit\Framework\TestCase;

/**
 * Ölçüm dikişinin ortam sözleşmesi — anahtarlar BULUNABİLİR olmalı.
 *
 * `config/analytics.php` ölçümü tek bir dikiş yerine indirdi ve kimlik
 * verilmediğinde her şeyi kapalı tuttu. Bunun sessiz bedeli şuydu: dağıtımı
 * yapan kişi hangi anahtarı yazacağını hiçbir örnek dosyada göremiyordu.
 * Var olmayan bir anahtar aranmaz; aranmayan anahtar yazılmaz ve ölçüm
 * "kurulmuş" sanılırken kapalı kalır (`docs/126`).
 *
 * Bu test çalışan bir üretim sunucusunu değil, ekibin kopyaladığı ÖRNEĞİ
 * doğrular. Bir örneğin doğru olması üretimin doğru olduğu anlamına gelmez;
 * ama yanlış/eksik bir örnek sessizce üretime gider.
 *
 * Requirement ID: ANALYTICS-TENANT-SEAM (`docs/46`).
 */
final class AnalyticsEnvironmentContractTest extends TestCase
{
    private const REPO_ROOT = __DIR__.'/../../..';

    private const EXAMPLE_FILES = ['.env.example', '.env.staging.example', '.env.production.example'];

    /**
     * Dikişin ortam yüzeyinin TAMAMI. Bir hedefi burada bildirmemek, onu
     * dağıtım günü görünmez yapar.
     */
    private const REQUIRED_KEYS = [
        'ANALYTICS_GTM_CONTAINER_ID',
        'ANALYTICS_GA4_ENABLED',
        'ANALYTICS_YANDEX_METRICA_ENABLED',
        'ANALYTICS_HOTJAR_ENABLED',
    ];

    /** @return array<string, string> */
    private function envExample(string $file): array
    {
        $values = [];

        foreach (file(self::REPO_ROOT.'/'.$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value);
        }

        return $values;
    }

    public function test_every_environment_example_declares_the_whole_analytics_seam(): void
    {
        foreach (self::EXAMPLE_FILES as $file) {
            $env = $this->envExample($file);

            foreach (self::REQUIRED_KEYS as $key) {
                self::assertArrayHasKey(
                    $key,
                    $env,
                    "{$key} {$file} içinde bildirilmeli; `config/analytics.php` bu anahtarı okuyor ama örnek dosya onu göstermiyorsa dağıtan kişi anahtarın varlığını bilemez."
                );
            }
        }
    }

    public function test_no_environment_example_carries_a_real_container_identity(): void
    {
        foreach (self::EXAMPLE_FILES as $file) {
            $env = $this->envExample($file);

            self::assertSame(
                '',
                $env['ANALYTICS_GTM_CONTAINER_ID'] ?? null,
                "{$file} içindeki ANALYTICS_GTM_CONTAINER_ID boş kalmalı: örnek dosya herkese açıktır ve buraya yazılan bir konteyner kimliği, her kurulumun aynı konteynere veri basması demektir. Gerçek kimlik yalnız üretim ortamının kendi env dosyasına girer."
            );
        }
    }

    public function test_the_disabled_default_is_kept_in_the_local_example(): void
    {
        $env = $this->envExample('.env.example');

        foreach (['ANALYTICS_GA4_ENABLED', 'ANALYTICS_YANDEX_METRICA_ENABLED', 'ANALYTICS_HOTJAR_ENABLED'] as $key) {
            self::assertSame(
                'false',
                $env[$key] ?? null,
                "Yerel geliştirmede {$key} kapalı kalmalı: açık bir hedef CSP'yi gevşetir ve testler ölçüm sunucularına bağlanabilir hâle gelir."
            );
        }
    }

    public function test_the_delivery_note_exists_and_points_at_the_single_seam(): void
    {
        $path = self::REPO_ROOT.'/docs/126-OLCUM-HESAPLARI-TESLIM.md';

        self::assertFileExists($path, 'Teslim edilen ölçüm kimliklerinin nereye girdiği bir belgede yazılı olmalı.');

        $contents = (string) file_get_contents($path);

        self::assertStringContainsString(
            'config/analytics.php',
            $contents,
            'Belge kararı tekrar etmemeli, dikiş yerine ATIF yapmalı.'
        );
    }
}
