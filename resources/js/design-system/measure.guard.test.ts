import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * DS-MEASURE — sayfa genişliği token'dan gelir, ham ölçekten değil.
 *
 * Dışarıdan gelen UX eleştirisi sayfa genişliklerini sabit piksel
 * aralıklarıyla istiyordu (form 560–720px, tablo 1200–1440px). Hedef
 * doğruydu; ifade biçimi külliyatla çelişiyordu ve külliyat master:
 * `docs/36` §5.3 UI geometrisinin logical token olmasını, §5.4 ise
 * bileşenin ham geometri BİLMEMESİNİ şart koşar.
 *
 * Sabit piksel ayrıca yanlış şeye tepki verir: ekrana, kapsayıcıya değil.
 * Kenar çubuğu açılıp kapandığında içerik genişliği aynı kalır ve ölçü
 * bozulur. `ch` birimi ise satırdaki karakter sayısını sabitler — font
 * değişse bile okunabilirlik korunur.
 *
 * Uzlaştırma `docs/44`'te kayıtlı.
 */

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CSS = path.resolve(ROOT, '../css/app.css');
const DEBT = path.resolve(ROOT, 'design-system/measure-debt.json');

function sourceFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir)) {
        const full = path.join(dir, entry);

        if (statSync(full).isDirectory()) {
            found.push(...sourceFiles(full));

            continue;
        }

        if (entry.endsWith('.tsx') && !entry.includes('.test.') && !entry.includes('.stories.')) {
            found.push(full);
        }
    }

    return found;
}

function rawWidthUses(): Record<string, number> {
    const pattern = /\bmax-w-(xs|sm|md|lg|xl|[2-7]xl)\b/g;
    const counts: Record<string, number> = {};

    for (const file of sourceFiles(ROOT)) {
        const matches = readFileSync(file, 'utf8').match(pattern);

        if (matches) {
            counts[path.relative(ROOT, file)] = matches.length;
        }
    }

    return counts;
}

describe('DS-MEASURE — okunabilir genişlik token kökünden gelir', () => {
    it('yayınlanan ölçek, kapsayıcıya teslim olan ve karaktere dayalı ölçüler tanımlar', () => {
        const css = readFileSync(CSS, 'utf8');

        for (const token of ['--container-form', '--container-content', '--container-table']) {
            expect(css, `DS-MEASURE-SCALE-01: ${token} yayınlanmamış.`).toContain(`${token}:`);
        }

        // `ch` — ölçü satırdaki karakter sayısıdır; piksel değil.
        expect(css).toMatch(/--container-form:\s*min\(100%,\s*\d+ch\)/);

        // `min(100%, …)` — dar ekranda kapsayıcıya teslim olur (ASG-320).
        expect(css).toMatch(/--container-content:\s*min\(100%/);
    });

    it('ham genişlik borcu artmaz', () => {
        const debt = JSON.parse(readFileSync(DEBT, 'utf8')) as {
            total: number;
            byFile: Record<string, number>;
        };

        const measured = Object.values(rawWidthUses()).reduce((sum, n) => sum + n, 0);

        expect(
            measured,
            `DS-MEASURE-RATCHET-01: ham genişlik kullanımı ${debt.total} → ${measured} arttı. ` +
                'Sayfa ya da form kapsayıcısıysa max-w-form / max-w-content / max-w-table kullan.',
        ).toBeLessThanOrEqual(debt.total);
    });

    it('erimiş borç geri yazılır ki kazanım geri alınamasın', () => {
        const debt = JSON.parse(readFileSync(DEBT, 'utf8')) as { total: number };
        const measured = Object.values(rawWidthUses()).reduce((sum, n) => sum + n, 0);

        expect(
            measured,
            `DS-MEASURE-RATCHET-01: borç ${debt.total} → ${measured} düştü — iyi haber. ` +
                '`measure-debt.json` içindeki değerleri yeni ölçüme çek.',
        ).toBe(debt.total);
    });
});
