<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * `I18N-RUNTIME-v1` planının çürümesini engeller.
 *
 * Bu tür bir belge tek şekilde ölür: kimse ona işaret etmez. Plan
 * dururken ona giden yollar kapanır, sonra biri "böyle bir karar var
 * mıydı?" diye sorar ve cevabı kimse bulamaz. Kapı yolları açık tutar.
 */
final class I18nRuntimeRoadmapTest extends TestCase
{
    private const ROADMAP = 'docs/40-I18N-RUNTIME-ROADMAP.md';

    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Belge yok: {$relative}");

        return (string) file_get_contents($path);
    }

    // --- I18N-ROADMAP-EXISTS-01 -------------------------------------------

    public function test_the_roadmap_exists_and_names_itself(): void
    {
        self::assertStringContainsString(
            'I18N-RUNTIME-v1',
            $this->read(self::ROADMAP),
            'I18N-ROADMAP-EXISTS-01: plan adsızsa ona atıf verilemez.'
        );
    }

    // --- I18N-ROADMAP-LINKED-02 -------------------------------------------

    public function test_every_document_that_should_point_at_the_plan_does(): void
    {
        // Planı arayan kişi i18n politikasından, matristen veya stage
        // belgesinden gelebilir. Tek giriş kapısı yeterli değildir.
        $referrers = [
            'docs/13-I18N-L10N-PO-MO.md',
            'docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md',
            'docs/19-STAGE-02-POST-MVP.md',
        ];

        foreach ($referrers as $referrer) {
            self::assertStringContainsString(
                self::ROADMAP,
                $this->read($referrer),
                "I18N-ROADMAP-LINKED-02: {$referrer} plana işaret etmiyor; plan oradan görünmez hâle gelmiş."
            );
        }
    }

    // --- I18N-ROADMAP-PHASED-03 -------------------------------------------

    public function test_each_phase_carries_a_dependency_not_just_a_wish_list(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        foreach (['Faz 1 —', 'Faz 2 —', 'Faz 3 —', 'Faz 4 —'] as $phase) {
            self::assertStringContainsString(
                $phase,
                $roadmap,
                "I18N-ROADMAP-PHASED-03: {$phase} yok."
            );
        }

        self::assertSame(
            4,
            substr_count($roadmap, '**Bağımlılık:'),
            'I18N-ROADMAP-PHASED-03: her fazın bağımlılığı yazılı olmalı — '
            .'sırasız bir liste, sırası tartışılan bir listedir.'
        );
    }

    // --- I18N-ROADMAP-COUNTER-04 ------------------------------------------

    public function test_the_plan_does_not_pretend_to_be_the_stage_counter(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        self::assertStringContainsString(
            'sabit 38-WP payda sayacını DEĞİŞTİRMEZ',
            $roadmap,
            'I18N-ROADMAP-COUNTER-04: ek plan, sabit sayacı değiştirmediğini açıkça söylemeli.'
        );

        // Değeri değil BİÇİMİ donduruyoruz: faz kapandıkça sayı ilerlemeli,
        // kapı da onunla birlikte ilerlemeli.
        self::assertMatchesRegularExpression(
            '/`I18N-RUNTIME-v1`: \*\*[0-4]\/4 faz tamam\.\*\*/',
            $roadmap,
            'I18N-ROADMAP-COUNTER-04: plan kendi ilerleme sayacını `X/4 faz tamam` biçiminde taşımalı.'
        );
    }

    // --- I18N-ROADMAP-CAUSE-05 --------------------------------------------

    public function test_the_reason_the_pipeline_cannot_serve_the_owner_is_stated(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        // Planın tamamı tek bir olgudan doğuyor. O olgu yazılı değilse
        // plan keyfî görünür ve ilk sadeleştirmede silinir.
        self::assertStringContainsString(
            'Node yoktur',
            $roadmap,
            'I18N-ROADMAP-CAUSE-05: paylaşımlı barındırmada Node olmadığı yazılı olmalı — planın sebebi budur.'
        );

        self::assertStringContainsString(
            'docs/15',
            $roadmap,
            'I18N-ROADMAP-CAUSE-05: kısıtın kanonik kaynağına atıf olmalı.'
        );
    }

    // --- I18N-ROADMAP-OWNERSHIP-06 ----------------------------------------

    public function test_the_translation_ownership_decision_is_recorded_where_developers_look(): void
    {
        $policy = $this->read('docs/13-I18N-L10N-PO-MO.md');

        // Üç maddenin üçü de kayıtlı olmalı. Biri düşerse kural yarım
        // kalır ve yarım kural uygulanmaz.
        self::assertStringContainsString(
            'Kaynak dil İngilizce',
            $policy,
            'I18N-ROADMAP-OWNERSHIP-06: kaynak dil kararı kayıtlı değil.'
        );

        self::assertStringContainsString(
            'Çeviriyi sahibi yapar',
            $policy,
            'I18N-ROADMAP-OWNERSHIP-06: çeviri sahipliği kararı kayıtlı değil.'
        );

        self::assertStringContainsString(
            'PO dosyasından çevrilebilir olmalıdır',
            $policy,
            'I18N-ROADMAP-OWNERSHIP-06: çevrilebilirlik şartı kayıtlı değil.'
        );
    }
}
