import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * EKRAN ŞEMA DEĞİLDİR — restoran yüzeyinin kelime dağarcığı muhafızı.
 *
 * Bu kapı, panelde tek tek bulunan kusurların ARDINDAKİ ortak nedeni kapatır.
 * Bulunanlar şunlardı ve hepsi gerçekti:
 *
 * - Marka sayfasında rozet olarak markanın VERİTABANI BİRİNCİL ANAHTARI (`#3`),
 * - Lokasyon ve Medya sayfalarında `#` ile kimlik kılığına sokulmuş SAYAÇLAR,
 * - Yayın sayfasında `Requires menu.publish permission` — iç izin anahtarı adı,
 * - `Scheduled publish is not available in Stage 1.` — iç yol haritası aşaması,
 * - `Publishing creates an immutable snapshot` — uygulama detayı,
 * - Faturalandırmada üç kez `no billing API has been queried`,
 * - Medyada denetçi için yazılmış `no scan approves itself` cümlesi.
 *
 * Ortak neden tek: **geliştiricinin "bu gerçekten bağlı" diye kendine kanıt
 * ürettiği metinler, restoran sahibinin ekranında kalıcı hâle gelmiş.** Bunlar
 * yanlış değildi — yanlış YERDEYDİ.
 *
 * Kapsam bilerek yalnız `workspace/` yani restoran yüzeyi. Platform yönetim
 * yüzeyi teknik bir kullanıcıya aittir ve orada "entitlement" gibi sözcükler
 * meşrudur; aynı kuralı oraya dayatmak, doğru olan metni bozardı.
 */

/**
 * Dosya BİLEREK katalog dizininin DIŞINDA duruyor.
 *
 * `workspace.ts`, katalogları `import.meta.glob` ile keşfeder. Bu muhafız
 * ilk yazıldığında `workspace/` içine konmuştu ve glob onu bir katalog sanıp
 * paketledi: i18n boru hattı, içine sızan `describe()` çağrısı yüzünden
 * "Cannot read properties of undefined" diyerek çöktü ve nerede kırıldığı
 * görünmedi. Katalog dizini veri içindir, kod için değil.
 */
const I18N_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), 'workspace');

/**
 * Yalnız STRING DEĞERLERİ sınanır, anahtarlar değil.
 *
 * `'…publishAction.snapshotNotice'` bir anahtardır ve kod içindir; kullanıcı
 * onu hiç görmez. Anahtarları da tarayan bir kural, düzeltilecek hiçbir şeyi
 * olmayan satırlarda ısrar eder ve kısa sürede susturulur.
 */
function stringValues(source: string): string[] {
    const values: string[] = [];

    for (const line of source.split('\n')) {
        if (/^\s*(\/\/|\*|\/\*)/.test(line)) {
            continue;
        }

        // `'anahtar': 'değer',` ya da devam satırındaki tek başına `'değer',`
        const withKey = line.match(/^\s*'[^']+':\s*'(.*)',?\s*$/);
        const bare = line.match(/^\s*'(.*)',?\s*$/);

        if (withKey) {
            values.push(withKey[1]);
        } else if (bare && !line.includes("':")) {
            values.push(bare[1]);
        }
    }

    return values;
}

const FORBIDDEN: Array<{ pattern: RegExp; why: string }> = [
    { pattern: /\bStage \d\b/, why: 'iç yol haritası aşaması' },
    { pattern: /\bAPI\b/, why: 'iç mimari sözcüğü' },
    { pattern: /\bcapabilit(y|ies)\b/i, why: 'iç yetenek sözcüğü' },
    { pattern: /\bimmutable\b/i, why: 'uygulama detayı' },
    { pattern: /\bsnapshot\b/i, why: 'uygulama detayı' },
    { pattern: /\bidempoten/i, why: 'uygulama detayı' },
    { pattern: /\bendpoint\b/i, why: 'iç mimari sözcüğü' },
    { pattern: /\bpayload\b/i, why: 'iç mimari sözcüğü' },
    { pattern: /\b[a-z]+\.[a-z]+ permission\b/, why: 'iç izin anahtarı adı' },
    { pattern: /\bhas been queried\b/i, why: 'kablolama anlatısı' },
];

const FILES = globSync('*.ts', { cwd: I18N_DIR })
    .filter((file) => !file.includes('.test.'))
    .map((file) => path.join(I18N_DIR, file));

describe('restoran yüzeyi kelime dağarcığı (docs/53)', () => {
    it('metin dosyası bulunmadan geçmez', () => {
        expect(FILES.length).toBeGreaterThan(5);
    });

    it('kullanıcıya gösterilen hiçbir metin mühendislik sözcüğü taşımaz', () => {
        const offenders: string[] = [];

        for (const file of FILES) {
            for (const value of stringValues(readFileSync(file, 'utf8'))) {
                for (const { pattern, why } of FORBIDDEN) {
                    if (pattern.test(value)) {
                        offenders.push(`${path.basename(file)}: "${value.slice(0, 64)}" — ${why}`);
                    }
                }
            }
        }

        expect(
            offenders,
            'EKRAN ŞEMA DEĞİLDİR: restoran sahibine gösterilen metinde mühendislik ' +
                'sözcüğü bulundu. Bu cümleler yanlış değil, yanlış YERDE: sistemin ' +
                'doğru çalıştığını kanıtlamak için yazılmışlar ve belgeye aitler. ' +
                'Ekranda kullanıcının YAPABİLECEĞİ şey yazar.',
        ).toEqual([]);
    });
});
