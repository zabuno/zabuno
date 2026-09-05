import clsx from 'clsx';

import { LANGUAGES, type LanguageRecord } from '../../../../i18n/languages';

export type LanguageSwitcherOption = {
    /** `LANGUAGES` kütüğündeki dil kodu. Kütükte olmayan kod ÇİZİLMEZ. */
    code: string;
    /**
     * Bu dilin adresi. `null` ise dil seçilemez ve sebebi `unavailableReason`
     * ile söylenir — bağlantısız bir satır, kırık bir bağlantıdan iyidir.
     */
    href: string | null;
    /**
     * Neden seçilemediği. Sunucu iki durumu ayırır ve bileşen o ayrımı
     * korur: dil henüz sunulmuyor (`not-offered`) ile bu sayfanın karşılığı
     * yok (`no-counterpart`) kullanıcı için iki farklı cümledir.
     */
    unavailableReason?: 'not-offered' | 'no-counterpart';
};

export type LanguageSwitcherProps = {
    /** Gezinme bölgesinin erişilebilir adı. Katalogdan gelir, kodda gömülü değil. */
    label: string;
    options: LanguageSwitcherOption[];
    currentCode: string;
    /** Aktif dilin METİN işareti — renk tek başına bir gösterge değildir. */
    currentLabel: string;
    /**
     * Seçilemeyen dillerin sebebine karşılık gelen metinler.
     *
     * `docs/120` §5.8'in iki yolundan ikincisi: dil ya HİÇ gösterilmez ya da
     * açıkça "henüz hazır değil" der. Bu metinler verilmezse bileşen birinci
     * yolu seçer ve o dilleri listeden düşürür — seçilebilir görünüp yarım
     * çeviri vermek, kapatılmış bir kusurun geri gelmesi olurdu.
     */
    unavailableLabels?: {
        'not-offered': string;
        'no-counterpart': string;
    };
    className?: string;
};

/**
 * Dil değiştirici — `docs/120` §5'teki dokuz maddenin bileşen karşılığı.
 *
 * ═══ NEDEN GERÇEK BAĞLANTI, MENÜ DEĞİL ═══
 *
 * Her dil kendi adresine giden bir `<a href>`'tir. Betikle açılan bir menü,
 * betik yüklenmeden gelen kullanıcıyı DİLSİZ bırakırdı — ve dil değiştirici,
 * arayüzü anlamayan kullanıcının tek çıkış kapısıdır. Bir arama motoru da
 * o menünün içindeki dilleri hiç görmez.
 *
 * Bu yüzden bileşen hiçbir durum tutmaz, hiçbir olay dinlemez ve JavaScript
 * kapalıyken tam olarak aynı işi yapar.
 *
 * ═══ NEDEN ENDONİM BİRİNCİL, BÖLGE İŞARETİ İKİNCİL ═══
 *
 * `docs/120` §6: bayrak tek başına dil anlatmaz. Tek başına bırakılsaydı
 * renk körü ya da küçük ekran kullanıcısı için hiçbir şey söylemezdi. Ayrıca
 * bölge işareti ekran okuyucudan gizlenir: "TR" harflerini duymak, "Türkçe"yi
 * duyduktan sonra bir bilgi eklemez.
 */
export function LanguageSwitcher({
    label,
    options,
    currentCode,
    currentLabel,
    unavailableLabels,
    className,
}: LanguageSwitcherProps) {
    const rows = options
        .map((option) => ({
            option,
            language: LANGUAGES[option.code] as LanguageRecord | undefined,
        }))
        .filter(
            (row): row is { option: LanguageSwitcherOption; language: LanguageRecord } =>
                row.language !== undefined,
        )
        /*
            SUNULMAYAN DİL YA GÖSTERİLMEZ YA "HENÜZ HAZIR DEĞİL" DER.

            Metin verilmediyse birinci yol seçilir. Üçüncü bir yol —
            seçilebilir görünüp yarım çeviri vermek — yok, çünkü kullanıcı
            o durumda ürünün bozuk olduğunu düşünür.
        */
        .filter((row) => row.option.href !== null || unavailableLabels !== undefined);

    return (
        <nav aria-label={label} className={className}>
            <ul className="flex flex-col gap-[var(--space-2)]">
                {rows.map(({ option, language }) => {
                    const isCurrent = option.code === currentCode;

                    /*
                        Satırın kendi `lang` ve `dir`i var. `lang` olmadan ekran
                        okuyucu "العربية"yi İngilizce telaffuz etmeye çalışır;
                        `dir` olmadan sağdan sola bir endonim, soldan sağa bir
                        listenin içinde ters sırada dizilir.
                    */
                    const shared = clsx(
                        'flex w-full items-center gap-[var(--space-3)]',
                        'rounded-[var(--radius-md)] px-[var(--space-3)] py-[var(--space-2)]',
                        // Dokunma hedefi dar ekranda da küçülmez: parmak imleç değildir.
                        'min-h-[var(--density-hit-area-min)] text-body',
                        'border-s-2 border-transparent',
                    );

                    const content = (
                        <>
                            <span className="min-w-0 grow">{language.endonym}</span>
                            {/*
                                Aktif dil METİNLE de işaretlenir. Yalnız renkle
                                işaretlemek, renk körü kullanıcı için hiçbir şey
                                söylemez ve yüksek kontrast temasında kaybolur.
                            */}
                            {isCurrent ? (
                                <span className="shrink-0 text-fg-secondary">{currentLabel}</span>
                            ) : null}
                            <span aria-hidden="true" className="shrink-0 text-fg-secondary">
                                {language.regionMark}
                            </span>
                        </>
                    );

                    if (option.href === null) {
                        const reason = option.unavailableReason ?? 'not-offered';

                        return (
                            <li key={option.code}>
                                <span
                                    lang={option.code}
                                    dir={language.direction}
                                    className={clsx(shared, 'flex-wrap text-fg-secondary')}
                                >
                                    {content}
                                    <span className="w-full text-fg-secondary">
                                        {unavailableLabels?.[reason]}
                                    </span>
                                </span>
                            </li>
                        );
                    }

                    return (
                        <li key={option.code}>
                            <a
                                href={option.href}
                                hrefLang={option.code}
                                lang={option.code}
                                dir={language.direction}
                                aria-current={isCurrent ? 'true' : undefined}
                                className={clsx(
                                    shared,
                                    'font-medium',
                                    'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                                    'text-fg-secondary hover:bg-surface-hover hover:text-fg',
                                    'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                                    isCurrent &&
                                        'border-s-brand bg-surface-active font-bold text-fg',
                                )}
                            >
                                {content}
                            </a>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
