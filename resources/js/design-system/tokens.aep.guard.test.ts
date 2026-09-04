import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

import {
    compositeOver,
    contrastRatio,
    readCustomProperties,
    resolveColorWithAlpha,
    WCAG_AA_LARGE_TEXT,
    WCAG_AA_NORMAL_TEXT,
} from './contrast';

/**
 * DS-AEP-INK-11 — AEP ink merdiveninin dondurulmuş değerleri (FF-125).
 *
 * Merdivenin altı değeri bir tercih değil bir İLİŞKİDİR: zemin karttan koyu
 * olamaz (açık temada kart zeminden AÇIK, koyu temada zemin karttan KOYU) ve
 * kart içi soluk dolgu ikisinin arasında durur. Bu ilişki bir kez bozuldu ve
 * kimse fark etmedi (`docs/102` §5b: `@theme` takma adı kök değere donuyor,
 * `.dark` içinde yeniden tanımlanmazsa koyu temada zemin açık kalıyor).
 *
 * Bu yüzden test değerleri dondurmakla yetinmez, ARALARINDAKİ İLİŞKİYİ ölçer:
 * bir gün biri elle değişirse, hangi değerin yanlış olduğunu değil hangi
 * ilişkinin bozulduğunu söyler.
 */
const CSS_PATH = 'resources/css/app.css';

function scopeOf(selector: string): Record<string, string> {
    const css = readFileSync(CSS_PATH, 'utf8');

    return { ...readCustomProperties(css, ':root'), ...readCustomProperties(css, selector) };
}

function luminanceOf(token: string, scope: Record<string, string>, over: string): number {
    const resolved = resolveColorWithAlpha(scope[token] ?? '', scope);
    const background = resolveColorWithAlpha(scope[over] ?? '', scope);

    expect(resolved, `DS-AEP-INK-11: ${token} çözülemedi.`).not.toBeNull();
    expect(background, `DS-AEP-INK-11: ${over} çözülemedi.`).not.toBeNull();

    const rgb = compositeOver(resolved!, background!.rgb);

    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

describe('AEP ink merdiveni', () => {
    it('açık temanın altı değeri dondurulmuştur', () => {
        const scope = scopeOf(':root');

        expect(scope['--canvas']).toBe('#f7f7fb');
        expect(scope['--surface']).toBe('#ffffff');
        expect(scope['--surface-subtle']).toBe('#ededf4');
        expect(scope['--border']).toBe('#e4e4ee');
        expect(scope['--fg']).toBe('#080616');
        // Marka sarısı ve üstündeki koyu ton ÖLÇÜLMÜŞ bir çifttir (11.63:1);
        // merdiven değişirken bunlar değişmez.
        expect(scope['--color-brand-500']).toBe('#ffb900');
        expect(scope['--color-action-primary-fg']).toBe('#1c1500');
    });

    it('koyu temanın dört değeri dondurulmuştur', () => {
        const scope = scopeOf('.dark');

        expect(scope['--canvas']).toBe('#080616');
        expect(scope['--surface']).toBe('#0d0a24');
        expect(scope['--surface-subtle']).toBe('#16123a');
        expect(scope['--border']).toBe('#26224a');
    });

    it('açık temada kart zeminden AÇIK, kart içi dolgu ikisinin arasında', () => {
        const scope = scopeOf(':root');

        const surface = luminanceOf('--surface', scope, '--surface');
        const canvas = luminanceOf('--canvas', scope, '--surface');
        const subtle = luminanceOf('--surface-subtle', scope, '--surface');

        expect(surface).toBeGreaterThan(canvas);
        expect(canvas).toBeGreaterThan(subtle);
    });

    it('koyu temada zemin karttan KOYU — derinlik ters dönmez', () => {
        const scope = scopeOf('.dark');

        const surface = luminanceOf('--surface', scope, '--surface');
        const canvas = luminanceOf('--canvas', scope, '--surface');
        const subtle = luminanceOf('--surface-subtle', scope, '--surface');

        expect(canvas).toBeLessThan(surface);
        expect(surface).toBeLessThan(subtle);
    });

    it('kontrol kenarlığı iki temada da metin dışı 3:1 eşiğini geçer', () => {
        // Bir girdinin VAR OLDUĞUNU anlatan tek görsel ipucu o kenarlıksa,
        // görülemeyen bir kenarlık girdiyi de görünmez yapar (WCAG 2.2 AA).
        for (const selector of [':root', '.dark'] as const) {
            const scope = scopeOf(selector);
            const background = resolveColorWithAlpha(scope['--surface'] ?? '', scope);
            const border = resolveColorWithAlpha(scope['--border-control'] ?? '', scope);

            const ratio = contrastRatio(compositeOver(border!, background!.rgb), background!.rgb);

            expect(
                ratio,
                `DS-AEP-INK-11: ${selector} temada kontrol kenarlığı ${ratio.toFixed(2)}:1 — girdi görünmez.`,
            ).toBeGreaterThanOrEqual(WCAG_AA_LARGE_TEXT);
        }
    });

    /*
        METİN JETONU ZEMİNE KARŞI DA ÖLÇÜLÜR (FF-125, tarayıcıda bulundu).

        `design-system.guard.test.ts` mürekkebi yalnız `--surface` üstünde
        ölçüyordu ve `--fg-muted` orada 6.60:1 çıkıyordu. Storybook'ta
        AdminShell açıldığında aynı metnin gerçek zemini `--canvas`'tı ve
        ölçüm 4.52:1'e düştü — hâlâ AA, ama pay 0.02. Kartın DIŞINDA duran
        her yardımcı metin (boş durum, sayfa açıklaması, tablo altı not) bu
        zemindedir; zemin bir gün bir ton koyulaşırsa metin sessizce AA'nın
        altına iner ve kimse fark etmez.

        Bu yüzden ZOR OLAN durum donduruluyor: her metin jetonu hem kartta
        hem zeminde ölçülür.
    */
    it('her metin jetonu KART üstünde de ZEMİN üstünde de AA geçer', () => {
        for (const selector of [':root', '.dark'] as const) {
            const scope = scopeOf(selector);

            for (const token of [
                '--fg',
                '--fg-secondary',
                '--fg-muted',
                '--fg-subtle',
                '--fg-link',
                '--fg-danger',
                '--fg-success',
                '--fg-warning',
            ]) {
                for (const over of ['--surface', '--canvas'] as const) {
                    const ink = resolveColorWithAlpha(scope[token] ?? '', scope);
                    const background = resolveColorWithAlpha(scope[over] ?? '', scope);

                    expect(ink, `DS-AEP-INK-11: ${token} çözülemedi.`).not.toBeNull();
                    expect(background, `DS-AEP-INK-11: ${over} çözülemedi.`).not.toBeNull();

                    const ratio = contrastRatio(
                        compositeOver(ink!, background!.rgb),
                        background!.rgb,
                    );

                    expect(
                        ratio,
                        `DS-AEP-INK-11: ${selector} temada ${token}, ${over} üstünde ${ratio.toFixed(2)}:1.`,
                    ).toBeGreaterThanOrEqual(WCAG_AA_NORMAL_TEXT);
                }
            }
        }
    });

    it('16px taban: meta artık boyutla değil renkle ayrışır', () => {
        const css = readFileSync(CSS_PATH, 'utf8');

        expect(css).toMatch(/--text-body:\s*1rem/);
        expect(css).toMatch(/--text-meta:\s*1rem/);
    });

    it('odak halkası KROMASIZ kalır — AEP paleti bu kararı değiştirmez', () => {
        /*
            AEP teslim paketi odak halkasını parlamento mavisine (#003399)
            çeviriyor ve "halka metin değildir, yasak kapsamı dışında" diyor.
            BU DEPODA UYGULANMADI ve sebebi kayıtlı: `docs/71`, sahibin
            şikâyeti tam olarak maviydi ve alınan karar "şu an mavi değil"
            değil, "mavi OLAMAZ" idi. Bir jetonu tasarım paketi istedi diye
            geri çevirmek, o kararı sessizce iptal etmek olurdu.

            Sahip isterse tek satırlık bir değişiklik; ama o satır bilerek
            atılacak, kazayla değil.
        */
        for (const selector of [':root', '.dark'] as const) {
            const scope = scopeOf(selector);
            const focus = scope['--focus'] ?? '';

            expect(focus, `DS-AEP-INK-11: ${selector} odak jetonu tanımsız.`).not.toBe('');
            expect(focus).toMatch(/oklch\([\d.]+\s+0\s+0\)/);
        }
    });
});
