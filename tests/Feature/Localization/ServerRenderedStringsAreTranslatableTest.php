<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Tests\Support\UntranslatableStringScanner;
use Tests\TestCase;

/**
 * I18N-SSR-RATCHET-16 — sunucuda üretilen metin de çevrilebilir olmalı.
 *
 * Sahiplik kararı (`docs/13` §Kaynak dil): kaynak dil İngilizce'dir ve
 * çeviriyi sahibi PO dosyalarından yapar. Bu kararın tek bir teknik şartı
 * var ve o şart koddadır: **ekranda görünen her dize kaynak katalogda bir
 * anahtara sahip olmalı.** Katalogda olmayan dize PO'da satır olarak da
 * yoktur; sahibi onu açıp çeviremez, çünkü görecek bir şey yoktur.
 *
 * React tarafı bu şartı zaten sağlıyordu — dizeler katalog nesnelerinden
 * geliyor. Sunucu tarafı sağlamıyordu ve kimse ölçmemişti: Blade
 * görünümlerinde tek bir çeviri çağrısı yoktu.
 *
 * Neden başta yasak değil de kilit: 71 dize vardı ve çoğu tek bir pakette
 * taşınamayacak kadar dağınıktı. Kilit borcun artmasını imkânsız kıldı,
 * azalmasını serbest bıraktı.
 *
 * **2026-09-04 (FF-98): borç SIFIRA indi ve kural MUTLAK YASAĞA döndü.**
 * Artık Blade içine yazılan tek bir görünür dize bile testi kırar. Eşik yok,
 * taban çizgisi yok, "sonra düzeltiriz" yok — bir istisna verildiği anda
 * sayaç yeniden yürümeye başlar (aynı gerekçe `DS-RAW-PALETTE-BANNED-01`
 * için de yazılmıştı ve orada da çalıştı).
 */
final class ServerRenderedStringsAreTranslatableTest extends TestCase
{
    /** @return array{total:int, byFile:array<string,int>} */
    private function debt(): array
    {
        $raw = (string) file_get_contents(base_path('lang/untranslatable-debt.json'));

        /** @var array{total:int, byFile:array<string,int>} $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @return array<string,int> */
    private function measured(): array
    {
        $counts = [];

        foreach (UntranslatableStringScanner::scanDirectory(resource_path('views')) as $file => $hits) {
            $counts[$file] = count($hits);
        }

        return $counts;
    }

    public function test_the_untranslatable_string_debt_never_grows(): void
    {
        $measured = array_sum($this->measured());
        $recorded = $this->debt()['total'];

        self::assertLessThanOrEqual(
            $recorded,
            $measured,
            "I18N-SSR-RATCHET-16: sunucuda üretilen çevrilemez dize sayısı {$recorded} → {$measured} arttı. "
            .'Yeni bir görünür dize doğrudan Blade içine yazıldı. Sahibi onu PO dosyasından çeviremez, '
            .'çünkü kaynak katalogda karşılığı yok. Dizeyi bir katalog anahtarına taşı.'
        );
    }

    public function test_a_burned_down_debt_is_written_back_so_the_ratchet_tightens(): void
    {
        $measured = array_sum($this->measured());
        $recorded = $this->debt()['total'];

        self::assertSame(
            $recorded,
            $measured,
            "I18N-SSR-RATCHET-16: borç {$recorded} → {$measured} düştü — bu iyi haber. "
            .'`lang/untranslatable-debt.json` içindeki `total` ve `byFile` değerlerini yeni ölçüme çek '
            .'ki kazanım geri alınamasın.'
        );
    }

    /*
        MUTLAK YASAK (FF-98). Borç sıfırlandığı gün, borç dosyasının kendi
        notu ne yapılacağını söylüyordu: kilit yerine yasak. Bu test onu
        sayıdan bağımsız olarak zorlar — kayıtlı borç yanlışlıkla yükseltilse
        bile ölçüm sıfır olmak zorundadır.
    */
    public function test_no_user_visible_string_lives_in_a_blade_view(): void
    {
        $measured = $this->measured();
        $offending = array_filter($measured, static fn (int $count): bool => $count > 0);

        self::assertSame(
            [],
            $offending,
            'I18N-SSR-RATCHET-16: Blade içine görünür bir dize yazıldı. Sahibi onu hiçbir PO '
            .'dosyasında bulamaz ve çeviremez. Dizeyi bir katalog anahtarına taşı: '
            .json_encode($offending, JSON_UNESCAPED_UNICODE)
        );
    }

    public function test_no_single_view_hides_a_growing_debt_behind_a_falling_total(): void
    {
        $measured = $this->measured();

        foreach ($this->debt()['byFile'] as $file => $recorded) {
            $now = $measured[$file] ?? 0;

            self::assertLessThanOrEqual(
                $recorded,
                $now,
                "I18N-SSR-RATCHET-16: {$file} içindeki çevrilemez dize sayısı {$recorded} → {$now} arttı. "
                .'Toplam düşmüş olsa bile bu bir gerileme: borç görünümler arasında taşınamaz.'
            );
        }
    }
}
