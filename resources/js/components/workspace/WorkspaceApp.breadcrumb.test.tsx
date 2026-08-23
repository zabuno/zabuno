import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED test freezing the accessible Breadcrumb contract inside <main> for
 * WorkspaceApp (S1-WP01A frontend-first). WorkspaceApp does not compose any
 * breadcrumb yet, so every assertion here must fail RED.
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
const SEARCH_OR_NOTIFICATIONS_FETCH_PATTERN = /search|notification/i;

function importWorkspaceModule<T extends Record<string, unknown> = Record<string, unknown>>(): Promise<T> {
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
    return { id: WORKSPACE_ID, name: 'Zeytin Restoranları', slug: 'zeytin-restoranlari', state: 'active' };
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

function buildFetchMock(locations: Array<Record<string, unknown>>) {
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
            return jsonResponse(200, locations);
        }

        const menuMatch = String(url).match(
            new RegExp(`^/api/workspaces/${WORKSPACE_ID}/brand/locations/(\\d+)/menu$`),
        );
        if (menuMatch && method === 'GET') {
            return jsonResponse(200, { locationId: Number(menuMatch[1]), categories: [] });
        }

        if (SEARCH_OR_NOTIFICATIONS_FETCH_PATTERN.test(String(url))) {
            throw new Error(`Unexpected search/notifications fetch in breadcrumb RED test: ${method} ${String(url)}`);
        }

        throw new Error(`Unhandled fetch in WorkspaceApp breadcrumb test: ${method} ${String(url)}`);
    });
}

async function renderCurrentWorkspace(locations: Array<Record<string, unknown>> = [makeLocation()]) {
    const fetchMock = buildFetchMock(locations);
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    render(<WorkspaceApp />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return fetchMock;
}

function getBreadcrumbWithin(main: HTMLElement) {
    return within(main).getByRole('navigation', { name: 'Breadcrumb' });
}

describe('WorkspaceApp — main breadcrumb (S1-WP01A, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('shows the real workspace, selected real location, and Dashboard as the final active crumb', async () => {
        await renderCurrentWorkspace([
            makeLocation({ id: 923, display_name: 'Kadıköy' }),
            makeLocation({ id: 924, display_name: 'Beşiktaş' }),
        ]);

        const main = screen.getByRole('main');
        const breadcrumb = getBreadcrumbWithin(main);

        expect(within(breadcrumb).getByText('Zeytin Restoranları')).toBeInTheDocument();
        expect(within(breadcrumb).getByText('Kadıköy')).toBeInTheDocument();

        const activeCrumb = within(breadcrumb).getByText('Dashboard');
        expect(activeCrumb).toHaveAttribute('aria-current', 'page');
        expect(activeCrumb.tagName).not.toBe('A');
        expect(within(breadcrumb).queryByRole('link', { name: 'Dashboard' })).toBeNull();

        vi.unstubAllGlobals();
    });

    it('updates the breadcrumb location crumb when the top-bar Current location combobox changes, without a second locations-list fetch', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace([
            makeLocation({ id: 923, display_name: 'Kadıköy' }),
            makeLocation({ id: 924, display_name: 'Beşiktaş' }),
        ]);

        const banner = screen.getByRole('banner');
        const combobox = within(banner).getByRole('combobox', { name: 'Current location' });

        const locationsListCallsBefore = fetchMock.mock.calls.filter(
            ([url]) => String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations`,
        ).length;

        await user.selectOptions(combobox, 'Beşiktaş');

        const main = screen.getByRole('main');
        const breadcrumb = getBreadcrumbWithin(main);

        expect(within(breadcrumb).getByText('Beşiktaş')).toBeInTheDocument();
        expect(within(breadcrumb).queryByText('Kadıköy')).toBeNull();

        const locationsListCallsAfter = fetchMock.mock.calls.filter(
            ([url]) => String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations`,
        ).length;
        expect(locationsListCallsAfter).toBe(locationsListCallsBefore);

        vi.unstubAllGlobals();
    });

    it('updates the final crumb to the real selected sidebar section', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const locationsLinks = screen.getAllByRole('link', { name: 'Locations' });
        await user.click(locationsLinks[0]);

        const main = screen.getByRole('main');
        const breadcrumb = getBreadcrumbWithin(main);

        const activeCrumb = within(breadcrumb).getByText('Locations');
        expect(activeCrumb).toHaveAttribute('aria-current', 'page');
        expect(within(breadcrumb).queryByText('Dashboard')).toBeNull();

        vi.unstubAllGlobals();
    });

    it('activating the selected-location breadcrumb link navigates to Locations and leaves the hash at #locations', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace([makeLocation({ id: 923, display_name: 'Kadıköy' })]);

        const main = screen.getByRole('main');
        const breadcrumb = getBreadcrumbWithin(main);
        const locationLink = within(breadcrumb).getByRole('link', { name: 'Kadıköy' });

        await user.click(locationLink);

        const activeCrumb = within(breadcrumb).getByText('Locations');
        expect(activeCrumb).toHaveAttribute('aria-current', 'page');
        expect(window.location.hash).toBe('#locations');

        vi.unstubAllGlobals();
    });

    it('renders no breadcrumb before a workspace has loaded', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async (url: string) => {
                if (String(url) === CSRF_COOKIE_URL) {
                    return jsonResponse(204, {});
                }

                return new Promise(() => {
                    // Never resolves: keeps WorkspaceApp in the loading phase.
                });
            }),
        );

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
        render(<WorkspaceApp />);

        expect(screen.queryByRole('navigation', { name: 'Breadcrumb' })).toBeNull();

        vi.unstubAllGlobals();
    });
});
