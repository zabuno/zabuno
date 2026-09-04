import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

/**
 * FF-86 (SHELL-RAIL-FIXED-WIDTH-01): kabuk raylarının genişliği SABİTTİR.
 *
 * `flex-[1_1_16rem]` bir büyüme oranıdır: ray, ana içerikle kalan alanı
 * paylaşır ve geniş ekranda ekranın yarısını kaplar (2026-09-04'te platform
 * kabuğunda tam olarak bu görüldü). Ray `basis` + `grow-0 shrink-0` ile
 * çizilir; büyüyen tek bölge ana içeriktir.
 *
 * Kaynak metni okunur, çünkü kural bir bileşenin ÇIKTISI değil, kabuk
 * ailesinin tamamı için geçerli bir yazım kuralıdır.
 */
const SHELL_FILES = [
    'resources/js/components/ops/OpsShell.tsx',
    'resources/js/components/workspace/chrome/DesktopChrome.tsx',
    'resources/js/components/catalog/layout/macro/AdminShell.tsx',
    'resources/js/components/catalog/layout/macro/AdminShell.stories.tsx',
];

describe('kabuk rayları sabit genişliktedir', () => {
    it.each(SHELL_FILES)('%s içinde büyüyen bir ray yok', (file) => {
        const source = readFileSync(file, 'utf8');
        const offenders = [...source.matchAll(/flex-\[\d+_1_\d+rem\]/g)].map((match) => match[0]);

        expect(offenders).toEqual([]);
    });

    it('yan panel ve kenar çubuğu basis ile ölçülür', () => {
        const shell = readFileSync(
            'resources/js/components/catalog/layout/macro/AdminShell.tsx',
            'utf8',
        );
        expect(shell).toMatch(/admin-shell-inspector[^"]*shrink-0 grow-0 basis-\[21rem\]/);

        const desktop = readFileSync(
            'resources/js/components/workspace/chrome/DesktopChrome.tsx',
            'utf8',
        );
        expect(desktop).toMatch(/admin-shell-sidebar[^"]*shrink-0 grow-0 basis-\[17rem\]/);

        const ops = readFileSync('resources/js/components/ops/OpsShell.tsx', 'utf8');
        expect(ops).toMatch(/admin-shell-sidebar[^"]*shrink-0 grow-0 basis-\[16rem\]/);
    });
});
