import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * RED test freezing the shell-level current-workspace / current-location
 * context contract for the AdminShell top bar (S1-WP01A frontend-first).
 * WorkspaceApp does not compose any of these top-bar controls yet, so every
 * assertion here must fail RED.
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
            throw new Error(
                `Unexpected search/notifications fetch in shell context RED test: ${method} ${String(url)}`,
            );
        }

        throw new Error(
            `Unhandled fetch in WorkspaceApp shell context test: ${method} ${String(url)}`,
        );
    });
}

async function renderCurrentWorkspace(
    locations: Array<Record<string, unknown>> = [makeLocation()],
) {
    const fetchMock = buildFetchMock(locations);
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    render(<WorkspaceApp />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return fetchMock;
}

describe('WorkspaceApp — AdminShell current-workspace / current-location context (S1-WP01A, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('exposes the real current-workspace name as a banner control that reaches the Choose a workspace screen', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const banner = screen.getByRole('banner');
        const workspaceControl = within(banner).getByRole('button', {
            name: 'Zeytin Restoranları',
        });

        await user.click(workspaceControl);

        expect(
            await screen.findByRole('heading', { name: 'Choose a workspace' }),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders an accessible "Current location" combobox in the banner with both server locations and the real first selection', async () => {
        await renderCurrentWorkspace([
            makeLocation({ id: 923, display_name: 'Kadıköy' }),
            makeLocation({ id: 924, display_name: 'Beşiktaş' }),
        ]);

        const banner = screen.getByRole('banner');
        const combobox = within(banner).getByRole('combobox', { name: 'Current location' });

        expect(within(combobox).getByRole('option', { name: 'Kadıköy' })).toBeInTheDocument();
        expect(within(combobox).getByRole('option', { name: 'Beşiktaş' })).toBeInTheDocument();
        expect((combobox as HTMLSelectElement).value).toBe('923');

        vi.unstubAllGlobals();
    });

    it('selecting the second real location updates the combobox and the AI command center shares the new selection without refetching the locations list', async () => {
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

        expect((combobox as HTMLSelectElement).value).toBe('924');

        const locationsListCallsAfter = fetchMock.mock.calls.filter(
            ([url]) => String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations`,
        ).length;
        expect(locationsListCallsAfter).toBe(locationsListCallsBefore);

        await user.click(screen.getByRole('button', { name: 'Open AI command center' }));
        const dialog = await screen.findByRole('dialog', { name: 'AI command center' });

        expect(within(dialog).getByText('Beşiktaş')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    // Bu test eskiden BUNUN TERSİNİ donduruyordu: arama ve bildirim
    // düğmeleri görünsün, devre dışı olsun, ipucunda "unavailable" yazsın.
    //
    // Bir kontrolü "dürüstçe devre dışı" göstermek, yapılmamış tarafı
    // kullanıcıya taşımaktır: nasıl etkinleştireceğini bilemez, çünkü
    // etkinleştirmenin bir yolu yoktur. Ana yüzeyde yalnız bugün iş gören
    // hedefler durur (`docs/44` devre dışı standardı; UX raporu §8.1).
    //
    // Arama ve bildirim gerçekten çalıştığında GÖRÜNÜR hâlde gelirler.
    it('shows no top-bar control that exists only to be disabled', async () => {
        const fetchMock = await renderCurrentWorkspace();
        const callCountAfterLoad = fetchMock.mock.calls.length;

        const banner = screen.getByRole('banner');

        expect(within(banner).queryByRole('button', { name: /search/i })).toBeNull();
        expect(within(banner).queryByRole('button', { name: /notification/i })).toBeNull();

        const disabled = within(banner)
            .queryAllByRole('button')
            .filter((control) => (control as HTMLButtonElement).disabled);
        expect(disabled).toEqual([]);

        expect(fetchMock.mock.calls.length).toBe(callCountAfterLoad);

        vi.unstubAllGlobals();
    });

    it('keeps exactly one "Open AI command center" launcher alongside the new banner controls', async () => {
        await renderCurrentWorkspace();

        expect(screen.getAllByRole('button', { name: 'Open AI command center' })).toHaveLength(1);
    });

    it('renders the real workspace control but no Current location combobox when the server returns zero locations', async () => {
        await renderCurrentWorkspace([]);

        const banner = screen.getByRole('banner');

        expect(
            within(banner).getByRole('button', { name: 'Zeytin Restoranları' }),
        ).toBeInTheDocument();
        expect(within(banner).queryByRole('combobox', { name: 'Current location' })).toBeNull();

        vi.unstubAllGlobals();
    });
});
