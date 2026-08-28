import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * Freezes the dashboard catalog-mutation sync contract for WorkspaceApp
 * (S1-WP01A foundation): WorkspaceApp wires MenuCatalogWorkspace's
 * onTreeChange signal so that the Dashboard's Visible items count and table
 * row for the mutated item update, without WorkspaceApp issuing an extra
 * GET to the dashboard menu-tree endpoint beyond the one initial load. The
 * mutation happens on the exclusive Menu (#menu) destination, so the test
 * mutates on Menu then returns to Dashboard to observe the sync
 * (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED).
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const LOCATION_ID = 923;
const MENU_ITEM_ID = 101;
const MENU_URL = `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/menu`;
const VISIBILITY_URL = `/api/workspaces/${WORKSPACE_ID}/menu-items/${MENU_ITEM_ID}/visibility`;

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
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
    };
}

function makeMenuTree(overrides: Partial<{ isVisible: boolean }> = {}) {
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
                        id: MENU_ITEM_ID,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: [],
                        isVisible: overrides.isVisible ?? true,
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
        if (String(url) === MENU_URL && method === 'GET') {
            return jsonResponse(200, makeMenuTree());
        }
        if (String(url) === VISIBILITY_URL && method === 'PUT') {
            return jsonResponse(200, { id: MENU_ITEM_ID, isVisible: false });
        }

        throw new Error(
            `Unhandled fetch in WorkspaceApp dashboardSync test: ${method} ${String(url)}`,
        );
    });
}

describe('WorkspaceApp — dashboard catalog-mutation sync (S1-WP01A foundation, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('updates the Dashboard Visible items count and table row after a Menu visibility toggle, without an extra dashboard menu-tree fetch', async () => {
        const user = userEvent.setup();
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType<typeof desktopChrome>;
        }>();
        render(<WorkspaceApp {...desktopChrome} />);

        expect(await screen.findByRole('heading', { name: 'Home' })).toBeInTheDocument();

        const dashboardTable = screen.getByRole('table', { name: 'Menu item list' });
        expect(within(dashboardTable).getByText('Yes')).toBeInTheDocument();

        expect(screen.getByText('Visible items')).toBeInTheDocument();
        expect(screen.getByText('1 / 1')).toBeInTheDocument();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Menus' }));

        const checkbox = await screen.findByRole('checkbox', { name: 'Show Kahve' });

        const menuTreeFetchCountBefore = fetchMock.mock.calls.filter(
            ([calledUrl, init]) =>
                String(calledUrl) === MENU_URL && (init?.method ?? 'GET').toUpperCase() === 'GET',
        ).length;

        fireEvent.click(checkbox);

        await waitFor(() => {
            expect(
                fetchMock.mock.calls.some(
                    ([calledUrl, init]) =>
                        String(calledUrl) === VISIBILITY_URL &&
                        (init?.method ?? 'GET').toUpperCase() === 'PUT',
                ),
            ).toBe(true);
        });

        await user.click(within(nav).getByRole('link', { name: 'Home' }));

        const updatedDashboardTable = screen.getByRole('table', { name: 'Menu item list' });
        await waitFor(() => {
            expect(within(updatedDashboardTable).getByText('No')).toBeInTheDocument();
        });

        await waitFor(() => {
            expect(screen.getByText('0 / 1')).toBeInTheDocument();
        });

        const menuTreeFetchCountAfter = fetchMock.mock.calls.filter(
            ([calledUrl, init]) =>
                String(calledUrl) === MENU_URL && (init?.method ?? 'GET').toUpperCase() === 'GET',
        ).length;
        expect(menuTreeFetchCountAfter).toBe(menuTreeFetchCountBefore);

        vi.unstubAllGlobals();
    });
});
