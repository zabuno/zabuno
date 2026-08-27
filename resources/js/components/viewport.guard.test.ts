import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * VIEWPORT-320-FIRST — `docs/48`.
 *
 * Sahibinin kuralı: bu ürün 320 px (iPhone 4) genişliği için ÖNCE
 * tasarlanır; diğer cihazlar akışkan biçimde uyum sağlar. "Sadece media
 * query" değil — gerçek 320-first.
 *
 * Bu kapı, kuralın en kolay çiğnendiği yeri kapatır: bir düzeni EKRAN
 * genişliğine bağlamak. `sm:flex-row` yazmak kolaydır ve 320'de doğru
 * görünür; ama ekranı dinlediği için kenar çubuğu açık bir masaüstünde dar
 * bir sütunun içinde de "geniş" davranır ve orada yanlıştır.
 *
 * Doğru araçlar sırayla: içsel düzen (`flex-wrap` + `flex-basis`, `minmax`
 * grid), sonra kapsayıcı sorgusu. İkisi de kapsayıcıyı dinler.
 */

const THIS_DIR = path.dirname(fileURLToPath(import.meta.url));

const BREAKPOINT_CLASS = /(?:^|["'\s])(sm|md|lg|xl|2xl):[a-z[]/;

/** Blok ve satır yorumları atılır: açıklamada `sm:` geçmesi ihlal değildir. */
function stripComments(source: string): string {
    return source
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/\{\/\*[\s\S]*?\*\/\}/g, '')
        .replace(/^\s*\/\/.*$/gm, '');
}

const PRODUCTION_FILES = globSync('**/*.tsx', { cwd: THIS_DIR })
    .filter((file) => !file.includes('.test.'))
    .filter((file) => !file.includes('.stories.'))
    .map((file) => path.join(THIS_DIR, file));

describe('320px-first (docs/48)', () => {
    it('üretim bileşeni bulunmadan geçmez', () => {
        expect(PRODUCTION_FILES.length).toBeGreaterThan(20);
    });

    it('hiçbir bileşen düzenini EKRAN genişliğine bağlamaz', () => {
        const offenders: string[] = [];

        for (const file of PRODUCTION_FILES) {
            const source = stripComments(readFileSync(file, 'utf8'));

            for (const line of source.split('\n')) {
                if (BREAKPOINT_CLASS.test(line)) {
                    offenders.push(`${path.relative(THIS_DIR, file)}: ${line.trim().slice(0, 70)}`);
                }
            }
        }

        expect(
            offenders,
            'VIEWPORT-320-FIRST: kırılma noktası önekli sınıf bulundu. Düzen ekranı ' +
                'değil KAPSAYICIYI dinlemeli: içsel düzen (flex-wrap + flex-basis, ' +
                'minmax grid) ya da kapsayıcı sorgusu kullanın (docs/48).',
        ).toEqual([]);
    });

    it('hiçbir bileşen 320 pikselden geniş sabit bir genişlik dayatmaz', () => {
        const offenders: string[] = [];

        // `w-[400px]`, `min-w-[420px]`, `width: '600px'` — 320'de taşarlar.
        const FIXED_WIDTH = /\b(?:min-)?w-\[(\d{3,})px\]|\bwidth:\s*['"](\d{3,})px['"]/g;

        for (const file of PRODUCTION_FILES) {
            const source = stripComments(readFileSync(file, 'utf8'));

            for (const match of source.matchAll(FIXED_WIDTH)) {
                const px = Number(match[1] ?? match[2]);

                if (px > 320) {
                    offenders.push(`${path.relative(THIS_DIR, file)}: ${match[0]}`);
                }
            }
        }

        expect(
            offenders,
            'VIEWPORT-320-FIRST: 320 pikselden geniş sabit genişlik bulundu. ' +
                'iPhone 4 ekranında yatay kaydırma üretir (docs/48).',
        ).toEqual([]);
    });
});
