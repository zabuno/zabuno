import { describe, expect, it } from 'vitest';

import { pseudoLocalize, pseudoLocalizationEnabled } from './pseudo';
import { LANGUAGES } from './languages';

/**
 * `docs/121` §4 — ölçüm dilinin istemci ucu, ve `docs/120` §2 kütüğü.
 *
 * Requirement ID'leri: DS-PSEUDO-TRANSFORM-01, DS-PSEUDO-PLACEHOLDER-02,
 * DS-PSEUDO-OFF-03, DS-LANG-REGISTRY-04.
 */

describe('sahte-yerelleştirme — istemci', () => {
    // --- DS-PSEUDO-TRANSFORM-01 -------------------------------------------

    /**
     * Dönüşüm üç şeyi aynı anda görünür kılar: dönüşmeyen metin (kodda
     * gömülü), uzayan metin (düzeni kıran), ve ayraçlar (parça parça kurulan
     * cümle).
     */
    it('metni ayraçlar arasında ve uzatarak döndürür', () => {
        const output = pseudoLocalize('Save changes');

        expect(output.startsWith('⟦'), 'DS-PSEUDO-TRANSFORM-01: baş ayraç yok.').toBe(true);
        expect(output.endsWith('⟧'), 'DS-PSEUDO-TRANSFORM-01: son ayraç yok.').toBe(true);
        expect(output).toContain('Şåvê');
        expect(
            output.length,
            'DS-PSEUDO-TRANSFORM-01: metin uzamamış — dar ekranda kırılan yer görünmez.',
        ).toBeGreaterThan('Save changes'.length * 1.35);
    });

    // --- DS-PSEUDO-PLACEHOLDER-02 -----------------------------------------

    it('adlı yer tutucuya dokunmaz', () => {
        const output = pseudoLocalize('{count} items in {menu}');

        expect(output).toContain('{count}');
        expect(output).toContain('{menu}');
    });

    it('boş dize boş kalır', () => {
        // Boş dize çevrilecek bir metin değil, bir arayüz durumudur; ayraç
        // eklenseydi ekranda anlamsız bir `⟦⟧` belirirdi.
        expect(pseudoLocalize('')).toBe('');
    });

    // --- DS-PSEUDO-OFF-03 -------------------------------------------------

    /**
     * VARSAYILAN KAPALI.
     *
     * Kip yalnız derleme zamanı bayrağıyla açılır; çalışma anında
     * açılabilen bir anahtar olsaydı bir gün üretimde açık kalırdı.
     */
    it('bayrak verilmedikçe kapalıdır', () => {
        expect(
            pseudoLocalizationEnabled(),
            'DS-PSEUDO-OFF-03: ölçüm kipi varsayılan olarak açık.',
        ).toBe(false);
    });
});

describe('dokuz dilin istemci kütüğü', () => {
    // --- DS-LANG-REGISTRY-04 ----------------------------------------------

    it('dokuz dili taşır ve yalnız ikisi sağdan soladır', () => {
        expect(Object.keys(LANGUAGES).sort()).toEqual(
            ['ar', 'de', 'en', 'fa', 'fr', 'it', 'ku', 'ru', 'tr'].sort(),
        );

        const rtl = Object.values(LANGUAGES)
            .filter((language) => language.direction === 'rtl')
            .map((language) => language.code)
            .sort();

        expect(
            rtl,
            'DS-LANG-REGISTRY-04: `docs/120` §2 yalnız `ar` ve `fa` için RTL diyor.',
        ).toEqual(['ar', 'fa']);
    });

    /**
     * BÖLGE İŞARETİ EMOJİ DEĞİLDİR.
     *
     * Emoji bu üründe yasak; ayrıca bayrak emojisi her işletim sisteminde
     * başka çizilir ve kiminde hiç çizilmez. Metin her yerde aynı okunur.
     */
    it('her bölge işareti iki ASCII harftir', () => {
        for (const language of Object.values(LANGUAGES)) {
            expect(
                language.regionMark,
                `DS-LANG-REGISTRY-04: ${language.code} bölge işareti emoji ya da bozuk.`,
            ).toMatch(/^[A-Z]{2}$/);
        }
    });

    /**
     * `docs/120` §6 — bayrak istisnaları BİREBİR.
     *
     * Bu siyasi bir hassasiyettir, estetik tercih değil: yanlış bayrak
     * kullanıcıyı kimliği üzerinden yanlış yerleştirir.
     */
    it('ar, ku ve en bir ülkeyle eşleştirilmez', () => {
        for (const code of ['ar', 'ku', 'en']) {
            expect(
                LANGUAGES[code].hasCountryFlag,
                `DS-LANG-REGISTRY-04: ${code} bayrak taşıyor.`,
            ).toBe(false);
        }
    });
});
