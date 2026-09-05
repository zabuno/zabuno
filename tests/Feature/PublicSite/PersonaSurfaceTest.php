<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PERSONA-SURFACE-01 (`docs/102` §5h, sahibin kararı 2026-09-04).
 *
 * Superadmin (platform + mühendislik) kabuğu lacivert zeminde çalışır;
 * restoran paneli ve kamu sayfaları KROMASIZ kalır. Ayrım oryantasyon
 * içindir: iki panel aynı tarayıcıda açıkken hangi tarafta olunduğu
 * başlık okunmadan görülür.
 *
 * Öznitelik SUNUCUDAN gelir; React yüklenmeden önceki ilk boyamada da
 * doğru olsun diye. Bu test onu belge düzeyinde dondurur — kiracı kabuğuna
 * sızmasını da.
 */
final class PersonaSurfaceTest extends TestCase
{
    #[Test]
    public function the_superadmin_shells_declare_the_platform_persona(): void
    {
        foreach (['resources/views/platform-app.blade.php', 'resources/views/engineering-app.blade.php'] as $view) {
            $markup = (string) file_get_contents(base_path($view));

            /*
                ÖZNİTELİĞİN YERİ ÖLÇÜLÜYOR, ETİKETİN TAM DİZESİ DEĞİL.

                Önce `'<body data-persona="platform">'` birebir aranıyordu ve
                bu, gövdeye ikinci bir öznitelik eklenen ilk günde kırıldı —
                oysa ölçmek istediği şey (persona sunucudan, `<body>`
                üzerinde) hiç değişmemişti. Testin kendi kırılganlığı, ölçtüğü
                kuralı yanlış bir yerden tutmasıydı.
            */
            self::assertMatchesRegularExpression(
                '/<body[^>]*\sdata-persona="platform"/',
                $markup,
                "PERSONA-SURFACE-01: [{$view}] persona `<body>` üzerinde bildirmeli; `<html>` RTL kapısında donmuştur ve ilk boyama kiracı tonunda başlayamaz."
            );
        }
    }

    #[Test]
    public function the_tenant_and_public_shells_declare_no_persona(): void
    {
        $views = [
            'resources/views/workspace-app.blade.php',
            'resources/views/public/layout.blade.php',
            'resources/views/public-menu.blade.php',
        ];

        foreach ($views as $view) {
            $markup = (string) file_get_contents(base_path($view));

            self::assertStringNotContainsString(
                'data-persona',
                $markup,
                "PERSONA-SURFACE-01: [{$view}] persona BİLDİRMEZ — restoran ve misafir yüzeyleri kromasız kalır."
            );
        }
    }
}
