import { beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach } from 'vitest';

import { LocationCard, type LocationCardLocation } from './LocationCard';
import type { OpeningHoursEntry } from '../../location/openingHours';

/**
 * ŞUBE KARTI — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Şubeler"`).
 *
 * NEDEN KIRMIZI: bu bileşen yok. Şubeler ekranı bugün "şehir başlıklı kart +
 * içinde satırlar" düzenindeydi; kaynağın düzeni ise KART IZGARASIDIR. Fark
 * kozmetik değil: satır listesi şubeleri şehre göre gruplayıp her birini bir
 * form satırına indiriyordu, yani "Kadıköy kaç masalı, bu hafta kaç kez
 * tarandı, kurulumu bitti mi" sorularının hiçbirinin ekranda karşılığı yoktu.
 *
 * SAYILAR UYDURULMAZ. Kart üç sayı taşıyabilir ama yalnız GERÇEĞİ OLANI
 * çizer:
 *   - masa sayısı → `dining_tables` satırlarından gelir, hep vardır,
 *   - tarama/hafta → analitik zaman serisi ucundan gelir; ölçüm kapalıysa
 *     (plan içermiyor, yetki yok, eşik altı) `null` gelir ve HİÇ çizilmez —
 *     yerine "0" yazmak, ölçülmemiş bir şeyi ölçülmüş göstermek olurdu,
 *   - çalışma saatleri → artık GERÇEK bir alan (`opening_hours`, `docs/109`
 *     §6.4). Girilmemişse satır HİÇ çizilmez; uydurma "09:00–23:00" yok.
 */
const BASE: LocationCardLocation = {
    id: 811,
    workspace_id: 61,
    brand_id: 501,
    display_name: 'Zeytin Kadıköy',
    country_code: 'TR',
    timezone: 'Europe/Istanbul',
    city: 'İstanbul',
    address_line1: 'Moda Caddesi 12',
    address_line2: null,
    postal_code: null,
    table_count: 12,
};

function renderCard(overrides: Partial<Parameters<typeof LocationCard>[0]> = {}) {
    return render(
        <LocationCard
            location={BASE}
            weeklyScans={340}
            editing={false}
            onOpenTables={vi.fn()}
            onToggleEdit={vi.fn()}
            {...overrides}
        />,
    );
}

/**
 * Zaman DONDURULUR: "bugün" iddiası olan bir kartı gerçek saatle test etmek,
 * testi haftanın gününe bağımlı — yani cumartesi kırılan bir test — yapardı.
 * 2026-09-07 pazartesi; İstanbul'da 16:00.
 */
beforeEach(() => {
    /*
        `shouldAdvanceTime` ŞART: `userEvent` gerçek zamanla ilerleyen
        bekleme kullanır ve zaman tamamen dondurulursa tık testleri sonsuza
        kadar bekler. Bu bayrak saati donmuş bir NOKTADAN başlatır ama
        akmasına izin verir — testin ihtiyacı olan tam da budur: bilinen
        bir gün, çalışan bir etkileşim.
    */
    vi.useFakeTimers({ shouldAdvanceTime: true });
    vi.setSystemTime(new Date('2026-09-07T13:00:00Z'));
});

afterEach(() => {
    vi.useRealTimers();
    cleanup();
});

/** Yedi gün 09:00–23:00; çağıran istediği günü ezer. */
function week(overrides: Partial<Record<number, OpeningHoursEntry>> = {}): OpeningHoursEntry[] {
    const days: OpeningHoursEntry[] = [];

    for (let day = 1; day <= 7; day++) {
        days.push(overrides[day] ?? { day, closed: false, opens_minute: 540, closes_minute: 1380 });
    }

    return days;
}

describe('LocationCard — kaynağın kart grameri', () => {
    it('adı, adresi ve masa sayısını taşır', () => {
        renderCard();

        expect(screen.getByText('Zeytin Kadıköy')).toBeInTheDocument();
        expect(screen.getByText('Moda Caddesi 12')).toBeInTheDocument();
        expect(screen.getByText('12 tables')).toBeInTheDocument();
    });

    /**
     * Adres alanı sunucuda zorunludur ama boş bir dizge yine de gelebilir
     * (eski kayıt, kırpılmış girdi). Kart o zaman boşluk göstermez: sahip
     * "adres yazmayı unutmuşum" diyebilmeli.
     */
    it('adres boşsa yerine bir cümle yazar', () => {
        renderCard({ location: { ...BASE, address_line1: '   ' } });

        expect(screen.getByText('No address yet')).toBeInTheDocument();
    });

    it('ikinci adres satırını da gösterir', () => {
        renderCard({ location: { ...BASE, address_line2: 'Kat 2' } });

        expect(screen.getByText('Moda Caddesi 12 Kat 2')).toBeInTheDocument();
    });

    // --- ÖLÇÜLMEYEN SAYI ÇİZİLMEZ ----------------------------------------

    it('ölçüm açıkken haftalık taramayı yazar', () => {
        renderCard({ weeklyScans: 340 });

        expect(screen.getByText('340 scans/week')).toBeInTheDocument();
    });

    it('ölçüm kapalıyken tarama satırı HİÇ çizilmez', () => {
        renderCard({ weeklyScans: null });

        expect(screen.queryByText(/scans\/week/)).not.toBeInTheDocument();
        // Sıfır da yazılmaz: "0 tarama" ile "ölçemedim" aynı şey değildir.
        expect(screen.queryByText('0 scans/week')).not.toBeInTheDocument();
    });

    it('gerçek sıfır tarama bir cevaptır ve yazılır', () => {
        renderCard({ weeklyScans: 0 });

        expect(screen.getByText('0 scans/week')).toBeInTheDocument();
    });

    // --- ÇALIŞMA SAATLERİ: GİRİLMEMİŞSE ÇİZİLMEZ --------------------------

    /**
     * Kaynağın kartı üçüncü ölçü olarak çalışma saatini gösteriyor
     * ("09:00–23:00"). Sahip henüz girmediyse kart o satırı HİÇ çizmez:
     * uydurma bir varsayılan, sahibin hiç söylemediği bir iddiayı ekranda
     * doğruymuş gibi gösterirdi.
     */
    it('saat girilmemişse satır HİÇ çizilmez', () => {
        renderCard({ location: { ...BASE, opening_hours: [] } });

        expect(screen.queryByTestId('location-card-hours')).not.toBeInTheDocument();
    });

    it('alan hiç yoksa da çizilmez — eski kayıt bozulmaz', () => {
        renderCard();

        expect(screen.queryByTestId('location-card-hours')).not.toBeInTheDocument();
    });

    it('tek tip haftada aralığı koşulsuz yazar', () => {
        renderCard({ location: { ...BASE, opening_hours: week() } });

        expect(screen.getByTestId('location-card-hours')).toHaveTextContent('09:00–23:00');
    });

    /**
     * Gece yarısını aşan kapanış SAKLAMADA 1440'ı geçer (10:00–00:00 →
     * 1440) ama ekranda gündelik saattir: kimse "24:00" diye bir saat
     * bilmez.
     */
    it('gece yarısını aşan aralığı gündelik saatle yazar', () => {
        const hours = week();

        for (const entry of hours) {
            entry.opens_minute = 600;
            entry.closes_minute = 1440;
        }

        renderCard({ location: { ...BASE, opening_hours: hours } });

        expect(screen.getByTestId('location-card-hours')).toHaveTextContent('10:00–00:00');
    });

    /**
     * Hafta değişiyorsa tek bir aralık yazmak yalan olur. Kart o zaman
     * BUGÜNÜ söyler ve bunu açıkça belirtir — "Today …".
     */
    it('hafta değişiyorsa bugünü söylediğini belirtir', () => {
        const hours = week({
            3: { day: 3, closed: true, opens_minute: null, closes_minute: null },
        });

        renderCard({ location: { ...BASE, opening_hours: hours } });

        expect(screen.getByTestId('location-card-hours')).toHaveTextContent('Today 09:00–23:00');
    });

    it('bugün kapalıysa bunu yazar', () => {
        const hours = week({
            1: { day: 1, closed: true, opens_minute: null, closes_minute: null },
        });

        renderCard({ location: { ...BASE, opening_hours: hours } });

        expect(screen.getByTestId('location-card-hours')).toHaveTextContent('Closed today');
    });

    // --- DURUM: YALNIZ BİLİNEBİLEN YÖN -----------------------------------

    /**
     * Kaynak köşeye "Açık" / "Kurulumda" rozeti koyuyor. Depoda şubenin
     * AÇIK olduğunu söyleyen hiçbir alan yok: masası olan bir şube tadilatta
     * da olabilir. Bu yüzden yalnız KANITLANABİLİR yön çizilir — masası
     * olmayan bir şube taranamaz, yani kurulumu bitmemiştir.
     *
     * Rozet METİN taşır; renk yalnız ona eşlik eder (WCAG 2.2 §1.4.1).
     */
    it('masası olmayan şubede kurulum rozeti gösterir', () => {
        renderCard({ location: { ...BASE, table_count: 0 } });

        expect(screen.getByText('In setup')).toBeInTheDocument();
    });

    it('masası olan şubede "Açık" diye bir iddia YAZMAZ', () => {
        renderCard({ location: { ...BASE, table_count: 12 } });

        expect(screen.queryByText('In setup')).not.toBeInTheDocument();
        expect(screen.queryByText(/^Open$/)).not.toBeInTheDocument();
    });

    // --- İKİ EYLEM --------------------------------------------------------

    it('masalar ve düzenle düğmeleri şubenin adıyla ayrışır', async () => {
        const onOpenTables = vi.fn();
        const onToggleEdit = vi.fn();
        const user = userEvent.setup();

        renderCard({ onOpenTables, onToggleEdit });

        await user.click(screen.getByRole('button', { name: 'Tables at Zeytin Kadıköy' }));
        expect(onOpenTables).toHaveBeenCalledTimes(1);

        await user.click(screen.getByRole('button', { name: 'Edit details for Zeytin Kadıköy' }));
        expect(onToggleEdit).toHaveBeenCalledTimes(1);
    });

    it('düzenleme açıkken formu kartın içinde taşır', () => {
        renderCard({ editing: true, children: <p>Form buraya</p> });

        expect(screen.getByText('Form buraya')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Edit details for Zeytin Kadıköy' }),
        ).toHaveAttribute('aria-expanded', 'true');
    });

    // --- GÖRSEL SÖZLEŞME --------------------------------------------------

    /**
     * Ham Tailwind paleti, sabit hap yarıçapı, `font-semibold` ve fiziksel
     * yön sınıfları bu depoda yasaktır: hepsi jeton kökünü atlar ya da RTL'de
     * kırılır (`docs/36` §5).
     */
    it('jeton kökünü atlayan sınıf taşımaz', () => {
        const { container } = renderCard({ location: { ...BASE, table_count: 0 } });

        const markup = container.innerHTML;

        expect(markup).not.toMatch(/\bfont-semibold\b/);
        expect(markup).not.toMatch(/\brounded-full\b/);
        expect(markup).not.toMatch(
            /\b(?:bg|text|border)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/,
        );
        expect(markup).not.toMatch(/\b(?:ml|mr|pl|pr|left|right|text-left|text-right)-\d/);
        expect(markup).not.toMatch(/\b(?:sm|md|lg|xl|2xl):/);
    });

    /** Sayılar `tabular-nums`: kartlar yan yana dururken rakamlar kaymaz. */
    it('sayı satırı tablo rakamı kullanır', () => {
        renderCard({ location: { ...BASE, opening_hours: week() } });

        expect(screen.getByText('12 tables').className).toContain('tabular-nums');
        expect(screen.getByText('340 scans/week').className).toContain('tabular-nums');
        // Saatler de rakamdır: "09:00–23:00" ile "10:00–00:00" yan yana
        // duran iki kartta aynı genişlikte olmalı.
        expect(screen.getByTestId('location-card-hours').className).toContain('tabular-nums');
    });
});
