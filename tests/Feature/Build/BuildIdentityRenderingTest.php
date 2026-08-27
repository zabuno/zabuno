<?php

declare(strict_types=1);

namespace Tests\Feature\Build;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kimliğin GERÇEKTEN sayfaya bastığını kanıtlar.
 *
 * Birim testleri `BuildIdentity`'nin doğru değeri ürettiğini gösterir ama
 * hiçbir şey ekrana çıkmasa da geçerler. Preview Truth'un tamamı bu etiketin
 * HTML'de bulunmasına dayanır: karşılaştırmanın sunucu yarısı budur ve
 * `scripts/preview-truth` kapısı da tam olarak bunu okur.
 */
final class BuildIdentityRenderingTest extends TestCase
{
    /**
     * Giriş ekranı bilerek seçildi: kimlik doğrulaması gerektirmeyen tek
     * uygulama yüzeyi burasıdır, yani kapı oturum açmadan da ölçebilir.
     */
    #[Test]
    public function the_login_page_carries_the_build_identity(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="zabuno-build-revision"', escape: false);
        $response->assertSee('name="zabuno-build-stale"', escape: false);
        $response->assertSee('name="zabuno-build-banner"', escape: false);
    }

    /**
     * Şerit üretimde varsayılan olarak KAPALIDIR.
     *
     * Bayat derleme bir geliştirme döngüsü kusurudur; restoran sahibine
     * gösterilecek bir şey değildir. Sessizleştirilen yalnız EKRANDIR —
     * sürüm etiketi yerinde kalır ve ölçüm olayı yine gider, yani üretimdeki
     * bir ayrışma hâlâ tespit edilebilir.
     */
    #[Test]
    public function the_banner_is_off_by_default_in_production(): void
    {
        // Yapılandırma dosyasının KENDİ varsayılanı sınanıyor: üretimde
        // ortam değişkeni verilmediğinde şerit kapalı olmalı.
        $default = require config_path('build.php');
        $this->assertIsBool($default['banner']);

        config(['build.banner' => false]);

        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="zabuno-build-banner" content="false"', escape: false);
    }
}
