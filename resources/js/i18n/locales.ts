/**
 * Altı katalog kaydı — CORE-08 (`docs/26` S1-WP03).
 *
 * English remains the source and fallback. Turkish is the second complete
 * interface catalogue; the other registered catalogues remain scaffolds.
 * Public document translations are validated separately before promotion.
 *
 * Yön burada yaşar, bileşende değil: RTL bir locale özelliğidir ve
 * bileşenin bilmesi gereken bir şey değildir (docs/37 §2.2, X3).
 */

export const LOCALES = {
    en: { name: 'English', direction: 'ltr', status: 'complete' },
    tr: { name: 'Türkçe', direction: 'ltr', status: 'complete' },
    de: { name: 'Deutsch', direction: 'ltr', status: 'scaffold' },
    fr: { name: 'Français', direction: 'ltr', status: 'scaffold' },
    ar: { name: 'العربية', direction: 'rtl', status: 'scaffold' },
    ru: { name: 'Русский', direction: 'ltr', status: 'scaffold' },
} as const satisfies Record<
    string,
    { name: string; direction: 'ltr' | 'rtl'; status: 'complete' | 'scaffold' }
>;

export type LocaleCode = keyof typeof LOCALES;

export type Direction = (typeof LOCALES)[LocaleCode]['direction'];

/** Çeviri bulunamadığında düşülen locale. Tek kaynak; her yerde tekrar edilmez. */
export const FALLBACK_LOCALE: LocaleCode = 'en';

export function isLocaleCode(value: string): value is LocaleCode {
    return value in LOCALES;
}

export function directionOf(locale: LocaleCode): Direction {
    return LOCALES[locale].direction;
}

/**
 * Çalışma anındaki locale. Kaynağı `<html lang>`'dir — Blade tarafı onu
 * yazar, böylece sunucu ve istemci tek bir gerçeği paylaşır ve locale
 * seçimi JS'e gömülmez.
 *
 * `en-GB` gibi bölgeli etiketler taban dile indirgenir; tanınmayan her
 * değer sessizce `en`'e düşer, çünkü okunmayan bir arayüz hatadan kötüdür.
 */
export function currentLocale(): LocaleCode {
    if (typeof document === 'undefined') {
        return FALLBACK_LOCALE;
    }

    const declared = document.documentElement.lang?.trim().toLowerCase() ?? '';
    const base = declared.split('-')[0];

    return isLocaleCode(base) ? base : FALLBACK_LOCALE;
}
