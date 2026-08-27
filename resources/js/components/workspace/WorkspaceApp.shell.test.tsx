import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED test freezing the real AdminShell composition contract for
 * WorkspaceApp's current-workspace view (S1-WP01A admin shell migration).
 * WorkspaceApp does not compose AdminShell yet, so this must fail RED
 * against the frozen production anchors in the delivery contract.
 */

vi.mock('./BrandOnboardingForm', () => ({
    BrandOnboardingForm: () => <div data-testid="brand-onboarding-form" />,
}));

vi.mock('./LocationOnboardingForm', () => ({
    LocationOnboardingForm: () => <div data-testid="location-onboarding-form" />,
}));

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeUser() {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
    };
}

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return jsonResponse(200, [makeWorkspace()]);
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return jsonResponse(200, makeWorkspace());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'GET') {
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation()]);
        }

        throw new Error(`Unhandled fetch in WorkspaceApp shell test: ${method} ${String(url)}`);
    });
}

async function renderCurrentWorkspace() {
    const fetchMock = buildFetchMock();
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    render(<WorkspaceApp />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return fetchMock;
}

describe('WorkspaceApp — real AdminShell composition (S1-WP01A, RED)', () => {
    beforeEach(() => {
        // Her test tarayıcıyı YENİ açmış gibi başlar: gezinti artık adresi
        // gerçekten değiştiriyor ve bir testin bıraktığı adres sonrakini
        // sessizce başka bir ekranda açardı.
        history.replaceState(null, '', '/');
    });

    it('renders the current workspace inside the real AdminShell: brand, accessible nav, skip link, and main landmark hosting catalog content once Menu is selected', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        expect(screen.getByText('Zabuno')).toBeInTheDocument();

        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );

        const main = screen.getByRole('main');
        expect(main).toHaveAttribute('id', 'main-content');

        expect(screen.getByRole('navigation', { name: 'Restaurant admin' })).toBeInTheDocument();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menu' }));

        expect(within(main).getByTestId('menu-catalog-workspace')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('surfaces the current workspace name and user email, and preserves accessible Switch workspace and Log out controls', async () => {
        await renderCurrentWorkspace();

        const banner = screen.getByRole('banner');
        expect(
            within(banner).getByRole('button', { name: 'Zeytin Restoranları' }),
        ).toBeInTheDocument();
        expect(screen.getByText('ada@example.com')).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Switch workspace' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Log out' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    // Niyet aynı: çekmece açılınca gezinti gelir, kapanınca gider, masaüstü
    // gezintisi kalır. Ölçüm değişti. Eskiden bu test AÇIKKEN İKİ `navigation`
    // landmark'ı bekliyordu — yani axe'in `landmark-unique` ihlali olarak
    // bildirdiği kusurun kendisini doğruluyordu: aynı adı taşıyan iki landmark
    // ekran okuyucu listesinde ayırt edilemez. Çekmece zaten adlandırılmış bir
    // diyalog olduğu için içindeki gezinti artık landmark değil; landmark
    // sayısı HER ZAMAN bir kalır ve çekmecenin varlığı içeriğinden okunur.
    it('opens the mobile drawer navigation with a Close control, then removes it on close while the desktop navigation landmark stays unique', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const landmarks = () => screen.getAllByRole('navigation', { name: 'Restaurant admin' });
        const dashboardLinks = () => screen.getAllByRole('link', { name: 'Dashboard' });

        expect(landmarks()).toHaveLength(1);
        expect(dashboardLinks()).toHaveLength(1);

        await user.click(screen.getByRole('button', { name: 'Open menu' }));

        // Çekmece gezintisi geldi…
        expect(dashboardLinks()).toHaveLength(2);
        // …fakat ikinci bir landmark üretmedi.
        expect(landmarks()).toHaveLength(1);

        await user.click(screen.getByRole('button', { name: 'Close' }));

        await waitFor(() => {
            expect(dashboardLinks()).toHaveLength(1);
        });
        expect(landmarks()).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    // Bölüm bir "sayfa"dır ve yeni sayfa baştan başlar. Kaydırmayı elle
    // sıfırlamak gerekir: tek sayfa uygulamasında adres değişse de tarayıcı
    // sayfayı yeniden yüklemez, dolayısıyla kaydırma konumu olduğu yerde
    // kalır ve kullanıcı yeni ekranın ortasına düşer.
    it('returns the page to the top when the active section changes', async () => {
        const user = userEvent.setup();
        const scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);

        await renderCurrentWorkspace();

        scrollTo.mockClear();

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                scrollTo,
                'Bölüm değiştiğinde sayfa başa dönmeli; aksi hâlde kullanıcı yeni ekranın ortasında açılır.',
            ).toHaveBeenCalledWith(expect.objectContaining({ top: 0 }));
        });

        // Yumuşak kaydırma her gezinmede gürültüdür ve azaltılmış hareket
        // tercihini çiğner.
        expect(scrollTo).not.toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

        vi.unstubAllGlobals();
    });

    // Bu test, "sıçrama düzeldi" dedikten SONRA hâlâ sıçradığı için var.
    //
    // Kök sebep düzeltmenin kendisiydi: sayfa başa alınıyor, ardından
    // `focus()` onu geri aşağı çekiyordu. `focus()` varsayılan olarak
    // elemanı görünür alana kaydırır ve `main` üst çubuğun altında başlar.
    //
    // Gerçek tarayıcıda ölçülen (720px viewport, 2400px içerik):
    //   scrollTo({top: 0}) -> 0 ;  ardından focus() -> 1680 ;
    //   focus({ preventScroll: true }) -> 0
    //
    // jsdom'da `focus()` kaydırmadığı için bu hata bir birim testinde
    // GÖRÜNMEZ. Bu yüzden sözleşme davranış üzerinden değil, seçeneğin
    // verilmesi üzerinden zorlanır — kaybedilen tek şey budur ve bilerek
    // kabul edilmiştir.
    // Sıçramanın GERÇEK sebebi buydu ve iki düzeltme denemesinden sonra
    // ancak tarayıcıda ölçerek bulundu.
    //
    // İçerik değişince belge kısalır (ölçülen: Medya 3802px -> Analitik
    // 1250px) ve tarayıcı kaydırmayı KIRPAR: 1000 -> 122. O kırpma bizim
    // kodumuzdan ÖNCE olur; `scrollTo` çağrımız çoktan kırpılmış bir
    // konuma varır. Kullanıcının gördüğü sıçrama tam olarak o kırpmadır.
    //
    // Çözüm sırayı tersine çevirmek: kaydırma zaten 0'dayken içerik
    // değişirse kırpacak bir şey kalmaz.
    //
    // jsdom düzen hesaplamaz, yani kırpmayı ÜRETEMEZ. Ama SIRAYI
    // gösterebilir: `scrollTo` çağrıldığı anda hâlâ eski bölüm mü
    // basılı? Tutulabilen sözleşme budur.
    it('resets the scroll while the previous section is still mounted', async () => {
        const user = userEvent.setup();
        let previousSectionStillMounted: boolean | null = null;

        vi.stubGlobal(
            'scrollTo',
            vi.fn(() => {
                if (previousSectionStillMounted === null) {
                    // Kapsayıcı id'si ile bakıyoruz: metinle aramak
                    // belirsizdir (nav bağlantısı ve başlık aynı kelimeyi
                    // taşır) ve testi kırılgan yapar.
                    previousSectionStillMounted =
                        document.getElementById('section-dashboard') !== null;
                }
            }),
        );

        await renderCurrentWorkspace();
        previousSectionStillMounted = null;

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                previousSectionStillMounted,
                'Kaydırma sıfırlanmadan önce içerik değişirse tarayıcı konumu kırpar ve sayfa sıçrar.',
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('moves focus without letting the browser scroll the page back down', async () => {
        const user = userEvent.setup();
        vi.stubGlobal('scrollTo', vi.fn());

        const focusSpy = vi.spyOn(HTMLElement.prototype, 'focus');

        await renderCurrentWorkspace();
        focusSpy.mockClear();

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(
                focusSpy.mock.calls.some(([options]) => options?.preventScroll === true),
                'Bölüm değişiminde odak `preventScroll: true` ile taşınmalı; aksi hâlde tarayıcı sayfayı geri aşağı kaydırır.',
            ).toBe(true);
        });

        focusSpy.mockRestore();
        vi.unstubAllGlobals();
    });

    it('transitions to the existing choose-workspace journey when Switch workspace is activated', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Switch workspace' }));

        expect(screen.getByRole('heading', { name: 'Choose a workspace' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('shows Dashboard active by default, with a Dashboard nav item, a dashboard destination that does not host the catalog, and a separate menu destination for the Menu nav link once selected', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        const dashboardLink = within(nav).getByRole('link', { name: 'Dashboard' });
        expect(dashboardLink).toHaveAttribute('href', '/app/zeytin-restoranlari/dashboard');
        expect(dashboardLink).toHaveAttribute('aria-current', 'page');

        const menuLink = within(nav).getByRole('link', { name: 'Menu' });
        expect(menuLink).toHaveAttribute('href', '/app/zeytin-restoranlari/menu');
        expect(menuLink).not.toHaveAttribute('aria-current', 'page');

        const main = screen.getByRole('main');
        expect(main.querySelector('#section-dashboard')).not.toBeNull();
        expect(main.querySelector('#section-menu')).toBeNull();

        await user.click(menuLink);

        expect(main.querySelector('#section-dashboard')).toBeNull();
        expect(main.querySelector('#section-menu')).not.toBeNull();

        const catalogDestination = within(main)
            .getByTestId('menu-catalog-workspace')
            .closest('#section-menu');
        expect(catalogDestination).not.toBeNull();

        vi.unstubAllGlobals();
    });

    it('closes the mobile drawer when its Menu link is activated, moving aria-current to Menu while the destination and desktop nav remain', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Open menu' }));

        const dialog = screen.getByRole('dialog');
        await user.click(within(dialog).getByRole('link', { name: 'Menu' }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        expect(screen.getByRole('navigation', { name: 'Restaurant admin' })).toBeInTheDocument();

        expect(screen.getByRole('link', { name: 'Menu' })).toHaveAttribute('aria-current', 'page');
        expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute(
            'aria-current',
            'page',
        );

        const main = screen.getByRole('main');
        expect(within(main).getByTestId('menu-catalog-workspace')).toBeInTheDocument();
        expect(main.querySelector('#section-menu')).not.toBeNull();

        vi.unstubAllGlobals();
    });
    // ANALYTICS-TENANT-SEAM: sahibin kilit kuralının ürün içindeki kanıtı.
    //
    // Tek sayfa uygulamasında `history.pushState` tarayıcıya göre sayfa
    // DEĞİŞTİRMEZ; GA4 ve Metrica kendiliğinden hiçbir şey ölçmez. Bu test
    // olmadan panelde on ekran gezen bir kullanıcı, ölçümde tek sayfalık bir
    // ziyaret gibi görünürdü — ve bunun tarayıcıda hiçbir belirtisi olmazdı.
    it('reports every section change to the dataLayer with the tenant attached', async () => {
        const user = userEvent.setup();
        const dataLayer: Array<Record<string, unknown>> = [];
        (window as unknown as { dataLayer: unknown[] }).dataLayer = dataLayer;

        await renderCurrentWorkspace();

        dataLayer.length = 0;

        await user.click(screen.getByRole('link', { name: 'Media' }));

        await waitFor(() => {
            expect(dataLayer).toHaveLength(1);
        });

        expect(dataLayer[0]).toMatchObject({
            event: 'page_view',
            page_path: '/app/zeytin-restoranlari/media',
            zabuno_tenant_slug: 'zeytin-restoranlari',
        });

        delete (window as unknown as { dataLayer?: unknown[] }).dataLayer;
        vi.unstubAllGlobals();
    });
    // Kullanıcı panele `/app` adresinden girer; orada hangi restoranın hangi
    // ekranı olduğu yazmaz. Böyle bir adres paylaşılamaz, yer imine
    // konamaz ve sunucu günlüğünde bütün restoranlar tek satıra karışır.
    it('rewrites the bare /app address to the tenant and section it is actually showing', async () => {
        history.replaceState(null, '', '/app');

        await renderCurrentWorkspace();

        await waitFor(() => {
            expect(window.location.pathname).toBe('/app/zeytin-restoranlari/dashboard');
        });

        vi.unstubAllGlobals();
    });
});
