import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { readdirSync, statSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

/**
 * ODAK GÖSTERGESİ SÖZLEŞMESİ — `docs/71`.
 *
 * Sahibin şikâyeti kesin: girdinin, kartın ya da düğmenin etrafında MAVİ bir
 * çizgi olmayacak — ne sekme tuşuyla gezilirken ne de fareyle tıklanınca.
 *
 * Bu dosya üç şeyi donduruyor:
 *
 * 1. Odak rengi SABİT KODLANMAZ; jetondan gelir. `app.css` bugün
 *    `#2563eb` yazıyor — yani jeton sisteminin dışına çıkmış bir mavi.
 * 2. Odak jetonu MAVİ tonunda değildir.
 * 3. Hiçbir yerde Tailwind/Flowbite `focus:ring-*` sınıfı kullanılmaz;
 *    o sınıflar tarayıcıdan bağımsız olarak mavi bir halka basar.
 */
const CSS_PATH = resolve(process.cwd(), 'resources/css/app.css');

/**
 * Yorumlar ÖNCE atılır.
 *
 * İlk hâlde atılmıyordu ve testin kendisi yanlış alarm verdi: bir kuralın
 * üstündeki açıklama metni eşleşmenin içine giriyordu. Bir testin ölçtüğü
 * şeyi tam olarak ölçmesi, ölçtüğünü sanmasından önemlidir.
 */
function stripComments(source: string): string {
    return source.replace(/\/\*[\s\S]*?\*\//g, '');
}

const css = stripComments(readFileSync(CSS_PATH, 'utf8'));

function collectSources(dir: string, out: string[] = []): string[] {
    for (const entry of readdirSync(dir)) {
        if (entry === 'node_modules' || entry === '.flowbite-react') continue;

        const full = resolve(dir, entry);

        if (statSync(full).isDirectory()) {
            collectSources(full, out);
            continue;
        }

        if (/\.(ts|tsx)$/.test(entry) && !/\.test\.tsx?$/.test(entry)) {
            out.push(full);
        }
    }

    return out;
}

describe('odak göstergesi sözleşmesi', () => {
    /**
     * Sabit kodlanmış bir renk, tema jetonlarının dışına çıkar: karanlık
     * temada, yüksek kontrastta ve marka rengi değiştiğinde birlikte
     * değişmez.
     */
    it('odak rengi sabit kodlanmaz, jetondan gelir', () => {
        const focusVisibleRules = css.match(/:focus-visible\s*\{[^}]*\}/g) ?? [];

        expect(focusVisibleRules.length).toBeGreaterThan(0);

        for (const rule of focusVisibleRules) {
            expect(rule, `sabit renk: ${rule}`).not.toMatch(/#[0-9a-f]{3,8}\b/i);
            expect(rule, `sabit renk: ${rule}`).not.toMatch(/\b(rgb|hsl|oklch)\(/);
        }
    });

    /**
     * Jeton MAVİ olmamalı. oklch'de mavi ton açısı yaklaşık 240–290
     * aralığındadır; sahibin şikâyet ettiği renk tam oradaydı (255).
     */
    it('odak jetonu mavi tonunda değildir', () => {
        const declarations = css.match(/--focus:\s*([^;]+);/g) ?? [];

        expect(declarations.length).toBeGreaterThan(0);

        for (const declaration of declarations) {
            const oklch = declaration.match(/oklch\(\s*[\d.]+\s+[\d.]+\s+([\d.]+)/);

            if (oklch === null) {
                // `Highlight` gibi sistem renkleri yüksek kontrast modunun
                // kendi paletidir; oraya karışılmaz.
                continue;
            }

            const hue = Number(oklch[1]);
            expect(hue >= 240 && hue <= 290, `mavi ton: ${declaration}`).toBe(false);
        }
    });

    /**
     * `focus:ring-*` Tailwind'in kutu gölgesiyle çizdiği halkadır ve
     * Flowbite varsayılanı onu mavi basar. Halka, `outline`'dan farklı
     * olarak öğenin dışına taşar ve komşu kontrollerin üstüne biner.
     */
    it('hiçbir kaynakta focus:ring sınıfı kullanılmaz', () => {
        const offenders: string[] = [];

        for (const file of collectSources(resolve(process.cwd(), 'resources/js'))) {
            const source = readFileSync(file, 'utf8');

            if (/(^|[\s'"`])(dark:)?focus(-visible)?:ring-/.test(source)) {
                offenders.push(file.replace(process.cwd(), ''));
            }
        }

        expect(offenders).toEqual([]);
    });

    /**
     * Tailwind/Flowbite HALKASI hiçbir odak durumunda çizilemez.
     *
     * Bu bir temizlik değil bir GARANTİ: Flowbite'ın jetona bağlanmamış
     * aileleri kendi mavi halka sınıflarını taşımaya devam ediyor. Hangi
     * bileşenin bağlandığını tek tek takip etmek, yeni bir Flowbite bileşeni
     * eklendiği gün mavinin geri gelmesi demekti.
     */
    it('halka değişkenleri her odak durumunda sıfırlanır', () => {
        const rule = css.match(/\*:focus,\s*\*:focus-visible,\s*\*:focus-within\s*\{[^}]*\}/);

        expect(rule, 'halka sıfırlama kuralı yok').not.toBeNull();
        expect(rule?.[0]).toMatch(/--tw-ring-shadow:\s*0 0 #0000/);
        expect(rule?.[0]).toMatch(/--tw-ring-offset-shadow:\s*0 0 #0000/);
    });

    /**
     * FARE TIKLAMASI HALKA BIRAKMAZ.
     *
     * Bunun tek doğru yolu `:focus-visible`'dır: tarayıcı, odağın klavyeden
     * mi işaretçiden mi geldiğini bilir. Çıplak `:focus` seçicisiyle stil
     * vermek, tıklanan her düğmeyi çerçeveler.
     */
    it('çıplak :focus seçicisine görünür bir gösterge bağlanmaz', () => {
        const bareFocusRules =
            css.match(/(^|[\s,{}])[^{},]*:focus(?!-visible)[^{},]*\{[^}]*\}/g) ?? [];

        expect(bareFocusRules.length).toBeGreaterThan(0);

        for (const rule of bareFocusRules) {
            /*
                DEĞERİ okunur, varlığı değil. İlk hâli `outline\s*:\s*(?!none)`
                kullanıyordu; `\s*` geri izleyip sıfır boşluk eşleşince
                lookahead boşluk karakterinde değerlendiriliyor ve `outline:
                none` bile "görünür gösterge" sayılıyordu. Testin kendi
                kusuru, ölçtüğü kusurdan önce düzeltildi.
            */
            for (const [, property, value] of rule.matchAll(
                /(outline|box-shadow)\s*:\s*([^;}]+)/g,
            )) {
                expect(value.trim(), `çıplak :focus ${property}: ${rule}`).toBe('none');
            }
        }
    });
});
