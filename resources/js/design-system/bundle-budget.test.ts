/**
 * Performans bütçesi kapısı — Dalga 4 (`docs/37` §5).
 *
 * `docs/06` bir JS bütçesi tanımlar fakat hiçbir şey onu ölçmüyordu. Bütçe,
 * ölçülmediği sürece bir temennidir: bundle sessizce büyür ve bunu ilk fark
 * eden, yavaş bir telefonda menüyü açmaya çalışan misafir olur.
 *
 * ÖLÇÜM BİRİMİ (FF-72, `docs/98` §6): bir ZİYARETÇİNİN İNDİRDİĞİ kadar.
 * 2026-09-04'e kadar `public/build/assets` altındaki bütün JS dosyalarının
 * toplamı ölçülüyordu — auth + platform + mühendislik + çalışma alanı
 * (masaüstü + mobil) hep birlikte. Hiçbir tarayıcı o toplamı indirmez;
 * misafir menüsü ise hiç JS yüklemez (`docs/38` §16). Şimdi her giriş
 * noktasının manifest'teki KAPANIŞI (kendi dosyası + içe aktardığı parçalar)
 * ölçülür ve en büyüğü bütçeye vurulur. Sayı (200 KB) DEĞİŞMEDİ; yalnız
 * neyi saydığı dürüstleşti.
 *
 * Requirement ID'si: DS-BUNDLE-BUDGET-07.
 */
import { describe, expect, it } from 'vitest';
import { execSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';
import budget from './bundle-budget.json';

const BUILD_DIR = 'public/build';
const MANIFEST = join(BUILD_DIR, 'manifest.json');

type ManifestEntry = { file: string; isEntry?: boolean; imports?: string[] };

function gzipKb(file: string): number {
    return gzipSync(readFileSync(join(BUILD_DIR, file))).length / 1024;
}

/** Bir giriş noktasının indirdiği her JS parçası — bir kez sayılır. */
function closureKb(
    manifest: Record<string, ManifestEntry>,
    key: string,
    seen: Set<string>,
): number {
    if (seen.has(key)) return 0;
    seen.add(key);
    const entry = manifest[key];
    if (!entry) return 0;
    let total = entry.file.endsWith('.js') ? gzipKb(entry.file) : 0;
    for (const imported of entry.imports ?? []) {
        total += closureKb(manifest, imported, seen);
    }
    return total;
}

describe('performans bütçesi', () => {
    it('hiçbir yüzeyin JS kapanışı bütçeyi aşmaz', () => {
        if (!existsSync(MANIFEST)) {
            execSync('npm run build', { stdio: 'ignore' });
        }

        const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8')) as Record<
            string,
            ManifestEntry
        >;
        const surfaces = Object.entries(manifest)
            .filter(([key, entry]) => entry.isEntry && /\.tsx?$/.test(key))
            .map(([key]) => ({ key, kb: closureKb(manifest, key, new Set()) }))
            .sort((a, b) => b.kb - a.kb);

        expect(
            surfaces.length,
            'DS-BUNDLE-BUDGET-07: hiç giriş noktası ölçülmedi.',
        ).toBeGreaterThan(0);

        const heaviest = surfaces[0];
        expect(
            heaviest.kb,
            `DS-BUNDLE-BUDGET-07: ${heaviest.key} kapanışı ${heaviest.kb.toFixed(0)} KB gzip — bütçe ` +
                `${budget.maxTotalGzipKb} KB (docs/06). Bütçeyi yükseltmek, kullanıcının ekranı ` +
                'açarken beklediği süreyi uzatmaktır; önce ne büyüdüğüne bakın.',
        ).toBeLessThanOrEqual(budget.maxTotalGzipKb);
    }, 300_000);
});
