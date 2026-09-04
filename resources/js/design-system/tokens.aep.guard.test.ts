import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';

import { APP_CSS_PATH, themeScope } from './cssSources';

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
const CSS_PATH = APP_CSS_PATH;

function scopeOf(selector: ':root' | '.dark'): Record<string, string> {
    return themeScope(selector, readCustomProperties);
}

function luminanceOf(token: string, scope: Record<string, string>, over: string): number {
    const resolved = resolveColorWithAlpha(scope[token] ?? '', scope);
    const background = resolveColorWithAlpha(scope[over] ?? '', scope);

    expect(resolved, `DS-AEP-INK-11: ${token} çözülemedi.`).not.toBeNull();
    expect(background, `DS-AEP-INK-11: ${over} çözülemedi.`).not.toBeNull();

    const rgb = compositeOver(resolved!, background!.rgb);

    return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
}

/**
 * Jeton artık ÇÖZÜLEREK ölçülür (FF-131).
 *
 * Değerler `resources/css/aep/` içinde yaşıyor ve `app.css` onların üstüne
 * `var(--aep-*)` takma adları koyuyor. Bu testler literal metni donduruyordu
 * ve geçiş sonrası `#f7f7fb` yerine `var(--aep-surface-canvas)` gördüler —
 * ürün doğru çalışırken kırmızıya döndüler.
 *
 * Donması gereken şey zaten metin değil DEĞERDİ: zincir ne kadar uzarsa
 * uzasın, `--canvas` sonunda aynı renge çıkmalı.
 */
function hexOf(token: string, scope: Record<string, string>): string {
    const resolved = resolveColorWithAlpha(scope[token] ?? '', scope);

    expect(resolved, `DS-AEP-INK-11: ${token} çözülemedi.`).not.toBeNull();

    const channel = (value: number) =>
        Math.round(linearToSrgbByte(value)).toString(16).padStart(2, '0');

    return `#${channel(resolved!.rgb[0])}${channel(resolved!.rgb[1])}${channel(resolved!.rgb[2])}`;
}

function linearToSrgbByte(channel: number): number {
    const srgb = channel <= 0.0031308 ? channel * 12.92 : 1.055 * channel ** (1 / 2.4) - 0.055;

    return srgb * 255;
}

describe('AEP ink merdiveni', () => {
    it('açık temanın altı değeri dondurulmuştur', () => {
        const scope = scopeOf(':root');

        expect(hexOf('--canvas', scope)).toBe('#f7f7fb');
        expect(hexOf('--surface', scope)).toBe('#ffffff');
        expect(hexOf('--surface-subtle', scope)).toBe('#ededf4');
        expect(hexOf('--border', scope)).toBe('#e4e4ee');
        expect(hexOf('--fg', scope)).toBe('#080616');
        // Marka sarısı ve üstündeki koyu ton ÖLÇÜLMÜŞ bir çifttir (11.63:1);
        // merdiven değişirken bunlar değişmez.
        expect(hexOf('--color-brand-500', scope)).toBe('#ffb900');
        // AEP'in marka üstü mürekkebi `#080616` (ink-950); depo daha önce
        // `#1c1500` kullanıyordu. Teslim paketi kanonik olduğu için değer
        // pakete çevrildi — ikisi de sarı üstünde 11:1'in üstünde.
        expect(hexOf('--color-action-primary-fg', scope)).toBe('#080616');
    });

    it('koyu temanın dört değeri dondurulmuştur', () => {
        const scope = scopeOf('.dark');

        expect(hexOf('--canvas', scope)).toBe('#080616');
        expect(hexOf('--surface', scope)).toBe('#0d0a24');
        expect(hexOf('--surface-subtle', scope)).toBe('#16123a');
        expect(hexOf('--border', scope)).toBe('#26224a');
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

    /*
        ODAK HALKASI — AEP mavisi, sahibin kararıyla (FF-130).

        Bu testin önceki hâli tersini donduruyordu: halka kromasız kalacaktı
        ve gerekçesi `docs/71`deki "mavi OLAMAZ" kararıydı. Sahip 2026-09-04'te
        o kararı kendi sözüyle geri aldı ("zip dosyaları bu işin tanrısıdır"),
        yani burada donan şey değişti — ve testin AMACI değişmedi: halka
        rengi kazayla kaymasın.

        Ayrım korunuyor ve asıl kural budur: yasak olan METİN mavisiydi.
        Halka metin değil bir kenarlıktır; bu yüzden ölçüsü de metin eşiği
        değil METİN DIŞI 3:1'dir (WCAG 2.2 AA). İki temada da ölçülür,
        çünkü aynı mavi koyu zeminde 1.85:1 verir — AEP paketinin kendi
        uyarısı.
    */
    it('odak halkası AEP mavisidir ve iki temada da görülebilir', () => {
        const expected: Record<string, string> = { ':root': '#003399', '.dark': '#93a8f4' };

        for (const selector of [':root', '.dark'] as const) {
            const scope = scopeOf(selector);
            const focus = scope['--focus'] ?? '';

            expect(hexOf('--focus', scope), `DS-AEP-INK-11: ${selector} odak halkası.`).toBe(
                expected[selector],
            );

            const ring = resolveColorWithAlpha(focus, scope);
            const background = resolveColorWithAlpha(scope['--canvas'] ?? '', scope);
            const ratio = contrastRatio(compositeOver(ring!, background!.rgb), background!.rgb);

            expect(
                ratio,
                `DS-AEP-INK-11: ${selector} temada odak halkası zemine karşı ${ratio.toFixed(2)}:1 — görülemeyen bir halka, halka değildir.`,
            ).toBeGreaterThanOrEqual(WCAG_AA_LARGE_TEXT);
        }
    });
});
