import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * PREVIEW-TRUTH / ERROR-BOUNDARY muhafızı — `docs/52`.
 *
 * Bu kapının varlık sebebi somut: paket yazılmadan önce depoda TEK BİR hata
 * sınırı yoktu ve bunun sonucu gözlenmişti — `i.map is not a function`
 * hatası bütün paneli bomboş bir sayfaya çeviriyordu.
 *
 * Sınırları bir kez eklemek yetmez, çünkü kusur EKLEME anında değil,
 * SONRAKİ giriş noktası eklendiğinde geri gelir: yeni bir yüzey yazan kişi
 * sarmalamayı unutur, hiçbir test düşmez (o yüzey zaten çalışıyordur) ve
 * koruma sessizce yalnız eski ekranlarda kalır.
 */

const JS_ROOT = path.dirname(fileURLToPath(import.meta.url));

/** React'i DOM'a bağlayan her dosya bir giriş noktasıdır. */
const ENTRY_POINTS = globSync('*.tsx', { cwd: JS_ROOT })
    .map((file) => path.join(JS_ROOT, file))
    .filter((file) => readFileSync(file, 'utf8').includes('createRoot('));

describe('giriş noktası korumaları (docs/52)', () => {
    it('giriş noktası bulunmadan geçmez', () => {
        expect(ENTRY_POINTS.length).toBeGreaterThanOrEqual(3);
    });

    it('her giriş noktası ağacını bir hata sınırıyla sarmalar', () => {
        const offenders = ENTRY_POINTS.filter(
            (file) => !readFileSync(file, 'utf8').includes('<AppErrorBoundary'),
        ).map((file) => path.relative(JS_ROOT, file));

        expect(
            offenders,
            'ERROR-BOUNDARY: sarmalanmamış giriş noktası bulundu. Sınırsız bir ' +
                'render hatası ekranı BOMBOŞ bırakır — kullanıcıya ne olduğunu, ne ' +
                'yapacağını söylemez ve çoğu zaman hiç bildirilmez. Ağacı ' +
                '<AppErrorBoundary scope="app"> ile sarmala.',
        ).toEqual([]);
    });

    /**
     * Şerit sınırın DIŞINDA olmalı.
     *
     * İçeride olsaydı, uygulama çöktüğü anda kaybolurdu — yani en çok
     * ihtiyaç duyulduğu anda susardı. Yanlış sürümün çalışıyor olması
     * çökmenin sebebi olabilir; o bilgi tam da o ekranda gerekir.
     */
    it('build şeridi hata sınırının dışında durur', () => {
        const offenders: string[] = [];

        for (const file of ENTRY_POINTS) {
            const source = readFileSync(file, 'utf8');

            if (!source.includes('<BuildTruthBanner />')) {
                offenders.push(`${path.relative(JS_ROOT, file)}: şerit hiç yok`);
                continue;
            }

            if (source.indexOf('<BuildTruthBanner />') > source.indexOf('<AppErrorBoundary')) {
                offenders.push(`${path.relative(JS_ROOT, file)}: şerit sınırın içinde`);
            }
        }

        expect(
            offenders,
            'PREVIEW-TRUTH: <BuildTruthBanner /> hata sınırından ÖNCE gelmeli; ' +
                'aksi hâlde uygulama çöktüğünde sürüm uyarısı da kaybolur.',
        ).toEqual([]);
    });
});
