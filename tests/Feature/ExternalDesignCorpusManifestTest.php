<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Depo dışı tasarım külliyatının görünürlüğünü koruyan test.
 *
 * Bu depo PUBLIC'tir ve bir klon tasarım külliyatını getirmez. Külliyatın
 * varlığı yalnız `docs/36` ve ona işaret eden dosyalarla bilinir. O
 * işaretçiler sessizce silinirse körlük geri döner — 2026-08-26'da bir oturum
 * tam olarak bu yüzden, çalışan bir referans implementasyonunun yanı başında
 * durduğunu fark etmeden sıfırdan token katmanı kurmaya başladı.
 *
 * Bu test o işaretçileri silinemez kılar: kaybolurlarsa CI kırılır.
 *
 * Requirement ID'leri: CORPUS-MANIFEST-01, CORPUS-POINTERS-02,
 * CORPUS-HANDOVER-03.
 */
final class ExternalDesignCorpusManifestTest extends TestCase
{
    private const MANIFEST = 'docs/36-EXTERNAL-DESIGN-CORPUS.md';

    /**
     * Külliyatı fark etmesi gereken herkesin baktığı yerler:
     * insan (README), devralan (docs/25) ve vibecoding yapan ajan
     * (AGENTS.md / CLAUDE.md — bunlar oturum bağlamına otomatik yüklenir).
     */
    private const POINTER_FILES = [
        'README.md',
        'AGENTS.md',
        'CLAUDE.md',
        'docs/25-STAGE-08-EXIT-READY.md',
    ];

    private function read(string $relative): string
    {
        $path = base_path($relative);

        self::assertFileExists($path, "CORPUS-MANIFEST-01: {$relative} bulunamadı.");

        return (string) file_get_contents($path);
    }

    // --- CORPUS-MANIFEST-01 ----------------------------------------------

    public function test_the_external_design_corpus_manifest_exists_and_states_where_the_corpus_lives(): void
    {
        $manifest = $this->read(self::MANIFEST);

        self::assertStringContainsString(
            '~/DEV/zabuno/frontend',
            $manifest,
            'CORPUS-MANIFEST-01: külliyatın nerede yaşadığı yazılı olmalı; konum olmadan kayıt işe yaramaz.'
        );

        foreach (['tokens', 'foundations', 'renderer-aep'] as $package) {
            self::assertStringContainsString(
                $package,
                $manifest,
                "CORPUS-MANIFEST-01: referans implementasyonunun `{$package}` paketi envanterde yok."
            );
        }

        foreach (['10-frontend-katman-mimarisi', '13-foundation-contract'] as $contract) {
            self::assertStringContainsString(
                $contract,
                $manifest,
                "CORPUS-MANIFEST-01: bu depoda karşılığı olmayan `{$contract}` sözleşmesi envanterde yok."
            );
        }
    }

    // --- CORPUS-POINTERS-02 ----------------------------------------------

    public function test_every_place_a_newcomer_looks_points_at_the_manifest(): void
    {
        foreach (self::POINTER_FILES as $file) {
            self::assertStringContainsString(
                '36-EXTERNAL-DESIGN-CORPUS',
                $this->read($file),
                "CORPUS-POINTERS-02: {$file} artık külliyata işaret etmiyor. "
                .'Bu işaretçi kaldırılırsa depoyu okuyan kişi/ajan külliyatın var olduğunu '
                .'öğrenemez ve körlük geri döner.'
            );
        }
    }

    // --- CORPUS-PHILOSOPHY-IN-REPO-04 -------------------------------------

    public function test_the_philosophy_corpus_now_lives_in_the_repository(): void
    {
        $index = 'docs/design-corpus/README.md';

        self::assertFileExists(
            base_path($index),
            'CORPUS-PHILOSOPHY-IN-REPO-04: felsefe külliyatı 2026-08-26 owner kararıyla depoya taşındı; dizini kaybolmuş.'
        );

        foreach (['olcu-birimleri.md', 'adaptive-semantic-grid.md', 'saas-panel-tasarim-sistemi.md'] as $doc) {
            self::assertFileExists(
                base_path("docs/design-corpus/{$doc}"),
                "CORPUS-PHILOSOPHY-IN-REPO-04: çekirdek felsefe belgesi {$doc} eksik."
            );
        }

        self::assertStringContainsString(
            'design-corpus',
            $this->read(self::MANIFEST),
            'CORPUS-PHILOSOPHY-IN-REPO-04: manifest, felsefenin artık depoda olduğunu söylemeli.'
        );
    }

    // --- FRONTEND-PLAN-POINTERS-05 ----------------------------------------
    // Plan da külliyat gibi yalnız işaretçileriyle bilinir. İşaretçi giderse
    // yeni gelen, kuralların ve zorlayıcıların nerede tanımlandığını bulamaz.
    public function test_the_frontend_master_plan_exists_and_is_discoverable(): void
    {
        self::assertFileExists(
            base_path('docs/37-FRONTEND-MASTER-PLAN.md'),
            'FRONTEND-PLAN-POINTERS-05: frontend planı kaybolmuş.'
        );

        foreach (['README.md', 'AGENTS.md', 'CLAUDE.md'] as $file) {
            self::assertStringContainsString(
                '37-FRONTEND-MASTER-PLAN',
                $this->read($file),
                "FRONTEND-PLAN-POINTERS-05: {$file} artık frontend planına işaret etmiyor."
            );
        }
    }

    // --- CORPUS-HANDOVER-03 ----------------------------------------------

    public function test_the_manifest_warns_that_a_clone_does_not_carry_the_corpus(): void
    {
        $manifest = $this->read(self::MANIFEST);

        self::assertMatchesRegularExpression(
            '/bu Git deposunda değildir|klonla?\s*(birlikte)?\s*gelmez/iu',
            $manifest,
            'CORPUS-HANDOVER-03: külliyatın depoda OLMADIĞI açıkça yazılmalı; '
            .'aksi hâlde devralan taraf klonun yeterli olduğunu sanır.'
        );

        self::assertStringContainsString(
            '36-EXTERNAL-DESIGN-CORPUS',
            $this->read('docs/25-STAGE-08-EXIT-READY.md'),
            'CORPUS-HANDOVER-03: exit dokümanı aktarımı zorunlu devir kalemi olarak taşımalı.'
        );
    }
}
