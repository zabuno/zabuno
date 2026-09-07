import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * SAĞDAN SOLA HİKÂYELER GERÇEKTEN SAĞDAN SOLA MI? (`docs/132` §2)
 *
 * 2026-09-07'de ölçüldü: katalogdaki 33 `RightToLeft` hikâyesinin 32'si
 * yönünü `parameters: { direction: 'rtl' }` ile bildiriyordu ve
 * `withDirection` decorator'ü yönü `context.globals`'tan okuyor. Yani o 32
 * hikâye, adında "sağdan sola" yazan ve gerçek Chrome'da SOLDAN SAĞA
 * çizilen hikâyelerdi (`macro-layout-pageheader--right-to-left` → sarmalayıcı
 * `dir="ltr"`).
 *
 * Bu bir yazım hatası değil, bir ÖLÇÜM YOKLUĞUYDU: `scripts/mobile-ux-audit`
 * o hikâyeleri 320 pikselde ölçüyor ve "RTL 320'de temiz" diye
 * raporlanabilecek her sonuç, aslında LTR'nin ikinci kez ölçülmesiydi.
 *
 * Bu test tuzağın geri gelmesini engeller. jsdom'da koşar ve düzen
 * ÖLÇMEZ — ölçtüğü tek şey, hikâyenin yönü decorator'ün OKUDUĞU yerden
 * bildirip bildirmediği. Düzenin gerçekten kırılıp kırılmadığını ölçen şey
 * `node scripts/mobile-ux-audit <dizin> --direction rtl`'dir.
 */
const CATALOG_ROOT = join(import.meta.dirname, '../components');

function storyFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            found.push(...storyFiles(path));
        } else if (entry.name.endsWith('.stories.tsx')) {
            found.push(path);
        }
    }

    return found;
}

describe('hikâye yönü decorator’ün okuduğu yerden bildirilir', () => {
    const files = storyFiles(CATALOG_ROOT);

    it('hikâye dosyası bulunur — arama deseni sessizce boşa düşmez', () => {
        expect(files.length).toBeGreaterThan(50);
    });

    it('hiçbir hikâye yönü `parameters` ile bildirmez', () => {
        const offenders = files
            .filter((file) => /parameters:\s*\{[^}]*\bdirection\b/.test(readFileSync(file, 'utf8')))
            .map((file) => file.replace(`${CATALOG_ROOT}/`, ''));

        expect(offenders).toEqual([]);
    });

    it('adında sağdan sola geçen her hikâye yönü `globals` ile bildirir', () => {
        const silent: string[] = [];

        for (const file of files) {
            const source = readFileSync(file, 'utf8');

            if (!/export const RightToLeft\b/.test(source)) {
                continue;
            }

            /*
                Bildirim, hikâyenin KENDİ gövdesinde olmalı. Dosyanın herhangi
                bir yerinde `globals` geçmesi yetmez: başka bir hikâyenin
                bildirimi, adı RTL olan bir hikâyeyi RTL yapmaz.
            */
            const body = source.slice(source.indexOf('export const RightToLeft'));
            const end = body.indexOf('\n};');
            const story = end === -1 ? body : body.slice(0, end);

            if (!/globals:\s*\{[^}]*direction:\s*'rtl'/.test(story)) {
                silent.push(file.replace(`${CATALOG_ROOT}/`, ''));
            }
        }

        expect(silent).toEqual([]);
    });

    it('en az otuz hikâye sağdan sola ölçülüyor', () => {
        const declared = files.filter((file) =>
            /globals:\s*\{[^}]*direction:\s*'rtl'/.test(readFileSync(file, 'utf8')),
        );

        // Sayı bir hedef değil, bir kör nokta alarmı: bildirim toplu bir
        // düzenlemede sessizce silinirse burası söyler.
        expect(declared.length).toBeGreaterThanOrEqual(30);
    });
});
