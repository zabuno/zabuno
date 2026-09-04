/**
 * Üretilmiş çeviri projeksiyonlarının yükleyicisi — CORE-08.
 *
 * `resources/js/i18n/generated/*.json` dosyaları **elle düzenlenmez**; PO
 * kaynaklarından `node scripts/i18n build` ile üretilir. Bu dosya onları
 * alan adına göre gruplar, böylece her katalog kendi çevirisini tek satırla
 * alır ve hiçbir yerde dosya adı elle yazılmaz.
 *
 * Kaynak locale (`en`) burada YOKTUR: taban katalog zaten kodda olduğu için
 * aynı metini ikinci kez paketlemek boşuna yük olurdu.
 *
 * YÜKLEME İSTEĞE BAĞLIDIR (FF-94). Önceden altı locale'in tamamı ana pakete
 * gömülüyordu. Türkçe katalog tamamlanınca çalışma alanı paketi 207 KB'ye
 * çıktı ve 200 KB bütçesini aştı — üstelik indirilen ağırlığın büyük kısmı
 * hiçbir kullanıcının okumadığı dillerdi. Artık yalnız AÇIK OLAN dilin
 * tablosu, ayrı bir parça olarak indirilir; İngilizce okuyan hiçbir şey
 * indirmez.
 *
 * Tablolar sözlüğe YERİNDE yazılır. Kataloglar `overridesFor()` sonucunu
 * modül yüklenirken bir kez alır; yeni bir nesne döndürseydik yükleme
 * bittiğinde ellerindeki eski nesne boş kalırdı.
 */
import { FALLBACK_LOCALE, isLocaleCode, type LocaleCode } from './locales';

type DomainOverrides = Partial<Record<LocaleCode, Record<string, string>>>;

const projections = import.meta.glob<Record<string, string>>('./generated/*.json', {
    import: 'default',
});

const byDomain = new Map<string, DomainOverrides>();

function parseFileName(filePath: string): { domain: string; locale: LocaleCode } {
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

    return { domain, locale };
}

/**
 * Bir dilin bütün alan tablolarını indirir ve sözlüğe yazar.
 *
 * Kaynak dil için hiçbir şey indirilmez: taban katalog zaten kodda.
 * Uygulama ÇİZİLMEDEN ÖNCE beklenir; aksi hâlde kullanıcı önce İngilizce
 * bir ekran görür, sonra ekran altından Türkçeye döner.
 */
export async function loadLocaleOverrides(locale: LocaleCode): Promise<void> {
    if (locale === FALLBACK_LOCALE) {
        return;
    }

    await Promise.all(
        Object.entries(projections).map(async ([filePath, load]) => {
            const parsed = parseFileName(filePath);

            if (parsed.locale !== locale) {
                return;
            }

            const table = await load();
            const existing = byDomain.get(parsed.domain) ?? {};
            existing[parsed.locale] = table;
            byDomain.set(parsed.domain, existing);
        }),
    );
}

/**
 * Bir alan adı için çeviri sözlüğü. Aynı alan için HER ZAMAN aynı nesne
 * döner; yükleme sonradan bittiğinde katalogların elindeki referans dolar.
 */
export function overridesFor(domain: string): DomainOverrides {
    const existing = byDomain.get(domain);

    if (existing !== undefined) {
        return existing;
    }

    const created: DomainOverrides = {};
    byDomain.set(domain, created);

    return created;
}
