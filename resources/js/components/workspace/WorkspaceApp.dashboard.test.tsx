import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * Freezes the current-workspace dashboard summary contract for WorkspaceApp
 * (S1-WP01A foundation): dashboard page header and category/menu-item
 * summary. The Location selector now lives on the exclusive Locations
 * (#locations) page rather than the Dashboard, so the switching test
 * selects there before returning to Dashboard
 * (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED).
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const LOCATION_ID = 923;

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

function makeLocation() {
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
    };
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
                    {
                        id: 102,
                        categoryId: 5,
                        productId: 902,
                        productName: 'Çay',
                        priceMinorAmount: 1500,
                        currencyCode: 'TRY',
                        position: 1,
                        allergens: [],
                        isVisible: false,
                    },
                ],
            },
            {
                id: 6,
                menuId: 42,
                name: 'Mains',
                position: 1,
                menuItems: [
                    {
                        id: 103,
                        categoryId: 6,
                        productId: 903,
                        productName: 'Tost',
                        priceMinorAmount: 8900,
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
            return jsonResponse(200, [makeLocation()]);
        }
        if (
            String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(200, makeMenuTree());
        }
        if (
            String(url) === `/api/workspaces/${WORKSPACE_ID}/menu/42/publications/current` &&
            method === 'GET'
        ) {
            return jsonResponse(200, { id: 55 });
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/qr-codes` &&
            method === 'GET'
        ) {
            return jsonResponse(200, [{ id: 1, state: 'active' }]);
        }

        throw new Error(`Unhandled fetch in WorkspaceApp dashboard test: ${method} ${String(url)}`);
    });
}

describe('WorkspaceApp — current workspace dashboard summary (S1-WP01A foundation, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('renders an accessible dashboard header with real category/menu-item counts, a row per real item, and keeps the existing menu catalog available below', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const dashboardDestination = document.querySelector('#section-dashboard');
        expect(dashboardDestination).not.toBeNull();

        expect(
            within(dashboardDestination as HTMLElement).getByText('Categories'),
        ).toBeInTheDocument();
        expect(within(dashboardDestination as HTMLElement).getByText('2')).toBeInTheDocument();

        expect(
            within(dashboardDestination as HTMLElement).getByText('Menu items'),
        ).toBeInTheDocument();
        expect(within(dashboardDestination as HTMLElement).getByText('3')).toBeInTheDocument();

        expect(
            within(dashboardDestination as HTMLElement).getByText('Visible items'),
        ).toBeInTheDocument();

        const dashboardMenuItemTable = within(dashboardDestination as HTMLElement).getByRole(
            'table',
            {
                name: 'Menu item list',
            },
        );
        expect(within(dashboardMenuItemTable).getByText('Kahve (Starters)')).toBeInTheDocument();
        expect(within(dashboardMenuItemTable).getByText('Çay (Starters)')).toBeInTheDocument();
        expect(within(dashboardMenuItemTable).getByText('Tost (Mains)')).toBeInTheDocument();

        expect(dashboardDestination?.querySelector('#menu-catalog')).toBeNull();

        vi.unstubAllGlobals();
    });

    it('switching Location on the Locations page to a second location with no menu replaces the first location dashboard data and drives MenuCatalogWorkspace with the second location id', async () => {
        const user = userEvent.setup();
        const SECOND_LOCATION_ID = 954;

        function makeSecondLocation() {
            return {
                id: SECOND_LOCATION_ID,
                workspace_id: WORKSPACE_ID,
                brand_id: 811,
                display_name: 'Beşiktaş',
                country_code: 'TR',
                city: 'İstanbul',
                address_line1: 'Barbaros Blv. 5',
                address_line2: null,
                postal_code: null,
            };
        }

        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
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

            throw new Error(
                `Unhandled fetch in WorkspaceApp dashboard test: ${method} ${String(url)}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const dashboardTable = screen.getByRole('table', { name: 'Menu item list' });
        expect(within(dashboardTable).getByText('Kahve (Starters)')).toBeInTheDocument();
        expect(within(dashboardTable).getByText('Çay (Starters)')).toBeInTheDocument();
        expect(within(dashboardTable).getByText('Tost (Mains)')).toBeInTheDocument();

        await user.click(screen.getByRole('link', { name: 'Locations' }));

        const locationSelect = screen.getByLabelText('Location') as HTMLSelectElement;
        fireEvent.change(locationSelect, { target: { value: String(SECOND_LOCATION_ID) } });

        await vi.waitFor(() => {
            expect(
                fetchMock.mock.calls.some(
                    ([calledUrl]) =>
                        String(calledUrl) ===
                        `/api/workspaces/${WORKSPACE_ID}/brand/locations/${SECOND_LOCATION_ID}/menu`,
                ),
            ).toBe(true);
        });

        await user.click(screen.getByRole('link', { name: 'Home' }));

        await vi.waitFor(() => {
            expect(screen.queryByText('Kahve (Starters)')).not.toBeInTheDocument();
        });
        expect(screen.queryByText('Çay (Starters)')).not.toBeInTheDocument();
        expect(screen.queryByText('Tost (Mains)')).not.toBeInTheDocument();
        expect(screen.queryByRole('table', { name: 'Menu item list' })).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders a resolved-empty state with an Open Menu CTA when the current location has no menu yet (DASHBOARD_EMPTY_SCOPE_FROZEN)', async () => {
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
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
                return jsonResponse(200, [makeLocation()]);
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(404, {});
            }
            if (method !== 'GET') {
                throw new Error(
                    `Unexpected mutation in dashboard resolved-empty test: ${method} ${String(url)}`,
                );
            }

            throw new Error(
                `Unhandled fetch in WorkspaceApp dashboard resolved-empty test: ${method} ${String(url)}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const dashboardDestination = document.querySelector('#section-dashboard') as HTMLElement;
        expect(dashboardDestination).not.toBeNull();

        await screen.findByText('No menu has been created for this location yet.');

        const openMenuLink = within(dashboardDestination).getByRole('link', { name: 'Open Menu' });
        expect(openMenuLink).toHaveAttribute('href', '#menu');

        expect(screen.queryByText('Loading your dashboard summary…')).not.toBeInTheDocument();
        expect(dashboardDestination.querySelector('table')).toBeNull();
        expect(within(dashboardDestination).queryByText('0')).not.toBeInTheDocument();

        expect(
            fetchMock.mock.calls.every(
                ([, callInit]) => (callInit?.method ?? 'GET').toUpperCase() === 'GET',
            ),
        ).toBe(true);

        vi.unstubAllGlobals();
    });

    it('DASHBOARD_SETUP_JOURNEY_RED renders a Dashboard Setup section with real brand/location/menu summary, Publication and QR not-connected-yet status, and no breakpoint tokens at 320 viewport', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        window.innerWidth = 320;
        window.dispatchEvent(new Event('resize'));

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const setupSection = await screen.findByRole('region', { name: 'Dashboard Setup' });

        expect(within(setupSection).getByText('Brand')).toBeInTheDocument();
        expect(within(setupSection).getByText('Location')).toBeInTheDocument();
        expect(within(setupSection).getByText('Menu')).toBeInTheDocument();
        expect(within(setupSection).getByText('Publication')).toBeInTheDocument();
        expect(within(setupSection).getByText('QR')).toBeInTheDocument();

        expect(within(setupSection).getByText('Zeytin')).toBeInTheDocument();
        expect(within(setupSection).getByText('Kadıköy')).toBeInTheDocument();
        expect(within(setupSection).getByText('2 categories · 3 items')).toBeInTheDocument();

        expect(await within(setupSection).findByText('Published #55')).toBeInTheDocument();
        expect(await within(setupSection).findByText('1 active QR')).toBeInTheDocument();
        expect(within(setupSection).queryByText('Not connected yet.')).toBeNull();

        expect(within(setupSection).queryByText(/%/)).not.toBeInTheDocument();

        const sectionClassName = setupSection.getAttribute('class') ?? '';
        expect(sectionClassName).not.toMatch(/(^|\s)(sm|md|lg|xl|2xl):/);

        expect(
            fetchMock.mock.calls.every(
                ([, callInit]) => (callInit?.method ?? 'GET').toUpperCase() === 'GET',
            ),
        ).toBe(true);

        vi.unstubAllGlobals();
    });

    it('DASHBOARD_SETUP_JOURNEY_RED keeps Brand and Location visible in Dashboard Setup and shows Menu as No menu yet when the current location has no menu', async () => {
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
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
                return jsonResponse(200, [makeLocation()]);
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(404, {});
            }
            if (method !== 'GET') {
                throw new Error(
                    `Unexpected mutation in DASHBOARD_SETUP_JOURNEY_RED resolved-empty test: ${method} ${String(url)}`,
                );
            }

            throw new Error(
                `Unhandled fetch in DASHBOARD_SETUP_JOURNEY_RED resolved-empty test: ${method} ${String(url)}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const setupSection = await screen.findByRole('region', { name: 'Dashboard Setup' });

        expect(within(setupSection).getByText('Zeytin')).toBeInTheDocument();
        expect(within(setupSection).getByText('Kadıköy')).toBeInTheDocument();
        expect(within(setupSection).getByText('No menu yet')).toBeInTheDocument();

        expect(
            fetchMock.mock.calls.every(
                ([, callInit]) => (callInit?.method ?? 'GET').toUpperCase() === 'GET',
            ),
        ).toBe(true);

        vi.unstubAllGlobals();
    });
});
