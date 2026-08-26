/**
 * Paranın tek biçimlendirme sahibi (frontend) — CORE-12, `docs/13` §4.
 *
 * Bu dosya var, çünkü aynı işi yapan dört ayrı kopya vardı ve hepsi kuruşu
 * sabit 100'e bölüyordu. Bu her para biriminde doğru değildir: Japon yeninde
 * ondalık yoktur (1499 minor = ¥1.499), Kuveyt dinarında üç basamak vardır
 * (1499 minor = 1,499 KWD). Sabit 100, kullanıcıya yüz kat yanlış bir tutar
 * göstermenin en sessiz yoludur.
 *
 * Bölen sabit değil, para biriminin KENDİ ondalık basamağından türetilir.
 */

/**
 * Platformun tanıdığı para birimleri. `Intl.NumberFormat` iyi biçimli ama
 * var olmayan bir kodu (örneğin `XYZ`) sessizce kabul eder ve "XYZ 14,99"
 * yazar — yani uydurulmuş bir tutarı gerçek gibi gösterir. Bu yüzden kodu
 * biçimine değil, platformun listesine soruyoruz.
 *
 * `supportedValuesOf` bulunmayan eski bir ortamda liste boş kalır ve
 * doğrulama biçim kontrolüne düşer; bu bilinen ve kaydedilmiş bir
 * sadeleşmedir.
 */
const KNOWN_CURRENCIES: ReadonlySet<string> | null =
    typeof Intl.supportedValuesOf === 'function'
        ? new Set(Intl.supportedValuesOf('currency'))
        : null;

function isKnownCurrency(currencyCode: string): boolean {
    const code = currencyCode.toUpperCase();

    if (KNOWN_CURRENCIES !== null) {
        return KNOWN_CURRENCIES.has(code);
    }

    return /^[A-Z]{3}$/.test(code);
}

/** Belgenin diline düşer; belge dili yoksa İngilizce. */
export function currentLocaleTag(): string {
    if (typeof document === 'undefined') {
        return 'en';
    }

    return document.documentElement.lang || 'en';
}

/**
 * Kuruş cinsinden tutarı okunabilir paraya çevirir.
 * Para birimi tanınmazsa `null` döner — uydurulmuş bir tutar göstermek,
 * hiç göstermemekten kötüdür.
 */
export function formatMoney(
    minorAmount: number,
    currencyCode: string,
    locale: string = currentLocaleTag(),
): string | null {
    if (!Number.isFinite(minorAmount) || !isKnownCurrency(currencyCode)) {
        return null;
    }

    let formatter: Intl.NumberFormat;

    try {
        formatter = new Intl.NumberFormat(locale, { style: 'currency', currency: currencyCode });
    } catch {
        return null;
    }

    const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2;

    return formatter.format(minorAmount / 10 ** digits);
}

/**
 * Biçimlendirilemeyen tutar için görünür bir yedek metin ister.
 * Çağıran, "fiyat yok" durumunu kendi diliyle anlatır; bu dosya metin
 * uydurmaz.
 */
export function formatMoneyOr(
    minorAmount: number,
    currencyCode: string,
    fallback: string,
    locale: string = currentLocaleTag(),
): string {
    return formatMoney(minorAmount, currencyCode, locale) ?? fallback;
}
