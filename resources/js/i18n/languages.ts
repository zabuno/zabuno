/**
 * Dokuz dilin istemci tarafındaki kaydı — `docs/120` §2 ve §6.
 *
 * ═══ BU DOSYA `locales.ts` DEĞİLDİR ═══
 *
 * `locales.ts` altı DERLENMİŞ KATALOĞU sayar: "bu dilde bir çeviri tablomuz
 * var mı" (CORE-08). Bu dosya dokuz DİLİ tarif eder: "bu dili adıyla, yönüyle
 * ve yazısıyla gösterebiliyor muyuz". İkisi ayrı sorudur ve tek listeye
 * indirmek, ikisinden birini yalan söylemeye zorlardı (`docs/120` §1).
 *
 * Hiçbiri "bu dil SUNULUYOR" demez — o soruya `i18n.shipped_locales` cevap
 * verir ve bugün cevabı yalnız `en`.
 *
 * ═══ NEDEN SUNUCUDAKİ KÜTÜĞÜN AYNISI ═══
 *
 * Kaynak `app/Support/Localization/Language.php`. Buradaki kopya bir
 * PROJEKSİYONDUR ve `NineLanguageRegistryTest` ikisinin ayrışmasını
 * imkânsız kılar. Ayrışsalardı iki gerçek doğardı: sunucunun tanıdığı
 * diller ile ekranda görünen diller — ve kullanıcı, sunucunun hiç tanımadığı
 * bir dile tıklardı.
 */

export type LanguageDirection = 'ltr' | 'rtl';

export type LanguageRecord = {
    /** ISO 639-1 kodu. */
    code: string;
    /**
     * Dilin KENDİ DİLİNDEKİ adı — asla çevrilmez, asla katalogdan gelmez.
     *
     * Sahibin gerekçesi: "yabancı dil bilmeyen Türk, kendi dilini kendi
     * dilinde okuyabilsin." Bir kullanıcı arayüzü ANLAMADIĞI için dil
     * değiştirmeye gelir; dil adını anlamadığı dilde göstermek aracın
     * kendisini bozar.
     */
    endonym: string;
    direction: LanguageDirection;
    /** ISO 15924 yazı sistemi. */
    script: 'Latn' | 'Arab' | 'Cyrl';
    /**
     * Endonimin yanındaki iki harfli işaret.
     *
     * Ülkesi olan dil için ülke kodu, olmayan için dilin kendi kodu — nötr
     * bölge işareti. `docs/120` §6: `ar` yirmiden fazla ülkenin dilidir,
     * `ku` için devlet bayrağı yoktur ve kullanılan işaretler siyasi iddia
     * taşır, `en` için "Birleşik Krallık mı ABD mi" sorusunun doğru cevabı
     * yoktur.
     *
     * İşaret METİNDİR, emoji değil: emoji bu üründe yasak, ayrıca bayrak
     * emojisi her işletim sisteminde başka çizilir ve kiminde hiç çizilmez.
     */
    regionMark: string;
    /** Bölge işareti gerçek bir ülkeyi mi gösteriyor. */
    hasCountryFlag: boolean;
};

export const LANGUAGES: Record<string, LanguageRecord> = {
    en: {
        code: 'en',
        endonym: 'English',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'EN',
        hasCountryFlag: false,
    },
    tr: {
        code: 'tr',
        endonym: 'Türkçe',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'TR',
        hasCountryFlag: true,
    },
    ar: {
        code: 'ar',
        endonym: 'العربية',
        direction: 'rtl',
        script: 'Arab',
        regionMark: 'AR',
        hasCountryFlag: false,
    },
    ru: {
        code: 'ru',
        endonym: 'Русский',
        direction: 'ltr',
        script: 'Cyrl',
        regionMark: 'RU',
        hasCountryFlag: true,
    },
    fa: {
        code: 'fa',
        endonym: 'فارسی',
        direction: 'rtl',
        script: 'Arab',
        regionMark: 'IR',
        hasCountryFlag: true,
    },
    ku: {
        /*
            Kurmancî — Latin yazı, soldan sağa. Soranî (`ckb`) Arap yazısıyla
            ve sağdan sola yazılır; ayrı bir dildir ve gerekirse ayrı eklenir
            (`docs/120` §8). İkisini tek koda sıkıştırmak, birini yanlış yazı
            sistemiyle göstermek olurdu.
        */
        code: 'ku',
        endonym: 'Kurdî',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'KU',
        hasCountryFlag: false,
    },
    de: {
        code: 'de',
        endonym: 'Deutsch',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'DE',
        hasCountryFlag: true,
    },
    fr: {
        code: 'fr',
        endonym: 'Français',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'FR',
        hasCountryFlag: true,
    },
    it: {
        code: 'it',
        endonym: 'Italiano',
        direction: 'ltr',
        script: 'Latn',
        regionMark: 'IT',
        hasCountryFlag: true,
    },
};

/** Kütükteki dil kodları, `docs/120` §2 tablosundaki sırayla. */
export const LANGUAGE_CODES = Object.keys(LANGUAGES);

export function languageOf(code: string): LanguageRecord | undefined {
    return LANGUAGES[code];
}
