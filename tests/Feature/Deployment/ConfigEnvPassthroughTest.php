<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * DEPLOY-ENV-PASSTHROUGH-13 — `config/` bir env anahtarı okuyorsa compose
 * onu konteynere GEÇİRMEK zorunda.
 *
 * Üretimde `.env` konteynerin içinde yaşamaz; uygulama yalnız compose'un
 * `environment:` bloğundaki değerleri görür. Bu aynı arıza sınıfı üçüncü
 * kez yakalandı: önce URL politikası (06), sonra posta (08), sonra virüs
 * tarayıcı (12). 2026-09-08'de dördüncüsü bulundu: `ANALYTICS_*`,
 * `SLA_*`, `LEGAL_*` ve `SUPPORT_ACCESS_*` — yirmi bir anahtar — config'te
 * okunuyor ama compose'da yoktu. Ekibin sunucu yönergesi "`.env`'e yaz,
 * `up -d` yap" diyordu; yapılsaydı ölçüm açılmış görünür, hiçbir veri
 * gelmezdi. Sessiz, ve ölçüm geriye dönük toplanamadığı için kalıcı.
 *
 * Her seferinde tek tek anahtar listelemek yerine kapı KAYNAKTAN türer:
 * config dosyalarında geçen `env('X')` çağrıları taranır ve her `X`
 * compose'da `X: ${X` biçiminde aranır. Yeni bir config anahtarı eklemek,
 * compose satırını da eklemek demektir — kapı ikisini birlikte sayar.
 *
 * Kapsam bilinçli olarak SUNUCU `.env`'inden gelen anahtarlarla sınırlı.
 * Laravel'in kendi anahtarları (`APP_*`, `DB_*`, `CACHE_*` ...) ya compose
 * tarafından sabitlenir ya da varsayılanı zaten üretim için doğrudur; onlar
 * kendi kapılarında (03, 06, 08, 12) korunur.
 */
final class ConfigEnvPassthroughTest extends TestCase
{
    /**
     * Sunucunun `.env`'inden beslenen anahtar önekleri. Yeni bir ürün
     * alanı `.env`'den okunmaya başladığında öneki buraya eklenir; ilk
     * çalıştırmada eksik compose satırları adıyla listelenir.
     */
    private const SERVER_ENV_PREFIXES = [
        'ANALYTICS_',
        'LEGAL_',
        'SLA_',
        'SUPPORT_',
        'MAIL_FROM_',
        'MAILGUN_',
        'CONTACT_',
        'MEDIA_SCANNER_',
    ];

    /** @return list<string> config/ altında `env('...')` ile okunan, öneklerden birine uyan anahtarlar */
    private function serverEnvKeysReadByConfig(): array
    {
        $keys = [];

        foreach (glob(base_path('config/*.php')) ?: [] as $file) {
            preg_match_all('/\benv\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]/', (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                foreach (self::SERVER_ENV_PREFIXES as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        self::assertNotEmpty($keys, 'DEPLOY-ENV-PASSTHROUGH-13: config/ altında hiç sunucu env anahtarı bulunamadı; tarama bozuk.');

        return $keys;
    }

    public function test_every_server_env_key_read_by_config_reaches_the_container(): void
    {
        $compose = (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents(base_path('docker-compose.yml')));

        $missing = [];

        foreach ($this->serverEnvKeysReadByConfig() as $key) {
            // `${KEY` ile başlamalı: değer sunucunun `.env`'inden gelir,
            // compose'a gömülmez. Varsayılan (`:-...`) serbesttir.
            if (preg_match('/^\s*'.$key.':\s*\$\{'.$key.'(:-[^}]*)?\}\s*$/m', $compose) !== 1) {
                $missing[] = $key;
            }
        }

        self::assertSame(
            [],
            $missing,
            'DEPLOY-ENV-PASSTHROUGH-13: şu anahtarlar config/ tarafından okunuyor ama docker-compose.yml '
            ."konteynere geçirmiyor — sunucunun .env'ine yazılmaları sessizce etkisiz kalır:\n  "
            .implode("\n  ", $missing)
        );
    }

    /**
     * Compose'daki varsayılan, config'teki varsayılanı BOŞ DİZEYLE EZMEMELİ.
     *
     * `env('X', 'öntanımlı')` yalnız değişken HİÇ yokken öntanımlıya döner;
     * compose `X: ${X:-}` yazarsa değişken BOŞ DİZE olarak vardır ve
     * öntanımlı kaybolur. Barındırma sağlayıcısı böyle boşalırdı: hukuk
     * sayfası "netcup GmbH" yerine "henüz verilmedi" yazardı.
     *
     * Kural birebir eşitlik DEĞİL: compose bilinçli olarak farklı bir
     * üretim varsayılanı taşıyabilir (tarayıcı zaman aşımı 10 yerine 30,
     * gönderen adresi `example.com` yerine `example.invalid`). Yasak olan
     * tek şey, config'in dolu bir varsayılanını boş dizeye düşürmek.
     */
    public function test_compose_never_erases_a_config_default_with_an_empty_string(): void
    {
        $compose = (string) preg_replace('/^\s*#.*$/m', '', (string) file_get_contents(base_path('docker-compose.yml')));

        $configDefaults = [];

        foreach (glob(base_path('config/*.php')) ?: [] as $file) {
            preg_match_all(
                '/\benv\(\s*[\'"]([A-Z][A-Z0-9_]*)[\'"]\s*,\s*([\'"])((?:(?!\2).)+)\2\s*\)/',
                (string) file_get_contents($file),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                foreach (self::SERVER_ENV_PREFIXES as $prefix) {
                    if (str_starts_with($match[1], $prefix)) {
                        $configDefaults[$match[1]] = $match[3];
                    }
                }
            }
        }

        foreach ($configDefaults as $key => $default) {
            if ($default === '') {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/^\s*'.$key.':\s*\$\{'.$key.'(:-)?\}\s*$/m',
                $compose,
                "DEPLOY-ENV-PASSTHROUGH-13: `{$key}` config'te `{$default}` varsayılanına sahip; compose onu boş dizeyle eziyor, dolu bir varsayılan taşımalı."
            );
        }
    }
}
