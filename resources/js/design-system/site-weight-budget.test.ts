/**
 * KURUMSAL SİTENİN AĞIRLIK KAPISI — `docs/138` §6.
 *
 * ═══ NEDEN İKİNCİ BİR BÜTÇE ═══
 *
 * `bundle-budget.test.ts` PANELİN JS bütçesini ölçer: "en ağır uygulama
 * yüzeyi kaç KB indiriyor?" — kaydolmuş, ürünü kullanmaya karar vermiş bir
 * kişinin sorusu. Kurumsal sitenin sorusu başkadır: **ürünü ilk kez gören
 * biri, tek satır okumadan önce kaç bayt bekliyor?**
 *
 * O ölçüm hiç yapılmıyordu ve yapılmadığı için bir sahne katmanı eklemenin
 * bedeli görünmezdi. Bu paket sahneyi getirdi; bedelini de getirmesi
 * gerekiyordu.
 *
 * ═══ NE ÖLÇÜLÜYOR ═══
 *
 *   `resources/css/app.css`      — her kurumsal sayfanın giydiği tek stil
 *   `resources/js/site-motion.ts` — sahne taşıyan sayfaların tek betiği
 *
 * Toplamları gzip'lenmiş hâlde bütçeye vurulur. React paketi ölçülmez ve
 * ölçülmemeli: kurumsal sayfalar onu HİÇ yüklemiyor (`docs/38` §16) ve bu
 * gerçeği bir toplamın içinde saklamak, kaybedildiğinde fark edilmemesine
 * yol açardı — bunun kendi kapısı var (`HOME-NO-REACT-05`).
 *
 * ═══ SAYININ ANLAMI ═══
 *
 * Hedef kitle mutfaktan, zayıf şebekeyle bakan bir restoran sahibi. 32 KB
 * gzip, ~50 KB/s etkili bir bağlantıda 0,65 saniyelik aktarımdır. 2026-09-08
 * ölçümü 29,4 KB; pay ~%9. Bir animasyon kütüphanesi eklemek bu kapıyı
 * DERHAL kırar — GSAP çekirdeği + ScrollTrigger tek başına 46,3 KB gzip,
 * yani bütçenin tamamından büyük.
 *
 * Requirement ID: SITE-WEIGHT-BUDGET-01.
 */
import { describe, expect, it } from 'vitest';
import { execSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { join } from 'node:path';
import budget from './site-weight-budget.json';

const BUILD_DIR = 'public/build';
const MANIFEST = join(BUILD_DIR, 'manifest.json');

/** Kurumsal ziyaretçinin gerçekten indirdiği giriş noktaları. */
const SURFACES = ['resources/css/app.css', 'resources/js/site-motion.ts'];

type ManifestEntry = { file: string; css?: string[]; imports?: string[] };

function gzipKb(file: string): number {
    return gzipSync(readFileSync(join(BUILD_DIR, file))).length / 1024;
}

/**
 * Bir giriş noktasının indirdiği HER parça — bir kez sayılır.
 *
 * `css` alanı da izlenir: bir betik girişi yanında stil getirebilir ve o
 * stil de ziyaretçinin beklediği bayttır.
 */
function closureKb(
    manifest: Record<string, ManifestEntry>,
    key: string,
    seen: Set<string>,
): number {
    if (seen.has(key)) return 0;
    seen.add(key);

    const entry = manifest[key];
    if (!entry) return 0;

    let total = gzipKb(entry.file);

    for (const css of entry.css ?? []) {
        if (!seen.has(css)) {
            seen.add(css);
            total += gzipKb(css);
        }
    }

    for (const imported of entry.imports ?? []) {
        total += closureKb(manifest, imported, seen);
    }

    return total;
}

describe('kurumsal sitenin ağırlık bütçesi', () => {
    it('ilk ziyaretçinin indirdiği stil ve betik bütçeyi aşmaz', () => {
        if (!existsSync(MANIFEST)) {
            execSync('npm run build', { stdio: 'ignore' });
        }

        const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8')) as Record<
            string,
            ManifestEntry
        >;

        /*
            GİRİŞ NOKTASI KAYBOLURSA KAPI SESSİZCE GEÇMEZ.

            Bir giriş `vite.config.ts`ten düşerse toplam sıfıra iner ve
            bütçe "geçti" derdi — ölçülmeyen bir sonucu yeşil göstermek,
            bu deponun kapatmak için yazdığı arıza ailesinin ta kendisi.
        */
        for (const surface of SURFACES) {
            expect(
                manifest[surface],
                `SITE-WEIGHT-BUDGET-01: [${surface}] manifest'te yok — ölçüm YAPILMADI. ` +
                    'Giriş noktası `vite.config.ts` içinden düşmüş olabilir.',
            ).toBeDefined();
        }

        const seen = new Set<string>();
        const total = SURFACES.reduce(
            (sum, surface) => sum + closureKb(manifest, surface, seen),
            0,
        );

        expect(
            total,
            `SITE-WEIGHT-BUDGET-01: kurumsal ziyaretçi ${total.toFixed(1)} KB gzip indiriyor — ` +
                `bütçe ${budget.maxTotalGzipKb} KB (docs/138 §6). Ürünü İLK KEZ gören bir ` +
                'restoran sahibi, tek satır okumadan önce bu kadar bekliyor. Bütçeyi ' +
                'yükseltmeden önce ne büyüdüğüne bakın; ölçümden önce `php artisan view:clear` ' +
                "çalıştırın (kirli görünüm önbelleği CSS'i ~6 KB şişirir).",
        ).toBeLessThanOrEqual(budget.maxTotalGzipKb);
    }, 300_000);
});
