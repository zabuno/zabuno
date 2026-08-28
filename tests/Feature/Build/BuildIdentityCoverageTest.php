<?php

declare(strict_types=1);

namespace Tests\Feature\Build;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * React mount eden HER görünüm derleme kimliğini taşır — `docs/52`.
 *
 * Kapsamı bir kez sağlamak yetmez, çünkü boşluk EKLEME anında değil, sonraki
 * yüzey eklendiğinde oluşur: yeni bir Blade görünümü yazan kişi kimlik
 * eklemeyi unutur, hiçbir test düşer (o sayfa zaten çalışmaktadır), ve
 * ayrışma tespiti sessizce yalnız eski sayfalarda kalır. Sessizce yarım kalan
 * bir dedektör, hiç olmayandan tehlikelidir: varlığına güvenilir.
 */
final class BuildIdentityCoverageTest extends TestCase
{
    #[Test]
    public function every_view_that_mounts_react_carries_the_build_identity(): void
    {
        $views = glob(resource_path('views').'/{,*/}*.blade.php', GLOB_BRACE) ?: [];

        /*
         * Bir görünümün React mount ettiği, giriş dosyasının ADINDAN değil
         * `@vite(` çağrısından anlaşılır.
         *
         * Önceden yalnız `.tsx` metni aranıyordu. Cihaza göre ayrı paket
         * sunulmaya başlandığında (docs/54) workspace görünümünde giriş adı
         * çalışma zamanında üretilir oldu — `entryFor('workspace')` — ve bu
         * muhafız o görünümü SESSİZCE görmez hâle geldi. Kapı, kontrol ettiği
         * şeyin adına değil, yaptığı işe bakmalı.
         */
        $mountsReact = array_filter(
            $views,
            static function (string $file): bool {
                $source = file_get_contents($file);

                if ($source === false) {
                    return false;
                }

                return str_contains($source, '.tsx') || str_contains($source, 'entryFor(');
            },
        );

        $this->assertGreaterThanOrEqual(
            9,
            count($mountsReact),
            'React mount eden görünüm bulunamadı: bu test hiçbir şey ölçmüyor olabilir.',
        );

        $missing = [];

        foreach ($mountsReact as $file) {
            $source = (string) file_get_contents($file);

            if (! str_contains($source, "@include('partials.build-identity')")) {
                $missing[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $missing,
            'PREVIEW-TRUTH: React mount eden şu görünümler derleme kimliği taşımıyor: '
                .implode(', ', $missing)
                ."\nBu sayfalarda hangi sürümün çalıştığı tespit edilemez; "
                .'ayrışma sessiz kalır. @include(\'partials.build-identity\') ekle.',
        );
    }
}
