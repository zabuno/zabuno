import { describe, expect, it } from 'vitest';
import { globSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * SAHİP PUANI KALDIRAMAZ — kaynak düzeyinde muhafız (`docs/116` §4).
 *
 * Sunucuda bu kural bir kontrolle değil, ELDE OLMAYAN BİR BAĞIMLILIKLA
 * korunuyor: yanıt denetleyicilerinin sinyal ya da puan deposuna uzanan bir
 * eli yok. Ekran tarafında öyle bir doğal koruma yok — burada her adres bir
 * dizedir ve `/ratings/products/501` ile `/ratings/products/501/reply`
 * arasındaki fark tek bir kelimedir.
 *
 * Bu yüzden kural yazıya dökülüyor: puan ekranından çıkan GET olmayan her
 * istek `/reply` ile bitmek zorunda. Bir gün biri "sahibe kaldırma düğmesi
 * ekleyelim" derse, o gün bu test kırılır ve karar yeniden verilir —
 * sessizce eskimez.
 *
 * Requirement ID'si: RATING-SCREEN-NO-DELETE-05.
 */

const RATINGS_DIR = path.dirname(fileURLToPath(import.meta.url));
const PAGES_DIR = path.join(RATINGS_DIR, '..');

const SOURCES = [
    ...globSync('*.ts', { cwd: RATINGS_DIR }).map((file) => path.join(RATINGS_DIR, file)),
    ...globSync('*.tsx', { cwd: RATINGS_DIR }).map((file) => path.join(RATINGS_DIR, file)),
    path.join(PAGES_DIR, 'RatingsPage.tsx'),
].filter((file) => !file.includes('.test.'));

/** `method: 'PUT'` / `method: 'DELETE'` — durumu değiştiren her istek. */
const MUTATING_METHOD = /method:\s*'(PUT|POST|DELETE|PATCH)'/;

describe('puan ekranı puanı kaldıramaz (docs/116 §4)', () => {
    it('kaynak bulunmadan geçmez', () => {
        expect(SOURCES.length).toBeGreaterThan(1);
    });

    it('durumu değiştiren her istek yalnız SAHİBİN KENDİ CÜMLESİNE gider', () => {
        const offenders: string[] = [];

        for (const file of SOURCES) {
            const source = readFileSync(file, 'utf8');

            if (!MUTATING_METHOD.test(source)) {
                continue;
            }

            for (const url of source.matchAll(/`\/api\/[^`]*`/g)) {
                if (url[0].includes('/ratings/') && !url[0].includes('/reply')) {
                    offenders.push(`${path.basename(file)}: ${url[0]}`);
                }
            }
        }

        expect(
            offenders,
            'RATING-SCREEN-NO-DELETE-05: puan ekranından puana yazan bir adres bulundu. ' +
                'Sahip yanıt verir, kaldıramaz: silinebilen bir ortalama misafire ' +
                '"bu restoranın seçtiği oyların ortalaması" olarak gösterilir; yani bir ' +
                'ölçüm değil, bir reklam.',
        ).toEqual([]);
    });
});
