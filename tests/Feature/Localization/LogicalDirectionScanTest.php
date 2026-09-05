<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Tests\TestCase;

/**
 * `docs/121` Ö10 — mantıksal CSS taraması, ŞABLON ve STİL katmanında.
 *
 * ═══ NEDEN AYRI BİR KAPI ═══
 *
 * `DS-LOGICAL-DIRECTION-06` (`resources/js/design-system/design-system.guard.test.ts`)
 * bu kuralı React bileşenlerinde zaten zorluyor ve orada ihlal sayısı SIFIR.
 * Ama o kapı `resources/js` altındaki `.tsx` dosyalarını okur; Blade
 * şablonları ve CSS dosyaları HİÇBİR kapının kapsamında değildi.
 *
 * Bu, kapının kendisinden daha tehlikeli bir durumdu: kurumsal sitenin
 * tamamı Blade'dir ve dokuz dilin ikisi (`ar`, `fa`) sağdan sola yazılır.
 * Fiziksel bir yön özelliği o iki dilde arayüzü SESSİZCE yanlış tarafa
 * hizalar — hata vermez, log basmaz, testi kırmaz. Yalnız yanlış görünür.
 *
 * ═══ NEDEN CIRCIR (RATCHET), MUTLAK YASAK DEĞİL ═══
 *
 * Ölçüm günü (2026-09-05) tek bir ihlal bulundu ve o ihlal bu paketin
 * kapsamı dışında — paket sınırı korunuyor. Sayı yalnız AZALABİLİR: yeni bir
 * fiziksel yön eklendiği an kapı kırılır, mevcut borç ise ayrı bir pakette
 * kapanır. Sıfıra indiği gün bu liste boşalır ve kural mutlaklaşır.
 *
 * Requirement ID'leri: I18N-LOGICAL-BLADE-20, I18N-LOGICAL-CSS-21.
 */
final class LogicalDirectionScanTest extends TestCase
{
    /**
     * Tailwind'in FİZİKSEL yön sınıfları.
     *
     * Mantıksal karşılıkları: `ms-`/`me-`/`ps-`/`pe-`/`text-start`/`text-end`/
     * `border-s`/`border-e`/`start-`/`end-`.
     */
    private const PHYSICAL_CLASSES = '/(?:^|[\s"\'])(?:m[lr]|p[lr])-[0-9a-z\[]|(?:^|[\s"\'])text-(?:left|right)\b|(?:^|[\s"\'])(?:border|rounded)-[lr]-|(?:^|[\s"\'])(?:left|right)-[0-9\[]|float-(?:left|right)/';

    /** CSS'in fiziksel yön ÖZELLİKLERİ — mantıksalı `*-inline-start/end`. */
    private const PHYSICAL_PROPERTIES = '/(?:margin|padding|border)-(?:left|right)\s*:|text-align\s*:\s*(?:left|right)|float\s*:\s*(?:left|right)/';

    /**
     * Bilinen ve KAYITLI borç — yalnız azalabilir.
     *
     * Her satır bir dosyadır; sayı o dosyadaki ihlal sayısıdır. Dosya adını
     * yazmak, borcu "bir sayı" olmaktan çıkarıp adreslenebilir kılar.
     *
     * @var array<string, int>
     */
    private const DEBT = [
        // `pl-5` — madde işaretli listenin girintisi. Arapça ve Farsçada
        // girinti yanlış tarafta kalır ve madde imleri metnin dışına düşer.
        'resources/views/public/partials/pricing.blade.php' => 1,
    ];

    // --- I18N-LOGICAL-BLADE-20 --------------------------------------------

    public function test_no_blade_template_adds_a_new_physical_direction_class(): void
    {
        $offenders = $this->scan(base_path('resources/views'), 'blade.php', self::PHYSICAL_CLASSES);

        self::assertNotEmpty(
            $this->files(base_path('resources/views'), 'blade.php'),
            'I18N-LOGICAL-BLADE-20: hiç şablon taranmadı — tarama bozulmuş olabilir ve sessizce "geçti" derdi.'
        );

        $this->assertWithinDebt($offenders, 'I18N-LOGICAL-BLADE-20');
    }

    // --- I18N-LOGICAL-CSS-21 ----------------------------------------------

    /**
     * CSS katmanı MUTLAK: burada borç yok ve olmamalı.
     *
     * Ölçüm günü sıfır ihlal bulundu. Bir stil dosyasına fiziksel yön
     * özelliği girmesi, bileşen katmanındaki bütün mantıksal sınıfları tek
     * hamlede boşa çıkarır — çünkü kaskad bileşeni ezer.
     */
    public function test_no_stylesheet_uses_a_physical_direction_property(): void
    {
        $offenders = $this->scan(base_path('resources/css'), 'css', self::PHYSICAL_PROPERTIES);

        self::assertSame(
            [],
            $offenders,
            'I18N-LOGICAL-CSS-21: stil katmanında fiziksel yön özelliği — `ar` ve `fa` sessizce ters hizalanır. '
            .'Mantıksal karşılığını kullanın (`margin-inline-start`, `padding-inline-end`, `text-align: start`).'
        );
    }

    /**
     * @param  array<string, int>  $offenders
     */
    private function assertWithinDebt(array $offenders, string $requirement): void
    {
        foreach ($offenders as $path => $count) {
            $allowed = self::DEBT[$path] ?? 0;

            self::assertLessThanOrEqual(
                $allowed,
                $count,
                "{$requirement}: `{$path}` içinde {$count} fiziksel yön sınıfı var, kayıtlı borç {$allowed}. "
                .'Sağdan sola yazılan `ar` ve `fa` bu kuralı ters uygular; mantıksal karşılığını kullanın '
                .'(ms-/me-/ps-/pe-/text-start/text-end/border-s/border-e/start-/end-).'
            );
        }

        // Kapanan borç listeden düşer; düşmezse liste bir gün gerçeği
        // anlatmayan bir kalıntıya döner.
        foreach (self::DEBT as $path => $allowed) {
            self::assertArrayHasKey(
                $path,
                $offenders,
                "{$requirement}: `{$path}` artık temiz — borç listesinden silin."
            );
        }
    }

    /** @return array<string, int> */
    private function scan(string $root, string $extension, string $pattern): array
    {
        $offenders = [];

        foreach ($this->files($root, $extension) as $path) {
            $body = (string) file_get_contents($path);
            $count = preg_match_all($pattern, $body);

            if ($count > 0) {
                $offenders[ltrim(str_replace(base_path(), '', $path), '/')] = $count;
            }
        }

        return $offenders;
    }

    /** @return list<string> */
    private function files(string $root, string $extension): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $found = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.'.$extension)) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
