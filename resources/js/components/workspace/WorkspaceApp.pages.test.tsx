import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * LIVE_SIX_PAGE_BATCH_RED
 *
 * RED integration suite freezing the six visible admin destinations
 * (Dashboard, Brand, Locations, Menu, Media, Publication) for the
 * current-workspace AdminShell (S1-WP01A). None of the six page modules
 * exist yet under resources/js/components/workspace/pages, so both the
 * static imports below and the nav/content assertions must fail RED.
 *
 * No Storybook, no git, no backend/config edits. Frontend-only, real
 * WorkspaceApp composition with realistic API-shaped fetch mocks — no
 * invented ids, no fake mutation calls.
 */

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

// Frozen module-existence + export contract for the six real page modules.
// These imports alone must fail RED today: the pages directory does not exist.
import { DashboardPage } from './pages/DashboardPage';
import { BrandPage } from './pages/BrandPage';
import { LocationsPage } from './pages/LocationsPage';
import { MenuPage } from './pages/MenuPage';
import { MediaPage } from './pages/MediaPage';
import { PublicationPage } from './pages/PublicationPage';

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const LOCATION_ID = 923;
const SECOND_LOCATION_ID = 954;

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

function makeBrand() {
    return {
        id: 811,
        workspaceId: WORKSPACE_ID,
        name: 'Zeytin',
        slug: 'zeytin',
        locale: 'tr',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contactEmail: null,
        contactPhone: null,
    };
}

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: LOCATION_ID,
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

function makeSecondLocation() {
    return makeLocation({
        id: SECOND_LOCATION_ID,
        display_name: 'Beşiktaş',
        address_line1: 'Barbaros Blv. 5',
    });
}

function makeMenuTree() {
    return {
        id: 42,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'published',
        categories: [
            {
                id: 5,
                menuId: 42,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
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
            return jsonResponse(200, makeBrand());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation(), makeSecondLocation()]);
        }
        if (
            String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(200, makeMenuTree());
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/${SECOND_LOCATION_ID}/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(404, {});
        }

        throw new Error(`Unhandled fetch in WorkspaceApp pages test: ${method} ${String(url)}`);
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

describe('WorkspaceApp — six real admin page modules (S1-WP01A, LIVE_SIX_PAGE_BATCH_RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('exports real, distinct React components from the six page modules', () => {
        expect(typeof DashboardPage).toBe('function');
        expect(typeof BrandPage).toBe('function');
        expect(typeof LocationsPage).toBe('function');
        expect(typeof MenuPage).toBe('function');
        expect(typeof MediaPage).toBe('function');
        expect(typeof PublicationPage).toBe('function');

        const components = [
            DashboardPage,
            BrandPage,
            LocationsPage,
            MenuPage,
            MediaPage,
            PublicationPage,
        ];
        expect(new Set(components).size).toBe(6);
    });

    it('exposes six accessible nav destinations with exactly one aria-current=page and consistent hrefs', async () => {
        await renderCurrentWorkspace();

        const names = ['Dashboard', 'Brand', 'Locations', 'Menu', 'Media', 'Publication'];
        const hashes = ['#dashboard', '#brand', '#locations', '#menu', '#media', '#publication'];

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        const links = names.map((name) => within(nav).getByRole('link', { name }));

        links.forEach((link, index) => {
            expect(link).toHaveAttribute('href', hashes[index]);
        });

        const current = links.filter((link) => link.getAttribute('aria-current') === 'page');
        expect(current).toHaveLength(1);
        expect(current[0]).toHaveAttribute('href', '#dashboard');
    });

    it('renders a distinct real page root/heading per nav destination and hides the prior page', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const main = screen.getByRole('main');
        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        const destinations: Array<{ name: string; heading: string; id: string }> = [
            { name: 'Brand', heading: 'Brand', id: 'brand' },
            { name: 'Locations', heading: 'Locations', id: 'locations' },
            { name: 'Menu', heading: 'Menu', id: 'menu' },
            { name: 'Media', heading: 'Media', id: 'media' },
            { name: 'Publication', heading: 'Publication', id: 'publication' },
            { name: 'Dashboard', heading: 'Dashboard', id: 'dashboard' },
        ];

        for (const destination of destinations) {
            await user.click(within(nav).getByRole('link', { name: destination.name }));

            const root = main.querySelector(`#${destination.id}`);
            expect(root).not.toBeNull();
            expect(
                within(root as HTMLElement).getByRole('heading', { name: destination.heading }),
            ).toBeInTheDocument();

            destinations
                .filter((other) => other.id !== destination.id)
                .forEach((other) => {
                    expect(main.querySelector(`#${other.id}`)).toBeNull();
                });
        }
    });

    it('Brand page renders literal server-returned brand data from GET brand', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Brand' }));

        const brandRoot = document.querySelector('#brand') as HTMLElement;
        expect(within(brandRoot).getByText('Zeytin')).toBeInTheDocument();
        expect(within(brandRoot).getByText('TRY')).toBeInTheDocument();
        expect(within(brandRoot).getByText('Europe/Istanbul')).toBeInTheDocument();
    });

    it('Locations page renders all literal server-returned locations and supports selecting the current location without invented ids', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('link', { name: 'Locations' }));

        const locationsRoot = document.querySelector('#locations') as HTMLElement;
        expect(within(locationsRoot).getByText('Kadıköy')).toBeInTheDocument();
        expect(within(locationsRoot).getByText('Bahariye Cd. 1')).toBeInTheDocument();
        expect(within(locationsRoot).getByText('Beşiktaş')).toBeInTheDocument();
        expect(within(locationsRoot).getByText('Barbaros Blv. 5')).toBeInTheDocument();

        const locationSelect = within(locationsRoot).getByLabelText(
            'Location',
        ) as HTMLSelectElement;
        expect(Array.from(locationSelect.options).map((option) => option.value)).toEqual(
            expect.arrayContaining([String(LOCATION_ID), String(SECOND_LOCATION_ID)]),
        );
        expect(locationSelect.value).toBe(String(LOCATION_ID));
    });

    it('Dashboard and Menu pages represent the current real menu catalog behavior', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const dashboardRoot = document.querySelector('#dashboard') as HTMLElement;
        expect(within(dashboardRoot).getByText('Categories')).toBeInTheDocument();
        expect(within(dashboardRoot).getByText('Menu items')).toBeInTheDocument();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menu' }));

        const menuRoot = document.querySelector('#menu') as HTMLElement;
        expect(within(menuRoot).getByTestId('menu-catalog-workspace')).toBeInTheDocument();
        expect(within(menuRoot).getByTestId('menu-catalog-workspace')).toHaveAttribute(
            'data-location-id',
            String(LOCATION_ID),
        );
    });

    it('Media and Publication pages show honest unavailable/empty states with no fake records or mutation calls', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();
        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        await user.click(within(nav).getByRole('link', { name: 'Media' }));
        const mediaRoot = document.querySelector('#media') as HTMLElement;
        expect(
            within(mediaRoot).getAllByText(/not available|no media|unavailable/i).length,
        ).toBeGreaterThan(0);
        expect(within(mediaRoot).queryByRole('table')).not.toBeInTheDocument();

        await user.click(within(nav).getByRole('link', { name: 'Publication' }));
        const publicationRoot = document.querySelector('#publication') as HTMLElement;
        expect(
            within(publicationRoot).getAllByText(/not available|no publication|unavailable/i)
                .length,
        ).toBeGreaterThan(0);

        const mutationCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method !== 'GET';
        });
        expect(mutationCalls).toHaveLength(0);
    });

    it('at a simulated 320x480 start, all six pages remain reachable through the mobile AdminShell as fluid page roots with no breakpoint contract', async () => {
        const user = userEvent.setup();
        setViewport(320, 480);

        await renderCurrentWorkspace();

        const names = ['Brand', 'Locations', 'Menu', 'Media', 'Publication', 'Dashboard'];
        const ids = ['brand', 'locations', 'menu', 'media', 'publication', 'dashboard'];

        for (let i = 0; i < names.length; i += 1) {
            await user.click(screen.getByRole('button', { name: 'Open menu' }));
            const dialog = screen.getByRole('dialog');

            await user.click(within(dialog).getByRole('link', { name: names[i] }));

            const root = document.querySelector(`#${ids[i]}`) as HTMLElement | null;
            expect(root).not.toBeNull();
        }
    });

    // ---------------------------------------------------------------------
    // SPEED_BATCH_STAGE2_RED
    //
    // Extends the same batch-level RED suite: Brand edit fields, Locations
    // Add-location bootstrap/POST/append contract, and a shared contextual
    // AI assistant affordance required on all six pages. Existing
    // assertions above are unchanged.
    // ---------------------------------------------------------------------

    it('Brand page Edit exposes real Name, Locale, Timezone, Currency, Description, Contact email, Contact phone fields and Save, with no mutation before explicit Save', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Brand' }));
        const brandRoot = document.querySelector('#brand') as HTMLElement;
        const scope = within(brandRoot);

        await user.click(await scope.findByRole('button', { name: 'Edit' }));

        expect(scope.getByLabelText('Name')).toBeInTheDocument();
        expect(scope.getByLabelText('Locale')).toBeInTheDocument();
        expect(scope.getByLabelText('Timezone')).toBeInTheDocument();
        expect(scope.getByLabelText('Currency')).toBeInTheDocument();
        expect(scope.getByLabelText('Description')).toBeInTheDocument();
        expect(scope.getByLabelText('Contact email')).toBeInTheDocument();
        expect(scope.getByLabelText('Contact phone')).toBeInTheDocument();

        expect(scope.getByRole('button', { name: 'Save' })).toBeInTheDocument();

        const mutationCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method !== 'GET';
        });
        expect(mutationCalls).toHaveLength(0);
    });

    it('Locations page Add location reveals the real LocationOnboardingForm fields and Create, and submitting bootstraps CSRF then POSTs exactly once to the current workspace locations endpoint, appending the server-returned location', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();

        await user.click(screen.getByRole('link', { name: 'Locations' }));
        const locationsRoot = document.querySelector('#locations') as HTMLElement;
        const scope = within(locationsRoot);

        await user.click(await scope.findByRole('button', { name: 'Add location' }));

        expect(scope.getByLabelText('Display name')).toBeInTheDocument();
        expect(scope.getByLabelText('Country')).toBeInTheDocument();
        expect(scope.getByLabelText('City')).toBeInTheDocument();
        expect(scope.getByLabelText('Address line 1')).toBeInTheDocument();
        expect(scope.getByLabelText('Address line 2')).toBeInTheDocument();
        expect(scope.getByLabelText('Postal code')).toBeInTheDocument();
        expect(scope.getByRole('button', { name: 'Create' })).toBeInTheDocument();

        const THIRD_LOCATION_ID = 977;
        const createdLocation = makeLocation({
            id: THIRD_LOCATION_ID,
            display_name: 'Şişli',
            country_code: 'TR',
            city: 'İstanbul',
            address_line1: 'Halaskargazi Cd. 9',
            address_line2: null,
            postal_code: null,
        });

        fetchMock.mockImplementation(async (url: string, init?: RequestInit) => {
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
                return jsonResponse(200, makeBrand());
            }
            if (
                String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` &&
                method === 'GET'
            ) {
                return jsonResponse(200, [makeLocation(), makeSecondLocation()]);
            }
            if (
                String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` &&
                method === 'POST'
            ) {
                return jsonResponse(201, createdLocation);
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(200, makeMenuTree());
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${SECOND_LOCATION_ID}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(404, {});
            }

            throw new Error(`Unhandled fetch in WorkspaceApp pages test: ${method} ${String(url)}`);
        });

        await user.type(scope.getByLabelText('Display name'), 'Şişli');
        await user.type(scope.getByLabelText('Country'), 'TR');
        await user.type(scope.getByLabelText('City'), 'İstanbul');
        await user.type(scope.getByLabelText('Address line 1'), 'Halaskargazi Cd. 9');

        await user.click(scope.getByRole('button', { name: 'Create' }));

        await scope.findByText('Şişli');

        const postCalls = fetchMock.mock.calls.filter(
            ([calledUrl, init]) =>
                String(calledUrl) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` &&
                ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
        );
        expect(postCalls).toHaveLength(1);

        const [, postInit] = postCalls[0];
        expect((postInit as RequestInit).credentials).toBe('include');

        const postHeaders = new Headers((postInit as RequestInit).headers);
        expect(postHeaders.get('Accept')).toBe('application/json');
        expect(postHeaders.get('Content-Type')).toBe('application/json');

        const postBody = JSON.parse(String((postInit as RequestInit).body));
        expect(postBody).toEqual({
            display_name: 'Şişli',
            country_code: 'TR',
            city: 'İstanbul',
            address_line1: 'Halaskargazi Cd. 9',
        });

        expect(scope.getByText('Kadıköy')).toBeInTheDocument();
        expect(scope.getByText('Beşiktaş')).toBeInTheDocument();
        expect(scope.getByText('Şişli')).toBeInTheDocument();

        const locationSelect = scope.getByLabelText('Location') as HTMLSelectElement;
        expect(Array.from(locationSelect.options).map((option) => option.value)).toEqual(
            expect.arrayContaining([
                String(LOCATION_ID),
                String(SECOND_LOCATION_ID),
                String(THIRD_LOCATION_ID),
            ]),
        );
    });

    it('every one of the six pages exposes the shared contextual AI assistant contract: command field, honest unavailable state, Why this suggestion, affected-records summary, and a disabled Review and approve control, with no mutation from navigation alone', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();

        const destinations: Array<{ name: string; id: string }> = [
            { name: 'Dashboard', id: 'dashboard' },
            { name: 'Brand', id: 'brand' },
            { name: 'Locations', id: 'locations' },
            { name: 'Menu', id: 'menu' },
            { name: 'Media', id: 'media' },
            { name: 'Publication', id: 'publication' },
        ];

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });

        for (const destination of destinations) {
            await user.click(within(nav).getByRole('link', { name: destination.name }));

            const root = document.querySelector(`#${destination.id}`) as HTMLElement;
            const scope = within(root);

            expect(scope.getByLabelText(/AI command/i)).toBeInTheDocument();
            expect(
                scope.getByText(/no real AI|AI (is )?not available|AI.*unavailable/i),
            ).toBeInTheDocument();
            expect(scope.getByText(/why this suggestion/i)).toBeInTheDocument();
            expect(scope.getByText(/affected records/i)).toBeInTheDocument();

            const reviewButton = scope.getByRole('button', { name: /review and approve/i });
            expect(reviewButton).toBeDisabled();
        }

        const mutationCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method !== 'GET';
        });
        expect(mutationCalls).toHaveLength(0);
    });

    // ---------------------------------------------------------------------
    // SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED
    //
    // Freezes that the initial URL hash selects the matching page and
    // aria-current after render, so a browser refresh on e.g. #brand does
    // not silently show Dashboard under a different hash. Unknown/missing
    // hash safely falls back to Dashboard. WorkspaceApp today always starts
    // on 'dashboard' regardless of window.location.hash, so this is
    // expected RED until initial-hash routing is wired.
    // ---------------------------------------------------------------------

    // ---------------------------------------------------------------------
    // PUBLICATION_DRAFT_PREVIEW_RED
    //
    // WorkspaceApp already holds dashboardMenuTree in state (used by
    // DashboardPage) but today renders <PublicationPage /> with zero props,
    // so Publication cannot show a Draft preview of the current tree.
    // ---------------------------------------------------------------------

    it('passes the current loaded dashboard menu tree through to PublicationPage so its Draft preview reflects the same server data as Dashboard', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Publication' }));

        const publicationRoot = document.querySelector('#publication') as HTMLElement;
        const region = within(publicationRoot).getByRole('region', {
            name: /draft.*preview|preview.*draft/i,
        });

        expect(within(region).getByText('Ana Menü')).toBeInTheDocument();
        expect(within(region).getByText('Kahve')).toBeInTheDocument();
    });

    // ---------------------------------------------------------------------
    // PUBLICATION_REAL_RED (S1-WP04a)
    //
    // WorkspaceApp already holds the current workspace id in state (used to
    // build every other page's API URLs) but today renders
    // <PublicationPage dashboardMenuTree={...} /> with no workspaceId prop,
    // so PublicationPage cannot call the real
    // /api/workspaces/{workspace}/menu/{menu}/publications* routes.
    // ---------------------------------------------------------------------

    it('passes the current workspace id and real menu id through to PublicationPage so it can call the real publication API', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Publication' }));

        await waitFor(() => {
            const publicationCall = fetchMock.mock.calls.find(
                ([url]) =>
                    String(url).includes(`/api/workspaces/${WORKSPACE_ID}/menu/`) &&
                    String(url).includes('/publications/current'),
            );
            expect(publicationCall).toBeDefined();
        });
    });

    describe('initial URL hash selects the matching page (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED)', () => {
        const hashToDestination: Array<{ hash: string; name: string; id: string }> = [
            { hash: '#dashboard', name: 'Dashboard', id: 'dashboard' },
            { hash: '#brand', name: 'Brand', id: 'brand' },
            { hash: '#locations', name: 'Locations', id: 'locations' },
            { hash: '#menu', name: 'Menu', id: 'menu' },
            { hash: '#media', name: 'Media', id: 'media' },
            { hash: '#publication', name: 'Publication', id: 'publication' },
        ];

        afterEach(() => {
            history.replaceState(null, '', window.location.pathname);
        });

        for (const destination of hashToDestination) {
            it(`starting at ${destination.hash} selects the ${destination.name} page and its nav aria-current after render`, async () => {
                window.location.hash = destination.hash;

                await renderCurrentWorkspace();

                const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
                const link = within(nav).getByRole('link', { name: destination.name });

                await screen
                    .findByText(destination.name, { selector: 'h2' })
                    .catch(() => undefined);

                expect(link).toHaveAttribute('aria-current', 'page');

                const otherLinks = hashToDestination
                    .filter((other) => other.id !== destination.id)
                    .map((other) => within(nav).getByRole('link', { name: other.name }));
                otherLinks.forEach((otherLink) => {
                    expect(otherLink).not.toHaveAttribute('aria-current', 'page');
                });

                const root = document.querySelector(`#${destination.id}`);
                expect(root).not.toBeNull();
            });
        }

        it('an unknown initial hash safely falls back to Dashboard', async () => {
            window.location.hash = '#does-not-exist';

            await renderCurrentWorkspace();

            const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
            const dashboardLink = within(nav).getByRole('link', { name: 'Dashboard' });

            expect(dashboardLink).toHaveAttribute('aria-current', 'page');
            expect(document.querySelector('#dashboard')).not.toBeNull();
        });
    });
});
