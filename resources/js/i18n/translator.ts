import { FALLBACK_LOCALE, currentLocale, type LocaleCode } from './locales';

/**
 * Katalog çevirici fabrikası — CORE-08 wiring.
 *
 * Her katalog modülü İngilizce tabanını sahiplenir ve bu fabrikayı kullanır.
 * Tasarım kararı: taban katalog **tip kaynağıdır**, çeviriler ise kısmi
 * override'dır. Böylece bir dil eksik olduğunda derleme kırılmaz, kullanıcı
 * `en` metnini görür ve eksiklik ölçülebilir kalır — Stage 1 kapsamı
 * (scaffold) tam olarak budur.
 *
 * Override'lar tabanda olmayan bir anahtar TAŞIYAMAZ: tipler bunu engeller,
 * `DS-I18N-OVERRIDE-KEYS` de kaçanı yakalar. Aksi hâlde çeviri dosyaları
 * sessizce ölü anahtar biriktirir.
 */

export type Catalog = Record<string, string>;

export type Overrides<C extends Catalog> = Partial<
    Record<LocaleCode, Partial<Record<keyof C, string>>>
>;

/** `{name}` yer tutucularını doldurur. Eksik değişken yer tutucuyu bırakır. */
function interpolate(template: string, vars?: Record<string, string>): string {
    if (!vars) return template;

    return Object.entries(vars).reduce<string>(
        (result, [name, value]) => result.replaceAll(`{${name}}`, value),
        template,
    );
}

export function createTranslator<C extends Catalog>(base: C, overrides: Overrides<C> = {}) {
    return function t(key: keyof C, vars?: Record<string, string>): string {
        const locale = currentLocale();

        const translated = locale === FALLBACK_LOCALE ? undefined : overrides[locale]?.[key];

        // Anahtarın kendisi son çare: eksik bir anahtar boş bir arayüz yerine
        // görünür bir iz bırakmalı ki fark edilsin.
        const template = translated ?? base[key] ?? String(key);

        return interpolate(template, vars);
    };
}

/**
 * Bir katalogda hangi locale'lerin ne kadar tamamlandığını ölçer.
 * Kapsam raporlaması ve testler bunu kullanır; tahmin edilmez, sayılır.
 */
export function coverageOf<C extends Catalog>(
    base: C,
    overrides: Overrides<C>,
): Record<string, { translated: number; total: number }> {
    const total = Object.keys(base).length;

    return Object.fromEntries(
        Object.entries(overrides).map(([locale, table]) => [
            locale,
            { translated: Object.keys(table ?? {}).length, total },
        ]),
    );
}
