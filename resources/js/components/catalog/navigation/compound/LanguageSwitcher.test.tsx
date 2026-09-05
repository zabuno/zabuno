import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { LanguageSwitcher, type LanguageSwitcherOption } from './LanguageSwitcher';
import { LANGUAGES } from '../../../../i18n/languages';

/**
 * `docs/120` §5 — dil değiştiricinin davranış sözleşmesi, dokuz madde.
 *
 * Testler SONUCU ölçer, mekanizmayı değil: ekranda ne var, tıklanınca ne
 * olur, betik olmadan ne kalır.
 *
 * Requirement ID'leri: DS-LANGSWITCH-ENDONYM-01, DS-LANGSWITCH-REGION-02,
 * DS-LANGSWITCH-CURRENT-03, DS-LANGSWITCH-NOJS-04, DS-LANGSWITCH-SAMEPAGE-05,
 * DS-LANGSWITCH-UNAVAILABLE-06, DS-LANGSWITCH-DIR-07.
 */

const nine: LanguageSwitcherOption[] = [
    { code: 'en', href: '/en/product/qr-menu/' },
    { code: 'tr', href: '/tr/urun/qr-menu/' },
    { code: 'ar', href: '/ar/product/qr-menu/' },
    { code: 'ru', href: '/ru/product/qr-menu/' },
    { code: 'fa', href: '/fa/product/qr-menu/' },
    { code: 'ku', href: '/ku/product/qr-menu/' },
    { code: 'de', href: '/de/product/qr-menu/' },
    { code: 'fr', href: '/fr/product/qr-menu/' },
    { code: 'it', href: '/it/product/qr-menu/' },
];

const labels = {
    'not-offered': 'Not ready yet',
    'no-counterpart': 'Not available on this page',
} as const;

function renderSwitcher(options = nine, currentCode = 'en') {
    return render(
        <LanguageSwitcher
            label="Language"
            options={options}
            currentCode={currentCode}
            currentLabel="current"
            unavailableLabels={labels}
        />,
    );
}

describe('dil değiştirici — docs/120 §5', () => {
    // --- DS-LANGSWITCH-ENDONYM-01 (§5.1) ----------------------------------

    /**
     * HER DİL KENDİ DİLİNDE YAZILIR — arayüz dili ne olursa olsun.
     *
     * Bir kullanıcı arayüzü ANLAMADIĞI için dil değiştirmeye gelir. Dil adını
     * anlamadığı dilde göstermek, onu anladığı tek kelimeden mahrum bırakır.
     */
    it('her dili kendi dilinde yazar', () => {
        renderSwitcher();

        for (const language of Object.values(LANGUAGES)) {
            expect(
                screen.getByText(language.endonym),
                `DS-LANGSWITCH-ENDONYM-01: ${language.code} endonimi ekranda yok.`,
            ).toBeInTheDocument();
        }
    });

    // --- DS-LANGSWITCH-REGION-02 (§5.2, §6) -------------------------------

    /**
     * BÖLGE İŞARETİ İKİNCİLDİR ve ekran okuyucudan gizlidir.
     *
     * Bayrak tek başına dil anlatmaz (`docs/120` §6). "TR" harflerini duymak,
     * "Türkçe"yi duyduktan sonra bir bilgi eklemez; okunması yalnız gürültü
     * olurdu.
     */
    it('bölge işaretini gösterir ama ekran okuyucuya okutmaz', () => {
        const { container } = renderSwitcher();

        const marks = Array.from(container.querySelectorAll('[aria-hidden="true"]')).map(
            (node) => node.textContent,
        );

        expect(marks, 'DS-LANGSWITCH-REGION-02: bölge işareti ya yok ya okunuyor.').toContain('TR');
    });

    /**
     * BAYRAK İSTİSNALARI EKRANDA DA GEÇERLİ.
     *
     * `ar`, `ku` ve `en` için gösterilen işaret bir ÜLKE değil dilin kendi
     * kodudur. Yanlış bayrak sessiz bir hata değildir: kullanıcıyı kimliği
     * üzerinden yanlış yerleştirir ve kimi durumda siyasi bir iddia taşır.
     */
    it('ülkesi olmayan diller için nötr işaret gösterir', () => {
        renderSwitcher();

        for (const code of ['ar', 'ku', 'en']) {
            expect(
                LANGUAGES[code].hasCountryFlag,
                `DS-LANGSWITCH-REGION-02: ${code} bir ülkeyle eşleştirilmiş.`,
            ).toBe(false);
            expect(screen.getByText(LANGUAGES[code].regionMark)).toBeInTheDocument();
        }
    });

    // --- DS-LANGSWITCH-CURRENT-03 (§5.3, §5.4) ----------------------------

    /**
     * AKTİF DİL YALNIZ RENKLE DEĞİL, METİNLE DE İŞARETLENİR.
     *
     * Renk tek başına bir gösterge değildir: renk körü kullanıcı onu görmez,
     * yüksek kontrast temasında kaybolur ve yazdırıldığında hiç kalmaz.
     */
    it('aktif dili hem metinle hem aria-current ile işaretler', () => {
        renderSwitcher(nine, 'tr');

        const active = screen.getByRole('link', { current: true });

        expect(
            active,
            'DS-LANGSWITCH-CURRENT-03: aktif dil aria-current taşımıyor.',
        ).toHaveAttribute('lang', 'tr');
        expect(
            active.textContent,
            'DS-LANGSWITCH-CURRENT-03: aktif dil metinle işaretlenmemiş.',
        ).toContain('current');
    });

    it('gezinme bölgesi erişilebilir bir ada sahiptir', () => {
        renderSwitcher();

        expect(screen.getByRole('navigation', { name: 'Language' })).toBeInTheDocument();
    });

    // --- DS-LANGSWITCH-NOJS-04 (§5.6) -------------------------------------

    /**
     * JAVASCRIPT OLMADAN ÇALIŞIR.
     *
     * Her dil gerçek bir `<a href>`'tir. Betikle açılan bir menü, betik
     * yüklenmeden gelen kullanıcıyı dilsiz bırakırdı — ve dil değiştirici,
     * arayüzü anlamayan kullanıcının tek çıkış kapısıdır.
     *
     * Ölçüm mekanizmayı değil sonucu ölçüyor: dokuz dilin dokuzu da
     * bağlantıdır ve hiçbiri düğme değildir.
     */
    it('her dil gerçek bir bağlantıdır, hiçbiri düğme değildir', () => {
        renderSwitcher();

        expect(screen.getAllByRole('link')).toHaveLength(9);
        expect(
            screen.queryAllByRole('button'),
            'DS-LANGSWITCH-NOJS-04: bir düğme var — betik yüklenmezse o dil erişilemez olurdu.',
        ).toHaveLength(0);
    });

    // --- DS-LANGSWITCH-SAMEPAGE-05 (§5.7) ---------------------------------

    /**
     * DİL DEĞİŞTİRMEK AYNI SAYFADA KALIR.
     *
     * Adres sunucudan gelir (`page_key` karşılığı) ve bileşen onu OLDUĞU GİBİ
     * kullanır. Kendisi bir adres kurmaya kalksaydı — örneğin ön eki
     * değiştirerek — Türkçe slug'lı bir sayfa İngilizcede 404 verirdi.
     */
    it('sunucudan gelen adresi olduğu gibi kullanır', () => {
        renderSwitcher();

        expect(screen.getByRole('link', { name: /Türkçe/ })).toHaveAttribute(
            'href',
            '/tr/urun/qr-menu/',
        );
    });

    it('her bağlantı hedefinin dilini hrefLang ile ilan eder', () => {
        renderSwitcher();

        expect(screen.getByRole('link', { name: /Deutsch/ })).toHaveAttribute('hreflang', 'de');
    });

    // --- DS-LANGSWITCH-UNAVAILABLE-06 (§5.8) ------------------------------

    /**
     * SUNULMAYAN DİL YA GÖSTERİLMEZ YA AÇIKÇA SÖYLER.
     *
     * Üçüncü bir yol yok: seçilebilir görünüp yarım çeviri vermek, tam olarak
     * 2026-09-05'te kapatılan kusurdur.
     */
    it('hazır olmayan dili bağlantı yapmaz ve sebebini yazar', () => {
        renderSwitcher([
            { code: 'en', href: '/en/product/qr-menu/' },
            { code: 'de', href: null, unavailableReason: 'not-offered' },
        ]);

        expect(screen.getByText('Deutsch')).toBeInTheDocument();
        expect(screen.getByText('Not ready yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: /Deutsch/ }),
            'DS-LANGSWITCH-UNAVAILABLE-06: hazır olmayan dile bağlantı verilmiş.',
        ).toBeNull();
    });

    it('sebep metni verilmediğinde hazır olmayan dili hiç göstermez', () => {
        render(
            <LanguageSwitcher
                label="Language"
                options={[
                    { code: 'en', href: '/en/product/qr-menu/' },
                    { code: 'de', href: null, unavailableReason: 'not-offered' },
                ]}
                currentCode="en"
                currentLabel="current"
            />,
        );

        expect(
            screen.queryByText('Deutsch'),
            'DS-LANGSWITCH-UNAVAILABLE-06: sebep söylenemiyorsa dil listede durmamalı.',
        ).toBeNull();
    });

    it('iki farklı sebebi iki farklı cümleyle söyler', () => {
        renderSwitcher([
            { code: 'en', href: '/en/product/qr-menu/' },
            { code: 'de', href: null, unavailableReason: 'not-offered' },
            { code: 'fr', href: null, unavailableReason: 'no-counterpart' },
        ]);

        expect(screen.getByText('Not ready yet')).toBeInTheDocument();
        expect(screen.getByText('Not available on this page')).toBeInTheDocument();
    });

    // --- DS-LANGSWITCH-DIR-07 (§5.9) --------------------------------------

    /**
     * YÖN SATIRA UYGULANIR — sağdan sola bir endonim, soldan sağa bir listede
     * doğru dizilir.
     *
     * `lang` olmadan ekran okuyucu "العربية"yi İngilizce telaffuz etmeye
     * çalışır; `dir` olmadan aynı satır ters sırada okunur.
     */
    it('sağdan sola dillerin satırı sağdan sola yazılır', () => {
        renderSwitcher();

        for (const code of ['ar', 'fa']) {
            const link = screen.getByRole('link', { name: new RegExp(LANGUAGES[code].endonym) });

            expect(link, `DS-LANGSWITCH-DIR-07: ${code} satırı LTR kalmış.`).toHaveAttribute(
                'dir',
                'rtl',
            );
            expect(link).toHaveAttribute('lang', code);
        }
    });

    it('kütükte olmayan bir dil kodu hiç çizilmez', () => {
        // Sunucudan gelmemesi gereken bir değer geldiğinde bileşen sessizce
        // atlar: adsız, yönsüz bir satır çizmek kullanıcıya hiçbir şey
        // söylemez ve tıklandığında nereye gittiği bilinmez.
        renderSwitcher([
            { code: 'en', href: '/en/product/qr-menu/' },
            { code: 'ja', href: '/ja/product/qr-menu/' },
        ]);

        expect(screen.getAllByRole('link')).toHaveLength(1);
    });
});
