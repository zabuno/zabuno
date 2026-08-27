<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * `DESIGN-2030-v1` planının çürümesini engeller.
 *
 * Tasarım planları en kolay çürüyen belgelerdir: kimse onlara bakmadan da
 * kod yazılabilir. Bu kapı planın görünür kalmasını ve her fazın bir
 * bağımlılık taşımasını şart koşar.
 */
final class DesignSystemRoadmapTest extends TestCase
{
    private const ROADMAP = 'docs/41-DESIGN-SYSTEM-ROADMAP.md';

    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Belge yok: {$relative}");

        return (string) file_get_contents($path);
    }

    public function test_the_roadmap_exists_and_names_itself(): void
    {
        self::assertStringContainsString(
            'DESIGN-2030-v1',
            $this->read(self::ROADMAP),
            'DESIGN-ROADMAP-EXISTS-01: plan adsızsa ona atıf verilemez.'
        );
    }

    public function test_every_document_that_should_point_at_the_plan_does(): void
    {
        // Planı arayan kişi külliyattan, frontend ana planından, matristen
        // veya stage belgesinden gelebilir.
        $referrers = [
            'docs/36-EXTERNAL-DESIGN-CORPUS.md',
            'docs/37-FRONTEND-MASTER-PLAN.md',
            'docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md',
            'docs/19-STAGE-02-POST-MVP.md',
        ];

        foreach ($referrers as $referrer) {
            self::assertStringContainsString(
                self::ROADMAP,
                $this->read($referrer),
                "DESIGN-ROADMAP-LINKED-02: {$referrer} plana işaret etmiyor."
            );
        }
    }

    public function test_each_phase_carries_a_dependency_so_the_order_is_not_a_matter_of_taste(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        foreach (['Faz 1 —', 'Faz 2 —', 'Faz 3 —', 'Faz 4 —', 'Faz 5 —', 'Faz 6 —'] as $phase) {
            self::assertStringContainsString($phase, $roadmap, "DESIGN-ROADMAP-PHASED-03: {$phase} yok.");
        }

        // Faz 1 tabandır ve bağımlılığı yoktur; kalan beşi bağımlılığını yazar.
        self::assertSame(
            5,
            substr_count($roadmap, '**Bağımlılık:'),
            'DESIGN-ROADMAP-PHASED-03: Faz 2–6 bağımlılığını yazmalı; sırasız liste, sırası tartışılan listedir.'
        );
    }

    public function test_the_measured_gap_is_recorded_so_the_plan_is_not_a_matter_of_opinion(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        // Kök bulgu yazılı olmazsa plan keyfî görünür ve ilk sadeleştirmede silinir.
        self::assertStringContainsString(
            '--spacing',
            $roadmap,
            'DESIGN-ROADMAP-EVIDENCE-04: token kökünün kopukluğu yazılı olmalı.'
        );

        self::assertStringContainsString(
            'docs/36',
            $roadmap,
            'DESIGN-ROADMAP-EVIDENCE-04: kanonik karar kaynağına atıf olmalı.'
        );
    }

    public function test_the_plan_does_not_pretend_to_be_the_stage_counter(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        self::assertStringContainsString(
            'sabit 38-WP payda sayacını DEĞİŞTİRMEZ',
            $roadmap,
            'DESIGN-ROADMAP-COUNTER-05: ek plan, sabit sayacı değiştirmediğini söylemeli.'
        );

        // Değeri değil biçimi donduruyoruz.
        self::assertMatchesRegularExpression(
            '/`DESIGN-2030-v1`: \*\*[0-6]\/6 faz tamam\.\*\*/',
            $roadmap,
            'DESIGN-ROADMAP-COUNTER-05: plan kendi sayacını `X/6 faz tamam` biçiminde taşımalı.'
        );
    }
}
