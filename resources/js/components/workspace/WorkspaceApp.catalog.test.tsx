import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * Freezes the current-workspace brand/location catalog routing contract for
 * WorkspaceApp (S1-WP02C follow-on). MenuCatalogWorkspace now mounts only
 * under the exclusive Menu (#menu) destination rather than always-on, so
 * tests click Menu before asserting catalog content
 * (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED).
 */

vi.mock('./BrandOnboardingForm', () => ({
    BrandOnboardingForm: (props: { workspaceId: number; onCreated: (brand: unknown) => void }) => {
        mockBrandOnboardingFormProps = props;
        return <div data-testid="brand-onboarding-form" data-workspace-id={props.workspaceId} />;
    },
}));

vi.mock('./LocationOnboardingForm', () => ({
    LocationOnboardingForm: (props: {
        workspaceId: number;
        onCreated: (location: unknown) => void;
    }) => {
        mockLocationOnboardingFormProps = props;
        return <div data-testid="location-onboarding-form" data-workspace-id={props.workspaceId} />;
    },
}));

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => {
        mockMenuCatalogWorkspaceProps = props;
        return (
            <div
                data-testid="menu-catalog-workspace"
                data-workspace-id={props.workspaceId}
                data-location-id={props.locationId}
            />
        );
    },
}));

let mockBrandOnboardingFormProps: {
    workspaceId: number;
    onCreated: (brand: unknown) => void;
} | null = null;
let mockLocationOnboardingFormProps: {
    workspaceId: number;
    onCreated: (location: unknown) => void;
} | null = null;
let mockMenuCatalogWorkspaceProps: { workspaceId: number; locationId: number } | null = null;

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
}

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

function makeBrand(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 811,
        workspace_id: WORKSPACE_ID,
        name: 'Zeytin',
        slug: 'zeytin',
        locale: 'tr',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contact_email: null,
        contact_phone: null,
        ...overrides,
    };
}

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

type Handlers = {
    brand?: () => Response;
    locations?: () => Response;
};

function buildFetchMock(handlers: Handlers) {
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
            return handlers.brand
                ? handlers.brand()
                : jsonResponse(404, { error: 'brand_not_found' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return handlers.locations ? handlers.locations() : jsonResponse(200, []);
        }

        throw new Error(
            `Unhandled fetch in WorkspaceApp catalog routing test: ${method} ${String(url)}`,
        );
    });
}

describe('WorkspaceApp — current workspace brand/location catalog routing (S1-WP02C follow-on, RED)', () => {
    beforeEach(() => {
        // Her test tarayıcıyı YENİ açmış gibi başlar: gezinti artık adresi
        // gerçekten değiştiriyor ve bir testin bıraktığı adres sonrakini
        // sessizce başka bir ekranda açardı.
        history.replaceState(null, '', '/');
    });

    it('fetches the brand profile for the current workspace id', async () => {
        const fetchMock = buildFetchMock({});
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(`/api/workspaces/${WORKSPACE_ID}/brand`);
        });

        vi.unstubAllGlobals();
    });

    it('keeps the default Dashboard visible and does not co-render BrandOnboardingForm when the brand fetch 404s', async () => {
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(404, { error: 'brand_not_found' }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(`/api/workspaces/${WORKSPACE_ID}/brand`);
        });

        // FF-137: bölüm `lazy` ile iniyor — çizilmesi için bir tık beklenir.
        await waitFor(() => {
            expect(document.getElementById('section-dashboard')).toBeInTheDocument();
        });
        expect(screen.queryByTestId('brand-onboarding-form')).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders BrandOnboardingForm exactly once with the workspace id after navigating to Brand when the brand fetch 404s, then loads locations after onCreated fires', async () => {
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(404, { error: 'brand_not_found' }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        await screen.findByRole('navigation', { name: 'Restaurant admin' });
        /*
            Marka kurulumu `brand` bölümünde yaşar ve o bölüm artık kenar
            çubuğunda LİSTELENMEZ (docs/50 §5) — adresi ise çalışır. Kurulum
            akışı bir gezinti hedefi değil, tek seferlik bir yolculuktur;
            kullanıcı oraya Dashboard'daki kurulum listesinden yönlendirilir.
        */
        history.replaceState(null, '', '/app/zeytin-restoranlari/brand');
        window.dispatchEvent(new PopStateEvent('popstate'));

        const brandOnboardingForms = await screen.findAllByTestId('brand-onboarding-form');
        expect(brandOnboardingForms).toHaveLength(1);
        expect(mockBrandOnboardingFormProps?.workspaceId).toBe(WORKSPACE_ID);

        expect(document.getElementById('section-brand')).not.toBeInTheDocument();

        mockBrandOnboardingFormProps?.onCreated(makeBrand({ id: 811, name: 'Zeytin' }));

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(`/api/workspaces/${WORKSPACE_ID}/brand/locations`);
        });

        const brandSection = await waitFor(() => {
            const el = document.getElementById('section-brand');
            if (!el) {
                throw new Error('Brand section not rendered after onCreated');
            }
            return el;
        });
        expect(within(brandSection).getByText('Zeytin')).toBeInTheDocument();

        expect(within(brandSection).queryByText('Loading your brand…')).not.toBeInTheDocument();
        expect(screen.queryByTestId('brand-onboarding-form')).not.toBeInTheDocument();

        const locationsCalls = fetchMock.mock.calls.filter(
            (call) => String(call[0]) === `/api/workspaces/${WORKSPACE_ID}/brand/locations`,
        );
        expect(locationsCalls).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('keeps the default Dashboard visible and does not co-render LocationOnboardingForm when the brand exists but locations are empty', async () => {
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(200, makeBrand()),
            locations: () => jsonResponse(200, []),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain(`/api/workspaces/${WORKSPACE_ID}/brand/locations`);
        });

        expect(document.getElementById('section-dashboard')).toBeInTheDocument();
        expect(screen.queryByTestId('location-onboarding-form')).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders LocationOnboardingForm exactly once with the workspace id after navigating to Locations when the brand exists but locations are empty, then mounts MenuCatalogWorkspace under Menu after onCreated fires', async () => {
        const user = userEvent.setup();
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(200, makeBrand()),
            locations: () => jsonResponse(200, []),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Locations' }));

        const locationOnboardingForms = await screen.findAllByTestId('location-onboarding-form');
        expect(locationOnboardingForms).toHaveLength(1);
        expect(mockLocationOnboardingFormProps?.workspaceId).toBe(WORKSPACE_ID);

        expect(document.getElementById('section-locations')).not.toBeInTheDocument();

        mockLocationOnboardingFormProps?.onCreated(makeLocation({ id: 923 }));

        await user.click(within(nav).getByRole('link', { name: 'Menus' }));

        const menuCatalog = await screen.findByTestId('menu-catalog-workspace');
        expect(menuCatalog).toBeInTheDocument();
        expect(mockMenuCatalogWorkspaceProps?.workspaceId).toBe(WORKSPACE_ID);
        expect(mockMenuCatalogWorkspaceProps?.locationId).toBe(923);

        vi.unstubAllGlobals();
    });

    it('mounts MenuCatalogWorkspace under Menu with the workspace id and the first fetched location id when the brand exists and locations are non-empty', async () => {
        const user = userEvent.setup();
        const orderedLocations = [makeLocation({ id: 4021 }), makeLocation({ id: 4022 })];
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(200, makeBrand()),
            locations: () => jsonResponse(200, orderedLocations),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menus' }));

        const menuCatalog = await screen.findByTestId('menu-catalog-workspace');
        expect(menuCatalog).toBeInTheDocument();
        expect(mockMenuCatalogWorkspaceProps?.workspaceId).toBe(WORKSPACE_ID);
        expect(mockMenuCatalogWorkspaceProps?.locationId).toBe(4021);
        expect(mockMenuCatalogWorkspaceProps?.locationId).not.toBe(923);

        vi.unstubAllGlobals();
    });

    it('shows an accessible error with retry and does not mount MenuCatalogWorkspace when the brand profile fetch fails', async () => {
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/user') return jsonResponse(200, makeUser());
            if (String(url) === '/api/workspaces') return jsonResponse(200, [makeWorkspace()]);
            if (String(url) === '/api/workspace-context') return jsonResponse(200, makeWorkspace());
            if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand`) {
                throw new TypeError('Failed to fetch');
            }

            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /retry|try again/i })).toBeInTheDocument();
        expect(screen.queryByTestId('menu-catalog-workspace')).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /**
     * ŞUBE SEÇİCİ ÜST ÇUBUĞA AİT — `docs/109` §6.4, kaynak `panel.dc.html`
     * (`data-screen-label="Şubeler"`).
     *
     * Aynı seçim iki yerde duruyordu: sayfanın içinde bir açılır liste ve üst
     * çubukta `WorkspaceContextControls`. Kaynağın Şubeler ekranı kart
     * ızgarasıdır ve içinde seçici yoktur; sayfadaki kopya aynı işi ikinci kez
     * gösteriyordu.
     *
     * Sınanan sözleşme DEĞİŞMEDİ: birden çok şubesi olan bir markada seçim
     * erişilebilir bir kontrolle yapılır, ilk şube varsayılandır ve seçimi
     * değiştirmek menü ekranını o şubeye çevirir. Yalnız kontrolün yeri
     * ekranın içinden üst çubuğa taşındı.
     */
    it('exposes one accessible current-location control in the top bar, defaults to the first location, and switches MenuCatalogWorkspace locationId on Menu when the user picks the second', async () => {
        const user = userEvent.setup();
        const firstLocation = makeLocation({ id: 5101, display_name: 'Kadıköy Şubesi' });
        const secondLocation = makeLocation({ id: 5102, display_name: 'Beşiktaş Şubesi' });
        const fetchMock = buildFetchMock({
            brand: () => jsonResponse(200, makeBrand()),
            locations: () => jsonResponse(200, [firstLocation, secondLocation]),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menus' }));
        await screen.findByTestId('menu-catalog-workspace');
        expect(mockMenuCatalogWorkspaceProps?.locationId).toBe(firstLocation.id);

        await user.click(within(nav).getByRole('link', { name: 'Locations' }));

        const locationsPage = document.getElementById('section-locations');
        if (!locationsPage) {
            throw new Error('Locations page container not found');
        }
        const locationsPageScope = within(locationsPage);

        // Ekranın kendisi artık bir kart ızgarasıdır ve seçiciyi TAŞIMAZ.
        expect(locationsPageScope.queryByRole('combobox', { name: /location/i })).toBeNull();
        expect(locationsPageScope.getAllByTestId('brand-location-row')).toHaveLength(2);

        const locationCombobox = screen.getByRole('combobox', { name: 'Current location' });

        const firstOption = within(locationCombobox).getByRole('option', {
            name: firstLocation.display_name,
        });
        expect(firstOption).toHaveValue(String(firstLocation.id));

        const secondOption = within(locationCombobox).getByRole('option', {
            name: secondLocation.display_name,
        });
        expect(secondOption).toHaveValue(String(secondLocation.id));

        expect(locationCombobox).toHaveValue(String(firstLocation.id));

        await userEvent.selectOptions(locationCombobox, secondOption);

        await user.click(within(nav).getByRole('link', { name: 'Menus' }));

        await waitFor(() => {
            expect(mockMenuCatalogWorkspaceProps?.locationId).toBe(secondLocation.id);
        });

        vi.unstubAllGlobals();
    });
});
