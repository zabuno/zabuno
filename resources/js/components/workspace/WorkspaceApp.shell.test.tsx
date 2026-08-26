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
        history.replaceState(null, '', window.location.pathname);
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

    // Gezinti bağlantıları `#media` gibi hash'ler ve her bölüm aynı id'yi
    // taşıyan bir kapsayıcı render ediyor; tarayıcı bu durumda o elemana
    // kaydırır ve gezinti tıklaması sayfayı sıçratır. Bölüm bir "sayfa"dır,
    // yeni sayfa baştan başlar.
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
                'Bölüm değiştiğinde sayfa başa dönmeli; aksi hâlde tarayıcı hash hedefine sıçrar.',
            ).toHaveBeenCalledWith(expect.objectContaining({ top: 0 }));
        });

        // Yumuşak kaydırma her gezinmede gürültüdür ve azaltılmış hareket
        // tercihini çiğner.
        expect(scrollTo).not.toHaveBeenCalledWith(expect.objectContaining({ behavior: 'smooth' }));

        vi.unstubAllGlobals();
    });

    it('transitions to the existing choose-workspace journey when Switch workspace is activated', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Switch workspace' }));

        expect(screen.getByRole('heading', { name: 'Choose a workspace' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('shows Dashboard active by default, with a Dashboard nav item, a #dashboard destination that does not host the catalog, and a separate #menu destination for the Menu nav link once selected', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        const dashboardLink = within(nav).getByRole('link', { name: 'Dashboard' });
        expect(dashboardLink).toHaveAttribute('href', '#dashboard');
        expect(dashboardLink).toHaveAttribute('aria-current', 'page');

        const menuLink = within(nav).getByRole('link', { name: 'Menu' });
        expect(menuLink).toHaveAttribute('href', '#menu');
        expect(menuLink).not.toHaveAttribute('aria-current', 'page');

        const main = screen.getByRole('main');
        expect(main.querySelector('#dashboard')).not.toBeNull();
        expect(main.querySelector('#menu')).toBeNull();

        await user.click(menuLink);

        expect(main.querySelector('#dashboard')).toBeNull();
        expect(main.querySelector('#menu')).not.toBeNull();

        const catalogDestination = within(main)
            .getByTestId('menu-catalog-workspace')
            .closest('#menu');
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
        expect(main.querySelector('#menu')).not.toBeNull();

        vi.unstubAllGlobals();
    });
});
