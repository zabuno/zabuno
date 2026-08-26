import { describe, expect, it } from 'vitest';
import { LOCALES, FALLBACK_LOCALE, currentLocale, directionOf, isLocaleCode } from './locales';
import { coverageOf, createTranslator } from './translator';
import { menuTr } from './catalogs/menu.tr';

/**
 * i18n kapısı — CORE-08 (`docs/26` S1-WP03, `docs/37` Dalga 5).
 *
 * Stage 1 kapsamı **scaffold + wiring**: altı locale tanımlı ve çözülebilir
 * olmalı, `en` eksiksiz olmalı, eksik çeviri `en`'e düşmeli. tr/de/fr/ar/ru
 * içerik tamlığı Stage 2'ye bırakılmıştır — bu yüzden bu kapı çeviri
 * EKSİKLİĞİNİ hata saymaz; ölçer.
 *
 * Requirement ID'leri: DS-I18N-SIX-CATALOGS-01, DS-I18N-FALLBACK-02,
 * DS-I18N-RTL-03, DS-I18N-OVERRIDE-KEYS-04.
 */

const base = {
    greeting: 'Hello',
    withVar: 'Hello {name}',
} as const;

describe('i18n kapısı', () => {
    // --- DS-I18N-SIX-CATALOGS-01 ------------------------------------------
    it('altı katalog tanımlıdır ve yalnız biri complete sayılır', () => {
        const codes = Object.keys(LOCALES);

        expect(codes.sort(), 'DS-I18N-SIX-CATALOGS-01: CORE-08 altı katalog şart koşar.').toEqual(
            ['ar', 'de', 'en', 'fr', 'ru', 'tr'].sort(),
        );

        const complete = codes.filter(
            (code) => LOCALES[code as keyof typeof LOCALES].status === 'complete',
        );

        expect(
            complete,
            "DS-I18N-SIX-CATALOGS-01: Stage 1'de yalnız `en` complete'tir; " +
                'bir dili complete ilan etmek içerik kanıtı ister.',
        ).toEqual(['en']);
    });

    // --- DS-I18N-FALLBACK-02 ----------------------------------------------
    it('eksik çeviri sessizce tabana düşer, arayüz boş kalmaz', () => {
        const t = createTranslator(base, { tr: { greeting: 'Merhaba' } });

        // jsdom `<html lang>` varsayılanı boştur → taban locale.
        expect(currentLocale(), 'DS-I18N-FALLBACK-02: tanınmayan lang tabana düşmeli.').toBe(
            FALLBACK_LOCALE,
        );

        document.documentElement.lang = 'tr';
        expect(t('greeting'), 'DS-I18N-FALLBACK-02: çeviri varsa kullanılmalı.').toBe('Merhaba');
        expect(
            t('withVar', { name: 'Ada' }),
            'DS-I18N-FALLBACK-02: çevirisi olmayan anahtar tabana düşmeli.',
        ).toBe('Hello Ada');

        document.documentElement.lang = 'de-DE';
        expect(
            t('greeting'),
            'DS-I18N-FALLBACK-02: bölgeli etiket taban dile indirgenmeli, scaffold dil tabana düşmeli.',
        ).toBe('Hello');

        document.documentElement.lang = 'xx';
        expect(t('greeting'), 'DS-I18N-FALLBACK-02: tanınmayan locale tabana düşmeli.').toBe(
            'Hello',
        );

        document.documentElement.lang = '';
    });

    // --- DS-I18N-RTL-03 ---------------------------------------------------
    it('yön locale özelliğidir ve Arapça RTL işaretlidir', () => {
        expect(directionOf('ar'), 'DS-I18N-RTL-03: Arapça RTL olmalı.').toBe('rtl');

        for (const code of ['en', 'tr', 'de', 'fr', 'ru'] as const) {
            expect(directionOf(code), `DS-I18N-RTL-03: ${code} LTR olmalı.`).toBe('ltr');
        }

        expect(isLocaleCode('ar')).toBe(true);
        expect(isLocaleCode('zz')).toBe(false);
    });

    // --- DS-I18N-OVERRIDE-KEYS-04 -----------------------------------------
    // Ölü anahtar, çeviri dosyalarının en sessiz çürüme biçimidir: kimse
    // fark etmeden birikir ve sonra hangisinin canlı olduğu bilinemez.
    it('menü çevirisi tabanda olmayan anahtar taşımaz ve kapsamı ölçülür', async () => {
        const menuModule = await import('./menu');
        const enKeys = new Set(Object.keys(menuModule).includes('t') ? Object.keys(menuTr) : []);

        expect(enKeys.size, 'DS-I18N-OVERRIDE-KEYS-04: Türkçe menü kataloğu boş.').toBeGreaterThan(
            0,
        );

        const coverage = coverageOf(menuTr as Record<string, string>, { tr: menuTr });

        expect(coverage.tr.translated, 'DS-I18N-OVERRIDE-KEYS-04: kapsam ölçümü çalışmıyor.').toBe(
            coverage.tr.total,
        );
    });
});
