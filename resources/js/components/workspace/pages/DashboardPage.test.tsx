import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { DashboardPage } from './DashboardPage';
import type { DashboardMenuTree } from './DashboardPage';
import type { BrandProfile } from '../BrandEditForm';
import type { LocationProfile } from '../LocationEditForm';

/**
 * DASHBOARD_SETUP_RED
 *
 * RED suite for the S1-WP01A Dashboard Setup surface: each Setup row must be
 * an actionable link to its real workspace section hash (#brand, #locations,
 * #menu, #publication), brand/location/menu status must be derived only from
 * the props passed in (no invented data), and Publication/QR must honestly
 * read "not connected" since no backend wiring exists yet.
 */
const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
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

function makeBrand(): BrandProfile {
    return {
        id: 12,
        name: 'Zabuno Kahve',
        slug: 'zabuno-kahve',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
    } as BrandProfile;
}

function makeLocation(): LocationProfile {
    return {
        id: 34,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'Istanbul',
        address_line1: 'Moda Cad. 1',
    } as LocationProfile;
}

function makeMenuTree(): DashboardMenuTree {
    return {
        id: 1,
        workspaceId: 1,
        locationId: 34,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 1,
                menuId: 1,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 1,
                        categoryId: 1,
                        productId: 1,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

describe('DashboardPage — Dashboard Setup rows (DASHBOARD_SETUP_RED)', () => {
    beforeEach(() => {
        setViewport(320, 480);
    });

    it('exposes an accessible Dashboard Setup region', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        expect(screen.getByRole('region', { name: /dashboard setup/i })).toBeInTheDocument();
    });

    /**
     * ADIMLAR GERÇEKTEN GÖTÜRÜR — `docs/70`.
     *
     * Bu test eskiden `href="#brand"` gibi bağlantıları donduruyordu ve o
     * gün doğruydu: uygulama fragment ile geziniyordu. Adres tabanlı
     * gezintiye geçildiğinde bu bağlantılar HİÇBİR ŞEY yapmaz oldu — o
     * kimlikte bir öğe yok, tarayıcı kaymıyor, ekran duruyor.
     *
     * Ölü bağlantı, kullanıcının ilk gördüğü ekranda duruyordu ve testi onu
     * koruyordu.
     */
    it('her kurulum adımı gerçek bölüme götürür', async () => {
        const user = userEvent.setup();
        const onNavigateToSection = vi.fn();

        render(
            <DashboardPage dashboardMenuTree={null} onNavigateToSection={onNavigateToSection} />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });

        await user.click(within(region).getByRole('button', { name: /brand/i }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('settings/brand');

        await user.click(within(region).getByRole('button', { name: /location/i }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('locations');

        /*
            GÜNCELLENDİ (FF-100): adımın erişilebilir adı artık yalnız etiket
            değil — durumu ve değeri de taşıyor ("Menu · No menu yet · Next
            step"). Ekran okuyucu kullanan biri için doğrusu budur: adımın
            adı tek başına "nerede kaldım" sorusunu cevaplamaz.
        */
        await user.click(within(region).getByRole('button', { name: /^menu\b/i }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('menu');

        await user.click(within(region).getByRole('button', { name: /publication/i }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('publication');

        // QR artık YAYIN ekranına değil, kendi ekranına götürür.
        await user.click(within(region).getByRole('button', { name: /qr/i }));
        expect(onNavigateToSection).toHaveBeenLastCalledWith('qr-codes');
    });

    /**
     * Liste bir DURUM listesi değil, GÖREV listesidir: hangi adım bitti,
     * hangisi sırada (`docs/50` §6.1).
     */
    it('tamamlanan ve bekleyen adımları ayırt eder', () => {
        render(
            <DashboardPage
                dashboardMenuTree={null}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });

        // Marka ve şube var → tamamlandı. Menü yok → sıradaki adım.
        expect(within(region).getAllByText('Done').length).toBeGreaterThanOrEqual(2);
        expect(within(region).getByText('Next step')).toBeInTheDocument();
    });

    it('shows real brand and location names derived only from props, with an honest empty menu status', () => {
        render(
            <DashboardPage
                dashboardMenuTree={null}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).toMatch(/Zabuno Kahve/);
        expect(regionText).toMatch(/Kadıköy Şube/);
        expect(regionText).toMatch(/no menu yet/i);
    });

    it('derives the menu summary from the loaded dashboardMenuTree only', () => {
        render(
            <DashboardPage
                dashboardMenuTree={makeMenuTree()}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).toMatch(/1 categor/i);
        expect(regionText).toMatch(/1 item/i);
    });

    it('shows an honest not connected status for Publication and QR', () => {
        render(
            <DashboardPage
                dashboardMenuTree={null}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const publicationRow = within(region)
            .getByText(/^publication$/i)
            .closest('div');
        const qrRow = within(region).getByText(/^qr/i).closest('div');

        expect(publicationRow?.parentElement?.textContent ?? '').toMatch(/not connected/i);
        expect(qrRow?.parentElement?.textContent ?? '').toMatch(/not connected/i);
    });

    it('shows empty brand/location status without inventing any name when props are absent', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        expect(screen.queryByText('Zabuno Kahve')).toBeNull();
        expect(screen.queryByText('Kadıköy Şube')).toBeNull();
    });

    it('renders no fake ID, token or AI-generated claim inside the Setup region', () => {
        render(
            <DashboardPage
                dashboardMenuTree={makeMenuTree()}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const regionText = region.textContent ?? '';

        expect(regionText).not.toMatch(/#\d+/);
        expect(regionText).not.toMatch(/\btoken\b/i);
        expect(regionText).not.toMatch(/\bai\b/i);
    });

    it('carries no fixed-pixel or breakpoint class on the Setup region at 320x480', () => {
        render(
            <DashboardPage
                dashboardMenuTree={makeMenuTree()}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = screen.getByRole('region', { name: /dashboard setup/i });
        const classLists = collectClassLists(region);
        const offenders = classLists.filter(
            (classList) =>
                FIXED_PIXEL_CLASS_PATTERN.test(classList) ||
                BREAKPOINT_CLASS_PATTERN.test(classList),
        );

        expect(offenders).toEqual([]);
    });

    it('makes zero fetch calls on mount', () => {
        const fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);

        render(
            <DashboardPage
                dashboardMenuTree={makeMenuTree()}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        expect(fetchSpy).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });
});

/**
 * HOME BAŞLIK BLOĞU — AEP teslim paketi (`Restoran Paneli v2.dc.html`,
 * `DESIGN_SPEC.md` §2).
 *
 * Referans ekran iki satırla açılıyor: küçük ve sakin bir üst satır, onun
 * altında büyük bir KARŞILAMA. Depodaki hâl tek bir "Ana sayfa" başlığı ve
 * altında panelin ne yaptığını anlatan bir paragraftı — yani her sabah
 * açılan ekran, kullanıcıya kendisini değil KENDİNİ anlatıyordu.
 *
 * Başlığın erişilebilir adı DEĞİŞMEZ ("Home"): kabuk sözleşmesi gezinti
 * etiketi ile sayfa başlığının aynı olmasını şart koşuyor
 * (`WorkspaceApp.pages.test.tsx`). Karşılama bu yüzden başlığın YERİNE
 * geçmez, altına gelir — h1 sayfayı adlandırmayı sürdürür, karşılama ise
 * ekranın ilk baktığın yerdeki insan sesidir.
 */
describe('Home karşılama başlığı (AEP_HOME_GREETING_RED)', () => {
    beforeEach(() => {
        setViewport(320, 480);
    });

    it('sayfayı adlandıran h1 hâlâ "Home" olarak okunur', () => {
        render(<DashboardPage dashboardMenuTree={null} brand={makeBrand()} />);

        expect(screen.getByRole('heading', { level: 1, name: 'Home' })).toBeInTheDocument();
    });

    it('marka adı biliniyorsa karşılama o adı kullanır', () => {
        render(<DashboardPage dashboardMenuTree={null} brand={makeBrand()} />);

        expect(screen.getByText('Have a good shift, Zabuno Kahve.')).toBeInTheDocument();
    });

    /*
        Ad YOKKEN uydurulmaz. "Have a good shift, İşletmeniz." gibi bir
        yer tutucu, kullanıcının adını bildiğimizi ima eder ve ilk gün
        tam olarak bilmediğimiz tek şey odur.
    */
    it('marka yokken karşılama ad uydurmaz', () => {
        render(<DashboardPage dashboardMenuTree={null} />);

        expect(screen.getByText('Have a good shift.')).toBeInTheDocument();
        expect(screen.queryByText(/Have a good shift,/)).toBeNull();
    });

    /*
        Karşılama BAŞLIK BLOĞUNUN İÇİNDE. Sayfanın herhangi bir yerinde
        duran bir cümle, referanstaki "üst satır + büyük satır" ritmini
        vermez; iki satır aynı kapsayıcıda ve dar bir boşlukla durmalı.
    */
    it('karşılama, h1 ile aynı başlık bloğunda durur', () => {
        render(<DashboardPage dashboardMenuTree={null} brand={makeBrand()} />);

        const header = screen.getByRole('heading', { level: 1, name: 'Home' }).parentElement;

        expect(header?.textContent).toContain('Have a good shift, Zabuno Kahve.');
    });

    /*
        Sayı kartları AEP metrik ölçeğini GERÇEKTEN kullanmalı. Bu test
        `StatValue`'nun kendi testini tekrar etmiyor: Home'un o bileşeni
        hâlâ kullandığını, yani ölçeğin ekrana ULAŞTIĞINI doğruluyor.
    */
    it('sayı kartlarındaki rakam metrik ölçekte ve tabular çizilir', () => {
        render(
            <DashboardPage
                dashboardMenuTree={makeMenuTree()}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        // Kategori ve ürün sayısı da 1; ikisi de aynı ölçeği taşımalı.
        const values = screen.getAllByText('1');

        expect(values.length).toBeGreaterThan(0);
        for (const value of values) {
            expect(value.style.fontSize).toBe('var(--aep-text-metric)');
            expect(value.className).toContain('tabular-nums');
        }
    });
});

const WORKSPACE_ID = 71;

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

describe('DashboardPage — Publication/QR live status (DASHBOARD_PUBLICATION_QR_LIVE_RED)', () => {
    beforeEach(() => {
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows Published #<id> and active QR count when current-publication and QR-list both resolve', async () => {
        const menuTree = makeMenuTree();
        const fetchMock = vi.fn(async (url: string) => {
            if (
                String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/menu/${menuTree.id}/publications/current`
            ) {
                return jsonResponse(200, { id: 55 });
            }
            if (
                String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/${menuTree.locationId}/qr-codes`
            ) {
                return jsonResponse(200, [{ id: 1, state: 'active' }]);
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = await screen.findByRole('region', { name: /dashboard setup/i });

        expect(await within(region).findByText('Published #55')).toBeInTheDocument();
        expect(await within(region).findByText('1 active QR')).toBeInTheDocument();
        expect(within(region).queryByText('Not connected yet.')).toBeNull();
    });

    it('shows honest not-connected status when current-publication resolves 404, without inventing a QR connection', async () => {
        const menuTree = makeMenuTree();
        const fetchMock = vi.fn(async (url: string) => {
            if (
                String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/menu/${menuTree.id}/publications/current`
            ) {
                return jsonResponse(404, {});
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = await screen.findByRole('region', { name: /dashboard setup/i });

        expect(await within(region).findAllByText('Not connected yet.')).toHaveLength(2);
        expect(within(region).queryByText(/^Published #/)).toBeNull();
        expect(within(region).queryByText(/active QR/)).toBeNull();
    });

    it('shows Status unavailable. for Publication and QR on a non-404 publication failure, never a false not-connected claim', async () => {
        const menuTree = makeMenuTree();
        const fetchMock = vi.fn(async (url: string) => {
            if (
                String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/menu/${menuTree.id}/publications/current`
            ) {
                return jsonResponse(500, {});
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        render(
            <DashboardPage
                workspaceId={WORKSPACE_ID}
                dashboardMenuTree={menuTree}
                brand={makeBrand()}
                location={makeLocation()}
            />,
        );

        const region = await screen.findByRole('region', { name: /dashboard setup/i });

        expect(await within(region).findAllByText('Status unavailable.')).toHaveLength(2);
        expect(within(region).queryByText('Not connected yet.')).toBeNull();
    });
});
