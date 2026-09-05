<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * P0-06 RED — üretim örneği YALAN SÖYLEMEZ (`docs/110` P0-06, kabul ölçütü 4).
 *
 * `.env.production.example` `MAIL_MAILER=log` öneriyordu. Bu dosyayla kurulan
 * bir üretimde doğrulama e-postası hiç ÇIKMAZ: kayıt olan kişi `/app`'in
 * `verified` kapısına takılır ve ürüne HİÇ GİREMEZ. Kusur ilk dakikada
 * vurur ve kuran kişinin yapabileceği hiçbir şey yoktur — çünkü örnek dosya
 * ona "böyle doğru" demiştir.
 *
 * `log` bir GELİŞTİRME değeridir: mesaj `storage/logs` içine yazılır ve
 * kimseye gitmez. Geliştirme örneğinde doğru, üretim örneğinde yalandır.
 *
 * Requirement IDs: PROD-MAIL-TRANSPORT-01, PROD-MAIL-TRANSPORT-DEV-02,
 * PROD-MAIL-TRANSPORT-NO-SECRET-03.
 */
final class ProductionMailTransportTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function envExample(string $file): array
    {
        $contents = (string) file_get_contents(base_path($file));

        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $values[trim($key)] = trim(trim($value), '"\'');
        }

        return $values;
    }

    // --- PROD-MAIL-TRANSPORT-01 -------------------------------------------

    public function test_the_production_example_does_not_propose_a_transport_that_sends_nothing(): void
    {
        $mailer = $this->envExample('.env.production.example')['MAIL_MAILER'] ?? null;

        self::assertNotNull($mailer, 'PROD-MAIL-TRANSPORT-01: MAIL_MAILER satırı olmalı.');

        // `log` ve `array` hiçbir yere göndermez. `array` yalnız testlerin
        // sürücüsüdür; ikisi de üretim varsayılanı olamaz.
        self::assertNotContains(
            $mailer,
            ['log', 'array'],
            'PROD-MAIL-TRANSPORT-01: üretim örneği hiçbir yere göndermeyen bir sürücü öneriyor; '
            .'bu dosyayla kurulan ürüne kimse GİREMEZ.'
        );

        // Önerilen sürücü gerçekten TANIMLI olmalı: `config/mail.php` içinde
        // karşılığı olmayan bir ad, kurulumu ilk gönderimde patlatırdı.
        self::assertArrayHasKey(
            $mailer,
            (array) config('mail.mailers'),
            "PROD-MAIL-TRANSPORT-01: `{$mailer}` config/mail.php içinde tanımlı değil."
        );
    }

    // --- PROD-MAIL-TRANSPORT-DEV-02 ---------------------------------------

    public function test_the_development_example_may_keep_the_log_transport(): void
    {
        /*
            AYRIM KAYDA GEÇER.

            `log` yanlış bir değer DEĞİL, yanlış YERDE bir değerdir.
            Geliştiricinin makinesinde gerçek e-posta göndermek istemeyiz;
            bu iddia, birinin "log her yerde kötü" diye `.env.example`'ı da
            değiştirmesini engeller.
        */
        self::assertSame(
            'log',
            $this->envExample('.env.example')['MAIL_MAILER'] ?? null,
            'PROD-MAIL-TRANSPORT-DEV-02: geliştirme örneği `log` kalmalı — yerelde gerçek e-posta göndermeyiz.'
        );
    }

    // --- PROD-MAIL-TRANSPORT-NO-SECRET-03 ---------------------------------

    public function test_the_production_example_still_carries_no_credential(): void
    {
        $env = $this->envExample('.env.production.example');

        foreach (['MAILGUN_DOMAIN', 'MAILGUN_SECRET'] as $secret) {
            self::assertSame(
                '',
                $env[$secret] ?? null,
                "PROD-MAIL-TRANSPORT-NO-SECRET-03: {$secret} boş olmalı; örnek dosyaya yazılan bir sır, sır değildir."
            );
        }
    }
}
