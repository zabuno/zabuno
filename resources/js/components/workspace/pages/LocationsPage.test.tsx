import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { LocationsPage } from './LocationsPage';
import type { LocationProfile } from '../LocationEditForm';

/**
 * ŞUBELER EKRANI — panel v3 kanonik kaynağı (`panel.dc.html`,
 * `data-screen-label="Şubeler"`).
 *
 * DÜZEN DEĞİŞTİ, ÖLÇÜ AYNI KALDI. Ekran "şehir başlıklı kart + içinde şube
 * satırları" düzenindeydi; kaynağın düzeni KART IZGARASIDIR. Bu dosyadan iki
 * grup test kaldırıldı ve sebebi yazılıdır:
 *
 * 1. **Sayfa içi şube seçici** ("Location" açılır listesi). Sözleşme sanılan
 *    şey aslında ÜST ÇUBUĞUN işiydi: `WorkspaceContextControls` aynı seçimi
 *    zaten sunuyor ve iki şubeden azında kendini gizliyor. Sayfadaki kopya
 *    aynı işi ikinci kez gösteriyordu. Seçme YETENEĞİ kaybolmadı, yer
 *    değiştirdi: kartın "Masalar" düğmesi o şubeyi seçer ve karekod ekranına
 *    götürür — kaynağın `goQr` bağlaması da tam olarak budur.
 * 2. **"{N} locations" sayacı.** Izgara şubeleri zaten yan yana gösteriyor;
 *    üstünde ayrıca "2 locations" yazmak, sayılabilir bir şeyi sayıp
 *    söylemekti.
 *
 * Korunan şey ölçüdür: 320 pikselde sabit piksel / breakpoint sınıfı yok,
 * dürüst boş durum yerinde, ve UYDURULMUŞ SAYI YOK.
 */

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

function makeLocation(overrides: Partial<LocationProfile> = {}): LocationProfile {
    return {
        id: 811,
        workspace_id: 61,
        brand_id: 501,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Moda Cd. 12',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

function renderPage(props: Partial<Parameters<typeof LocationsPage>[0]> = {}) {
    return render(
        <LocationsPage
            workspaceId={61}
            locations={[makeLocation()]}
            onLocationSaved={vi.fn()}
            onLocationCreated={vi.fn()}
            onOpenTables={vi.fn()}
            addingLocation={false}
            onToggleAddLocation={() => {}}
            {...props}
        />,
    );
}

function timeSeriesResponse(body: unknown) {
    return {
        ok: true,
        status: 200,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

describe('LocationsPage — dürüst 0 şube boş durumu', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('şube yokken kart çizmez ve ölçüm için sunucuya hiç gitmez', () => {
        renderPage({ locations: [] });

        expect(screen.queryAllByRole('article')).toHaveLength(0);
        // Ölçülecek bir şube yokken analitik ucuna gitmek, cevabı boş olduğu
        // bilinen bir soruyu sormak olurdu.
        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('sıfır şubede de "Şube ekle" düğmesi durur', () => {
        renderPage({ locations: [] });

        expect(screen.getByRole('button', { name: 'Add location' })).toBeInTheDocument();
    });
});

describe('LocationsPage — kart ızgarası', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('her şube için bir kart çizer', () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => timeSeriesResponse({ state: 'not_enough_data' })),
        );

        renderPage({
            locations: [makeLocation(), makeLocation({ id: 812, display_name: 'Beşiktaş Şube' })],
        });

        expect(screen.getByText('Kadıköy Şube')).toBeInTheDocument();
        expect(screen.getByText('Beşiktaş Şube')).toBeInTheDocument();
        expect(screen.getAllByRole('article')).toHaveLength(2);
    });

    /**
     * Kaynağın `goQr` bağlaması: kart "Masalar"a basınca o şubenin karekod
     * ekranına gider. Sayfa içi açılır listeden kaybolan yetenek buraya
     * taşındı ve burada bir CÜMLE oldu — "bu şubenin masaları".
     */
    it('"Masalar" düğmesi şubenin kimliğiyle çağırır', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => timeSeriesResponse({ state: 'not_enough_data' })),
        );
        const onOpenTables = vi.fn();
        const user = userEvent.setup();

        renderPage({ locations: [makeLocation()], onOpenTables });

        await user.click(screen.getByRole('button', { name: 'Tables at Kadıköy Şube' }));

        expect(onOpenTables).toHaveBeenCalledWith(811);
    });

    /**
     * Kart düzenleme panelini AÇAR; panelin kendi "Edit" düğmesi alanları
     * yazılabilir yapar (`LocationEditForm`'un mevcut sözleşmesi). Panel
     * kapalıyken hiç çizilmez: üç şubeli bir markada üç uzun form yan yana
     * durursa ızgara ızgara olmaktan çıkar.
     */
    it('"Düzenle" şubenin düzenleme panelini açar ve kapatır', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => timeSeriesResponse({ state: 'not_enough_data' })),
        );
        const user = userEvent.setup();

        renderPage({ locations: [makeLocation()] });

        const edit = screen.getByRole('button', { name: 'Edit details for Kadıköy Şube' });
        expect(edit).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('button', { name: 'Edit Kadıköy Şube' })).not.toBeInTheDocument();

        await user.click(edit);
        expect(edit).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('button', { name: 'Edit Kadıköy Şube' })).toBeInTheDocument();

        await user.click(edit);
        expect(edit).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('button', { name: 'Edit Kadıköy Şube' })).not.toBeInTheDocument();
    });
});

describe('LocationsPage — haftalık tarama ölçümü', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    /**
     * Sayı GERÇEK ÖLÇÜMDEN gelir: marka kapsamlı zaman serisi ucunun şube
     * payı (`locationShare`). Uydurulmaz ve tahmin edilmez.
     */
    it('ölçüm hazırsa şube payından haftalık taramayı yazar', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                expect(String(url)).toContain('/api/workspaces/61/analytics/time-series?range=7d');

                return timeSeriesResponse({
                    state: 'ready',
                    locationShare: [
                        { id: 811, label: 'Kadıköy Şube', qrResolveCount: 340, sharePercent: 100 },
                    ],
                });
            }),
        );

        renderPage({
            locations: [makeLocation(), makeLocation({ id: 812, display_name: 'Beşiktaş Şube' })],
        });

        expect(await screen.findByText('340 scans/week')).toBeInTheDocument();
        // Payda hiç görünmeyen şube o pencerede HİÇ taranmamıştır: gerçek 0.
        expect(screen.getByText('0 scans/week')).toBeInTheDocument();
    });

    it('ölçüm eşiğin altındaysa hiçbir kart tarama sayısı yazmaz', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => timeSeriesResponse({ state: 'not_enough_data', locationShare: [] })),
        );

        renderPage({ locations: [makeLocation()] });

        await waitFor(() => expect(screen.getByText('Kadıköy Şube')).toBeInTheDocument());

        expect(screen.queryByText(/scans\/week/)).not.toBeInTheDocument();
    });

    /**
     * Rapor plana bağlıdır (402) ve yetkisiz kullanıcıya 404 döner. İkisinde
     * de doğru davranış AYNIDIR: sayı yoktur, o yüzden yazılmaz. Yerine "0"
     * koymak, ölçülmemiş bir şeyi ölçülmüş göstermek olurdu.
     */
    it('ölçüm ucu kapalıysa sayı yerine hiçbir şey yazmaz', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    ({
                        ok: false,
                        status: 402,
                        headers: new Headers(),
                        json: async () => ({}),
                    }) as Response,
            ),
        );

        renderPage({ locations: [makeLocation()] });

        await waitFor(() => expect(screen.getByText('Kadıköy Şube')).toBeInTheDocument());

        expect(screen.queryByText(/scans\/week/)).not.toBeInTheDocument();
    });
});

describe('LocationsPage — akışkan işaretleme', () => {
    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('320x480 görünümünde sabit piksel ya da breakpoint sınıfı taşımaz', () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => timeSeriesResponse({ state: 'not_enough_data' })),
        );
        setViewport(320, 480);

        const { container } = renderPage({ locations: [makeLocation()] });

        const root = container.querySelector('#section-locations') as HTMLElement;
        expect(root).not.toBeNull();

        const classLists = collectClassLists(root);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });
});
