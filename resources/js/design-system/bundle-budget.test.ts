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
 * ölçülür ve en büyüğü bütçeye vurulur.
 *
 * ÖLÇÜMÜN KENDİSİ ÖLÇÜLDÜ (FF-235, `docs/143`). Kapı 2026-09-07'de tam
 * eşikte duruyordu ve ortamdan ortama kırmızı/yeşil karar veriyordu: AYNI
 * commit (55c57e6) CI'da 200,065 KB, aynı makinede 199,771 KB ölçüldü.
 * Fark derlemeden GELMİYORDU — CI'nın ve yerelin ürettiği 117 JS dosyasının
 * adı ve boyutu birebir aynıydı (adlar içerik özeti olduğu için bu, baytların
 * aynı olduğunun kanıtıdır). Fark ÖLÇEN taraftaydı: `zlib.gzipSync`, koşan
 * Node'un bağlandığı zlib gerçeklemesine göre aynı baytları farklı sıkıştırır
 * (yerel Node paylaşılan zlib 1.2.12'ye bağlı, CI'nınki kendi kopyasını
 * taşıyor). Ölçülen band: %0,15.
 *
 * Bu yüzden kapının ASIL eşiği artık HAM BAYTTIR: ham bayt derlemenin
 * çıktısıdır ve belirlenimdir. Gzip eşiği `docs/06` ile sürekliliği koruduğu
 * için DURUYOR, fakat ölçülen bandın on katından fazla payla. Gzip ham
 * bayttan asla büyük olamayacağı için ham eşik gevşek değil, DAHA SIKI bir
 * kısıttır: sıkıştırılabilir yığın (ör. büyük tekrarlı çeviri tabloları)
 * gzip'te ucuz görünür, ham bayttadır ki görünür.
 *
 * Requirement ID'si: DS-BUNDLE-BUDGET-07.
 */
import { afterAll, describe, expect, it } from 'vitest';
import { execFileSync, execSync } from 'node:child_process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';
import budget from './bundle-budget.json';

const BUILD_DIR = 'public/build';

type ManifestEntry = { file: string; isEntry?: boolean; imports?: string[] };
type Manifest = Record<string, ManifestEntry>;

function readManifest(dir: string): Manifest {
    return JSON.parse(readFileSync(join(dir, 'manifest.json'), 'utf8')) as Manifest;
}

/** Bir giriş noktasının indirdiği her JS parçası — bir kez sayılır. */
function closureFiles(manifest: Manifest, key: string, seen: Set<string>): string[] {
    if (seen.has(key)) return [];
    seen.add(key);
    const entry = manifest[key];
    if (!entry) return [];
    const files = entry.file.endsWith('.js') ? [entry.file] : [];
    for (const imported of entry.imports ?? []) {
        files.push(...closureFiles(manifest, imported, seen));
    }
    return files;
}

function surfaces(dir: string) {
    const manifest = readManifest(dir);
    return Object.entries(manifest)
        .filter(([key, entry]) => entry.isEntry && /\.tsx?$/.test(key))
        .map(([key]) => {
            const files = closureFiles(manifest, key, new Set());
            let rawKb = 0;
            let gzipKb = 0;
            for (const file of files) {
                const bytes = readFileSync(join(dir, file));
                rawKb += bytes.length / 1024;
                gzipKb += gzipSync(bytes).length / 1024;
            }
            return { key, files, rawKb, gzipKb };
        })
        .sort((a, b) => b.rawKb - a.rawKb);
}

function ensureBuilt(): void {
    if (!existsSync(join(BUILD_DIR, 'manifest.json'))) {
        execSync('npm run build', { stdio: 'ignore' });
    }
}

const scratchDirs: string[] = [];

afterAll(() => {
    for (const dir of scratchDirs) rmSync(dir, { recursive: true, force: true });
});

describe('performans bütçesi', () => {
    it('hiçbir yüzeyin JS kapanışı bütçeyi aşmaz', () => {
        ensureBuilt();

        const measured = surfaces(BUILD_DIR);

        expect(
            measured.length,
            'DS-BUNDLE-BUDGET-07: hiç giriş noktası ölçülmedi.',
        ).toBeGreaterThan(0);

        const heaviest = measured[0];
        const detail =
            `${heaviest.key} kapanışı ${heaviest.rawKb.toFixed(1)} KB ham / ` +
            `${heaviest.gzipKb.toFixed(1)} KB gzip, ${heaviest.files.length} parça`;

        expect(
            heaviest.rawKb,
            `DS-BUNDLE-BUDGET-07: ${detail} — ham bütçe ${budget.maxTotalRawKb} KB (docs/06, ` +
                'docs/143). Bütçeyi yükseltmek, kullanıcının ekranı açarken beklediği süreyi ' +
                'uzatmaktır; önce ne büyüdüğüne bakın.',
        ).toBeLessThanOrEqual(budget.maxTotalRawKb);

        expect(
            heaviest.gzipKb,
            `DS-BUNDLE-BUDGET-07: ${detail} — gzip bütçe ${budget.maxTotalGzipKb} KB (docs/06, ` +
                'docs/143). Bütçeyi yükseltmek, kullanıcının ekranı açarken beklediği süreyi ' +
                'uzatmaktır; önce ne büyüdüğüne bakın.',
        ).toBeLessThanOrEqual(budget.maxTotalGzipKb);
    }, 300_000);

    /*
        Bir bütçe, ölçtüğü sayı koşudan koşuya oynuyorsa bütçe değildir: yeşil
        de kırmızı da tesadüf olur. Bu iddia, kapının ölçtüğü şeyin KARARLI
        olduğunu kapının kendi içinde kanıtlar — aynı kaynaktan ikinci bir
        derleme, birincisiyle bayt bayt aynı parçaları üretmelidir.

        Ölçülen tek sayı değil, kapanıştaki HER parçanın adı ve boyutudur:
        Rollup parça adını içerikten türetir, yani aynı ad + aynı boyut aynı
        bayt demektir. Toplam eşit çıkıp içerik kaymış olamaz.
    */
    it('ölçtüğü sayı belirlenimdir: ikinci derleme aynı paketi üretir', () => {
        ensureBuilt();

        const scratch = mkdtempSync(join(tmpdir(), 'zabuno-bundle-budget-'));
        scratchDirs.push(scratch);

        /*
            `NODE_ENV` AÇIKÇA verilir. Vitest kendi sürecine `NODE_ENV=test`
            koyar; miras alan bir alt derleme React'in GELİŞTİRME çalışma
            zamanını paketler ve 642 KB'lık kapanış 875 KB olur. Karşılaştırma
            o hâlde derlemenin kararlılığını değil, iki farklı derlemeyi
            ölçerdi — kapı her koşuda kırılırdı ve sebebi paket olmazdı.
        */
        execFileSync(
            'npx',
            [
                'vite',
                'build',
                '--mode',
                'production',
                '--outDir',
                scratch,
                '--emptyOutDir',
                '--logLevel',
                'error',
            ],
            { stdio: 'ignore', env: { ...process.env, NODE_ENV: 'production' } },
        );

        const first = surfaces(BUILD_DIR);
        const second = surfaces(scratch);

        const fingerprint = (list: ReturnType<typeof surfaces>) =>
            list.map((s) => ({
                key: s.key,
                files: [...s.files].sort(),
                rawKb: Number(s.rawKb.toFixed(4)),
            }));

        expect(
            fingerprint(second),
            'DS-BUNDLE-BUDGET-07: aynı kaynaktan iki derleme farklı paket üretti. Bütçe ' +
                'kapısı bu hâlde bir şey ölçmez; önce derlemeye giren ortama bağlı girdiyi ' +
                'bulun (docs/143 §2).',
        ).toEqual(fingerprint(first));
    }, 300_000);
});
