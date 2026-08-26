import { describe, expect, it } from 'vitest';
import { execSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';
import budget from './bundle-budget.json';

/**
 * Performans bütçesi kapısı — Dalga 4 (`docs/37` §5).
 *
 * `docs/06` bir JS bütçesi tanımlar fakat hiçbir şey onu ölçmüyordu. Bütçe,
 * ölçülmediği sürece bir temennidir: bundle sessizce büyür ve bunu ilk fark
 * eden, yavaş bir telefonda menüyü açmaya çalışan misafir olur.
 *
 * Requirement ID'si: DS-BUNDLE-BUDGET-07.
 */

const BUILD_DIR = 'public/build/assets';

function totalGzipKb(dir: string): number {
    const bytes = readdirSync(dir)
        .filter((name) => name.endsWith('.js'))
        .map((name) => gzipSync(readFileSync(join(dir, name))).length)
        .reduce((sum, size) => sum + size, 0);

    return bytes / 1024;
}

describe('performans bütçesi', () => {
    it("üretim JS bundle'ı bütçeyi aşmaz", () => {
        if (!existsSync(BUILD_DIR) || !statSync(BUILD_DIR).isDirectory()) {
            // Build yoksa ölçüm anlamsızdır; sessizce geçmek yerine üretiriz,
            // çünkü "build yok" bir bütçe kanıtı değildir.
            execSync('npm run build', { stdio: 'ignore' });
        }

        const measured = totalGzipKb(BUILD_DIR);

        expect(
            measured,
            `DS-BUNDLE-BUDGET-07: JS bundle ${measured.toFixed(0)} KB gzip — bütçe ` +
                `${budget.maxTotalGzipKb} KB (docs/06). Bütçeyi yükseltmek, misafirin ` +
                'menüyü açarken beklediği süreyi uzatmaktır; önce ne büyüdüğüne bakın.',
        ).toBeLessThanOrEqual(budget.maxTotalGzipKb);

        // Ölçüm gerçekten koştu mu? Boş bir dizin sessizce "0 KB, bütçe içinde" derdi.
        expect(measured, 'DS-BUNDLE-BUDGET-07: hiç bundle ölçülmedi.').toBeGreaterThan(0);
    }, 300_000);
});
