import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * DS-SPACE-ROOT — boşluğun tek kökü vardır.
 *
 * `--space-1..8` yayınlanıyordu ama hiçbir bileşen onları tüketmiyordu:
 * 323 kullanım (`p-4`, `gap-2`, `mt-6`) Tailwind'in kendi `--spacing`
 * ölçeğinden geliyordu ve o ölçek bu depoda TANIMLI DEĞİLDİ — yani
 * Tailwind varsayılanı (0.25rem) yürürlükteydi.
 *
 * İki ölçek sayısal olarak çakışıyordu (4/8/12/16…), bu yüzden kimse fark
 * etmemişti. Ama `--space-4`'ü değiştirmek ekranda hiçbir şeyi
 * değiştirmiyordu; owner'ın istediği "master değişince hepsi değişir"
 * davranışı boşlukta yoktu — tipografide olmadığı gibi.
 *
 * Külliyatın kararı (`docs/36` §5.3): atomik grid 4, ana ritim 8. `--spacing`
 * o gridin kendisidir ve adımlar ondan TÜRER.
 */

const CSS = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../css/app.css');

describe('DS-SPACE-ROOT — atomik grid token kökünde', () => {
    it('atomik grid yayınlanır ve Tailwind ölçeğinin kaynağıdır', () => {
        const css = readFileSync(CSS, 'utf8');

        // İsim uzayı `--spacing`: `p-4` ve `gap-2` bunu çarpar. Kendi
        // adımızı verseydik token yayınlanır, utility'ler yine Tailwind
        // varsayılanından gelirdi.
        expect(css, 'DS-SPACE-ROOT-01: `--spacing` yayınlanmamış.').toMatch(/--spacing:\s*4px/);
    });

    it('her adım gridin katıdır, ayrı yazılmış bir sabit değil', () => {
        const css = readFileSync(CSS, 'utf8');

        for (let step = 1; step <= 8; step += 1) {
            expect(
                css,
                `DS-SPACE-ROOT-02: --space-${step} gridden türemiyor; ayrı bir sabit olarak yazılmış.`,
            ).toMatch(new RegExp(`--space-${step}:\\s*calc\\(var\\(--spacing\\)`));
        }
    });

    it('ölçek değerleri değişmedi — bağlama görsel bir değişiklik değildi', () => {
        const css = readFileSync(CSS, 'utf8');
        const multipliers = [1, 2, 3, 4, 6, 8, 12, 16];

        multipliers.forEach((multiplier, index) => {
            expect(
                css,
                `DS-SPACE-ROOT-03: --space-${index + 1} çarpanı değişmiş; ` +
                    'kökü bağlamak boşlukları değiştirmemeliydi.',
            ).toMatch(
                new RegExp(
                    `--space-${index + 1}:\\s*calc\\(var\\(--spacing\\) \\* ${multiplier}\\)`,
                ),
            );
        });
    });
});
