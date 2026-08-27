import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * DS-RADIUS-ROOT — köşe yarıçapı token kökünden gelir.
 *
 * Ölçüldü: 44 kullanımın 33'ü (`rounded-md`, `rounded-lg`) ZATEN
 * `--radius-*` token'larını çarpıyordu — ama o token'lar bu depoda tanımlı
 * değildi, yani Tailwind varsayılanı yürürlükteydi. Tipografi ve boşlukta
 * olanın aynısı: tasarım sistemi yayınlamıyor, ekran başka yerden besleniyor.
 *
 * Kalan 11'i token bile çarpmıyordu: derlenmiş CSS'te `rounded` sabit
 * `.25rem`, `rounded-full` sabit bir sayı olarak çıkıyordu. İkisi de
 * token'lı karşılıklarına taşındı.
 *
 * Taşıma sırasında körlemesine yeniden adlandırma iki gerçek şeyi bozdu ve
 * ikisi de geri alındı: bir QR tema DEĞERİ (`'rounded'`) ve Flowbite'ın
 * Avatar bileşenindeki bir JSX PROP'u (`rounded`). İkisi de CSS sınıfı
 * değildi. Bu yüzden aşağıdaki kontrol yalnız tartışmasız olanı — sabit
 * değerli `rounded-full` sınıfını — arar; çıplak `rounded` sözcüğü
 * mekanik olarak sınıf mı veri mi ayırt edilemez.
 */

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CSS = path.resolve(ROOT, '../css/app.css');

function sourceFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir)) {
        const full = path.join(dir, entry);

        if (statSync(full).isDirectory()) {
            found.push(...sourceFiles(full));

            continue;
        }

        if (
            (entry.endsWith('.tsx') || entry.endsWith('.ts')) &&
            !entry.includes('.test.') &&
            !entry.includes('.stories.')
        ) {
            found.push(full);
        }
    }

    return found;
}

describe('DS-RADIUS-ROOT — yarıçap token kökünde', () => {
    it('dört rol yayınlanır ve üçü atomik gridden türer', () => {
        const css = readFileSync(CSS, 'utf8');

        // `--radius-*` Tailwind'in isim uzayıdır: `rounded-lg` bunu çarpar.
        // Kendi adımızı verseydik token yayınlanır, utility'ler yine
        // Tailwind varsayılanından gelirdi.
        expect(css).toMatch(/--radius-sm:\s*var\(--spacing\)/);
        expect(css).toMatch(/--radius-md:\s*calc\(var\(--spacing\) \* 1\.5\)/);
        expect(css).toMatch(/--radius-lg:\s*calc\(var\(--spacing\) \* 2\)/);

        // Hap biçimi gridle ilişkili değil: yarıçap değil, biçim kararı.
        expect(css).toMatch(/--radius-pill:/);
    });

    it('sabit değerli hap sınıfı kullanılmaz', () => {
        const offenders: string[] = [];

        for (const file of sourceFiles(ROOT)) {
            if (/\brounded-full\b/.test(readFileSync(file, 'utf8'))) {
                offenders.push(path.relative(ROOT, file));
            }
        }

        expect(
            offenders,
            "DS-RADIUS-ROOT-02: `rounded-full` derlenmiş CSS'te sabit bir sayıya çözülür ve " +
                'token kökünden geçmez. `rounded-pill` kullanın.',
        ).toEqual([]);
    });
});
