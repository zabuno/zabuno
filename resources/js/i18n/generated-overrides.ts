/**
 * Üretilmiş çeviri projeksiyonlarının yükleyicisi — CORE-08.
 *
 * `resources/js/i18n/generated/*.json` dosyaları **elle düzenlenmez**; PO
 * kaynaklarından `node scripts/i18n build` ile üretilir. Bu dosya onları
 * alan adına göre gruplar, böylece her katalog kendi çevirisini tek satırla
 * alır ve hiçbir yerde dosya adı elle yazılmaz.
 *
 * Kaynak locale (`en`) burada YOKTUR: taban katalog zaten kodda olduğu için
 * aynı metni ikinci kez paketlemek boşuna yük olurdu.
 */
import { FALLBACK_LOCALE, isLocaleCode, type LocaleCode } from './locales';

const projections = import.meta.glob<Record<string, string>>('./generated/*.json', {
    eager: true,
    import: 'default',
});

const byDomain = new Map<string, Partial<Record<LocaleCode, Record<string, string>>>>();

for (const [filePath, table] of Object.entries(projections)) {
    const match = /\.\/generated\/(.+)\.([a-z]{2})\.json$/.exec(filePath);

    if (match === null) {
        throw new Error(
            `Unexpected translation projection file name "${filePath}"; expected "<domain>.<locale>.json".`,
        );
    }

    const [, domain, locale] = match;

    if (!isLocaleCode(locale) || locale === FALLBACK_LOCALE) {
        throw new Error(
            `Translation projection "${filePath}" targets an unknown or source locale "${locale}".`,
        );
    }

    const existing = byDomain.get(domain) ?? {};
    existing[locale] = table;
    byDomain.set(domain, existing);
}

/** Bir alan adı için üretilmiş çevirileri döner; yoksa boş tablo. */
export function overridesFor(domain: string): Partial<Record<LocaleCode, Record<string, string>>> {
    return byDomain.get(domain) ?? {};
}
