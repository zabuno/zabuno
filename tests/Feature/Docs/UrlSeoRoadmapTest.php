<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * URL/SEO ek planının (`URL-SEO-v1`) kaybolmamasını sağlayan kapı.
 *
 * Bu plan, URL motoru kurulurken BİLEREK ertelenen işleri taşır. Böyle bir
 * liste tek bir şekilde ölür: kimse ona işaret etmez ve altı ay sonra
 * "biz bunu neden yapmamıştık?" diye sorulur. Kapı, plana giden yolların
 * açık kaldığını doğrular.
 *
 * Requirement ID'leri: ROADMAP-EXISTS-01, ROADMAP-LINKED-02,
 * ROADMAP-PHASED-03, ROADMAP-COUNTER-04.
 */
final class UrlSeoRoadmapTest extends TestCase
{
    private const ROADMAP = 'docs/39-URL-SEO-ROADMAP.md';

    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "Belge yok: {$relative}");

        return (string) file_get_contents($path);
    }

    // --- ROADMAP-EXISTS-01 -------------------------------------------------

    public function test_the_roadmap_exists_and_names_itself(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        self::assertStringContainsString('URL-SEO-v1', $roadmap, 'ROADMAP-EXISTS-01: plan adsızsa ona atıf verilemez.');
    }

    // --- ROADMAP-LINKED-02 -------------------------------------------------

    public function test_every_document_that_should_point_at_the_plan_does(): void
    {
        // Tek bir giriş kapısı yeterli değil: planı arayan kişi politikadan,
        // matristen veya stage belgesinden gelebilir.
        $referrers = [
            'docs/38-URL-POLICY.md',
            'docs/26-MILESTONE-WORK-PACKAGE-MATRIX.md',
            'docs/19-STAGE-02-POST-MVP.md',
            'docs/20-STAGE-03-GO-TO-MARKET.md',
        ];

        foreach ($referrers as $referrer) {
            self::assertStringContainsString(
                'docs/39-URL-SEO-ROADMAP.md',
                $this->read($referrer),
                "ROADMAP-LINKED-02: {$referrer} plana işaret etmiyor; plan oradan görünmez hâle gelmiş."
            );
        }
    }

    // --- ROADMAP-PHASED-03 -------------------------------------------------

    public function test_each_phase_carries_a_trigger_not_just_a_wish_list(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        foreach (['## Faz 1', '## Faz 2', '## Faz 3', '## Faz 4'] as $phase) {
            self::assertStringContainsString($phase, $roadmap, "ROADMAP-PHASED-03: {$phase} yok.");
        }

        // "Sonra bakarız" listesi, bakılmayan listedir. Her fazın ne zaman
        // gerçek olacağını söyleyen bir tetikleyicisi olmalı.
        self::assertGreaterThanOrEqual(
            3,
            substr_count($roadmap, 'Tetikleyici'),
            'ROADMAP-PHASED-03: fazların çoğunda tetikleyici yok; bu bir dilek listesi olur.'
        );
    }

    // --- ROADMAP-COUNTER-04 ------------------------------------------------

    public function test_the_plan_does_not_pretend_to_be_the_stage_counter(): void
    {
        $roadmap = $this->read(self::ROADMAP);

        // `docs/17` §4 sabit 0/8 stage sayacını korur. Ek planın kendi
        // sayacı olması normaldir; ONUN YERİNE GEÇMESİ değildir.
        self::assertStringContainsString(
            'sabit 38-WP payda sayacını DEĞİŞTİRMEZ',
            $roadmap,
            'ROADMAP-COUNTER-04: ek plan, sabit sayacı değiştirmediğini açıkça söylemeli.'
        );
        // Sayacın DEĞERİ dondurulmaz — sayaç ilerlemek için vardır. İlk
        // yazımda değeri sabitlemiştim ve Faz 1 biter bitmez kendi kapım
        // kırıldı. Dondurulan şey BİÇİMDİR: `X/4 faz`, X ∈ [0, 4].
        self::assertSame(
            1,
            preg_match('#\b([0-4])/4 faz\b#u', $roadmap, $matches),
            'ROADMAP-COUNTER-04: plan `X/4 faz` biçiminde bir sayaç taşımalı.'
        );

        self::assertLessThanOrEqual(4, (int) $matches[1]);
    }

    public function test_the_shared_hosting_limit_is_stated_where_it_bites(): void
    {
        // Özel alan adı sertifikası paylaşımlı barındırmada mümkün değildir.
        // Bunu planda söylememek, satılamayacak bir yeteneği satmaya açık
        // kapı bırakmaktır.
        $roadmap = $this->read(self::ROADMAP);

        self::assertStringContainsString('mümkün DEĞİL', $roadmap);
        self::assertStringContainsString('docs/15', $roadmap);
    }
}
