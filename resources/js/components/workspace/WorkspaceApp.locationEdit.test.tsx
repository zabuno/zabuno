import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, within } from '@testing-library/react';

/**
 * Freezes the per-location Edit contract on the exclusive Locations
 * (#locations) page for WorkspaceApp (S1-WP01A foundation): a scoped
 * per-location Edit control and location edit fields per real location row.
 * Split from the legacy combined "Brand & Locations" nav item/#brand-locations
 * root (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED) — this now navigates
 * via the real "Locations" nav link with no alias link.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 61;
const LOCATION_ID_A = 811;
const LOCATION_ID_B = 812;

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
    return { id: 1, name: 'Grace Hopper', email: 'grace@example.com' };
}

function makeWorkspace() {
    return { id: WORKSPACE_ID, name: 'Menekşe Kahve', slug: 'menekse-kahve', state: 'active' };
}

function makeBrand() {
    return {
        id: 501,
        workspace_id: WORKSPACE_ID,
        name: 'Menekşe',
        slug: 'menekse',
        locale: 'tr',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: 'Semt kahvecisi, 1998den beri.',
        contact_email: 'iletisim@menekse.example',
        contact_phone: '+90 212 555 01 01',
    };
}

function makeLocationA() {
    return {
        id: LOCATION_ID_A,
        workspace_id: WORKSPACE_ID,
        brand_id: 501,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Moda Cd. 12',
        address_line2: null,
        postal_code: null,
    };
}

function makeLocationB() {
    return {
        id: LOCATION_ID_B,
        workspace_id: WORKSPACE_ID,
        brand_id: 501,
        display_name: 'Beşiktaş Şube',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Barbaros Blv. 30',
        address_line2: 'Kat 2',
        postal_code: '34353',
    };
}

function baseHandlers(url: string, method: string): Response | undefined {
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
        return jsonResponse(200, [makeLocationA(), makeLocationB()]);
    }
    if (
        String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}/menu` &&
        method === 'GET'
    ) {
        return jsonResponse(404, {});
    }
    if (
        String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_B}/menu` &&
        method === 'GET'
    ) {
        return jsonResponse(404, {});
    }
    return undefined;
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const handled = baseHandlers(url, method);
        if (handled) {
            return handled;
        }
        throw new Error(
            `Unhandled fetch in WorkspaceApp locationEdit test: ${method} ${String(url)}`,
        );
    });
}

async function renderOnLocationsPage(fetchMock: ReturnType<typeof buildFetchMock>) {
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>();
    render(<WorkspaceApp />);

    const navLink = await screen.findByRole('link', { name: 'Locations' });
    fireEvent.click(navLink);

    const destination = document.querySelector('#section-locations') as HTMLElement;
    expect(destination).not.toBeNull();

    return within(destination);
}

function locationRows(scope: ReturnType<typeof within>): HTMLElement[] {
    return scope.getAllByTestId('brand-location-row');
}

function editButtonName(location: { display_name: string }): string {
    return `Edit ${location.display_name}`;
}

describe('WorkspaceApp — Locations page per-location Edit (S1-WP01A foundation)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('each real location row has its own scoped Edit control', async () => {
        const fetchMock = buildFetchMock();
        const scope = await renderOnLocationsPage(fetchMock);

        const rows = locationRows(scope);
        expect(rows).toHaveLength(2);

        const locations = [makeLocationA(), makeLocationB()];
        rows.forEach((row: HTMLElement, index: number) => {
            expect(
                within(row).getByRole('button', { name: editButtonName(locations[index]) }),
            ).toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    it('clicking the first row Edit reveals fields prefilled from that location only', async () => {
        const fetchMock = buildFetchMock();
        const scope = await renderOnLocationsPage(fetchMock);
        const locationA = makeLocationA();

        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(firstRowScope.getByRole('button', { name: editButtonName(locationA) }));

        expect(
            (await firstRowScope.findByLabelText('Display name')) as HTMLInputElement,
        ).toHaveValue(locationA.display_name);
        expect(firstRowScope.getByLabelText('Country code')).toHaveValue(locationA.country_code);
        expect(firstRowScope.getByLabelText('City')).toHaveValue(locationA.city);
        expect(firstRowScope.getByLabelText('Address line 1')).toHaveValue(locationA.address_line1);
        expect(firstRowScope.getByLabelText('Address line 2')).toHaveValue('');
        expect(firstRowScope.getByLabelText('Postal code')).toHaveValue('');

        const secondRowScope = within(rows[1]);
        expect(secondRowScope.queryByLabelText('Display name')).not.toBeInTheDocument();
        expect(
            secondRowScope.getByRole('button', { name: editButtonName(makeLocationB()) }),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('Cancel closes the location edit form without issuing any write request and restores the row', async () => {
        const fetchMock = buildFetchMock();
        const scope = await renderOnLocationsPage(fetchMock);

        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        await firstRowScope.findByLabelText('Display name');

        fireEvent.click(firstRowScope.getByRole('button', { name: 'Cancel' }));

        expect(firstRowScope.queryByLabelText('Display name')).not.toBeInTheDocument();
        expect(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        ).toBeInTheDocument();
        expect(firstRowScope.getByText(makeLocationA().display_name)).toBeInTheDocument();

        const writeCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method === 'PUT' || method === 'POST';
        });
        expect(writeCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('Save and Cancel are disabled while the PUT is pending', async () => {
        let resolvePut: ((value: Response) => void) | undefined;
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                method === 'PUT'
            ) {
                return new Promise<Response>((resolve) => {
                    resolvePut = resolve;
                });
            }
            const handled = baseHandlers(url, method);
            if (handled) {
                return handled;
            }
            throw new Error(
                `Unhandled fetch in WorkspaceApp locationEdit pending test: ${method} ${String(url)}`,
            );
        });

        const scope = await renderOnLocationsPage(fetchMock);
        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        await firstRowScope.findByLabelText('Display name');

        const saveButton = firstRowScope.getByRole('button', { name: 'Save' });
        const cancelButton = firstRowScope.getByRole('button', { name: 'Cancel' });
        expect(saveButton).not.toBeDisabled();
        expect(cancelButton).not.toBeDisabled();

        fireEvent.click(saveButton);

        expect(saveButton).toBeDisabled();
        expect(cancelButton).toBeDisabled();

        resolvePut?.(jsonResponse(200, makeLocationA()));

        vi.unstubAllGlobals();
    });

    it('Save bootstraps the CSRF cookie then PUTs exactly once with credentials, headers, and exact payload, updating only that row in-place with no /api/user or /api/workspace-context refetch', async () => {
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (String(url) === CSRF_COOKIE_URL) {
                document.cookie = 'XSRF-TOKEN=test-xsrf-token';
                return jsonResponse(204, {});
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                method === 'PUT'
            ) {
                return jsonResponse(200, {
                    ...makeLocationA(),
                    display_name: 'Kadıköy Şube Güncel',
                    address_line1: 'Moda Cd. 14',
                    address_line2: 'Zemin kat',
                    postal_code: '34710',
                });
            }
            const handled = baseHandlers(url, method);
            if (handled) {
                return handled;
            }
            throw new Error(
                `Unhandled fetch in WorkspaceApp locationEdit save test: ${method} ${String(url)}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC';

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType;
        }>();
        render(<WorkspaceApp />);
        const navLink = await screen.findByRole('link', { name: 'Locations' });
        fireEvent.click(navLink);
        const destination = document.querySelector('#section-locations') as HTMLElement;
        const scope = within(destination);

        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        const displayNameInput = await firstRowScope.findByLabelText('Display name');

        fireEvent.change(displayNameInput, { target: { value: 'Kadıköy Şube Güncel' } });
        fireEvent.change(firstRowScope.getByLabelText('Address line 1'), {
            target: { value: 'Moda Cd. 14' },
        });
        fireEvent.change(firstRowScope.getByLabelText('Address line 2'), {
            target: { value: 'Zemin kat' },
        });
        fireEvent.change(firstRowScope.getByLabelText('Postal code'), {
            target: { value: '34710' },
        });

        const saveButton = firstRowScope.getByRole('button', { name: 'Save' });
        fireEvent.click(saveButton);

        const putCalls = await (async () => {
            const deadline = Date.now() + 2000;
            while (Date.now() < deadline) {
                const calls = fetchMock.mock.calls.filter(
                    ([url, requestInit]) =>
                        String(url) ===
                            `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                        (
                            (requestInit as RequestInit | undefined)?.method ?? 'GET'
                        ).toUpperCase() === 'PUT',
                );
                if (calls.length > 0) {
                    return calls;
                }
                await new Promise((resolve) => setTimeout(resolve, 10));
            }
            return [];
        })();

        expect(putCalls).toHaveLength(1);
        const [, putInit] = putCalls[0];
        const putRequestInit = putInit as RequestInit;
        expect(putRequestInit.credentials).toBe('include');
        const putHeaders = new Headers(putRequestInit.headers);
        expect(putHeaders.get('Content-Type')).toBe('application/json');
        expect(putHeaders.get('X-XSRF-TOKEN')).toBe('test-xsrf-token');
        expect(JSON.parse(String(putRequestInit.body))).toEqual({
            display_name: 'Kadıköy Şube Güncel',
            country_code: 'TR',
            city: 'İstanbul',
            address_line1: 'Moda Cd. 14',
            address_line2: 'Zemin kat',
            postal_code: '34710',
        });

        const csrfCallIndex = fetchMock.mock.calls.findIndex(
            ([url]) => String(url) === CSRF_COOKIE_URL,
        );
        const putCallIndex = fetchMock.mock.calls.findIndex(
            ([url, requestInit]) =>
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                ((requestInit as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(csrfCallIndex).toBeGreaterThanOrEqual(0);
        expect(csrfCallIndex).toBeLessThan(putCallIndex);

        const updatedRows = locationRows(scope);
        const updatedFirstRowScope = within(updatedRows[0]);
        expect(await updatedFirstRowScope.findByText('Kadıköy Şube Güncel')).toBeInTheDocument();
        expect(updatedFirstRowScope.getByText('Moda Cd. 14')).toBeInTheDocument();

        const secondRowScope = within(updatedRows[1]);
        const locationB = makeLocationB();
        expect(secondRowScope.getByText(locationB.display_name)).toBeInTheDocument();
        expect(secondRowScope.getByText(locationB.address_line1)).toBeInTheDocument();

        const userRefetches = fetchMock.mock.calls.filter(([url]) => String(url) === '/api/user');
        const contextRefetches = fetchMock.mock.calls.filter(
            ([url]) => String(url) === '/api/workspace-context',
        );
        expect(userRefetches).toHaveLength(1);
        expect(contextRefetches).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('a 422 validation response shows role=alert scoped to the row and preserves all row values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                method === 'PUT'
            ) {
                return jsonResponse(422, {
                    message: 'The display name field is required.',
                    errors: { display_name: ['The display name field is required.'] },
                });
            }
            return fetchMock(url, init);
        });

        const scope = await renderOnLocationsPage(withPut);
        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        await firstRowScope.findByLabelText('Display name');
        fireEvent.click(firstRowScope.getByRole('button', { name: 'Save' }));

        expect(await firstRowScope.findByRole('alert')).toBeInTheDocument();

        fireEvent.click(firstRowScope.getByRole('button', { name: 'Cancel' }));

        const restoredRows = locationRows(scope);
        for (const [row, location] of [
            [restoredRows[0], makeLocationA()],
            [restoredRows[1], makeLocationB()],
        ] as const) {
            const rowScope = within(row);
            expect(rowScope.getByText(location.display_name)).toBeInTheDocument();
            expect(rowScope.getByText(location.address_line1)).toBeInTheDocument();
        }

        vi.unstubAllGlobals();
    });

    it('a 403 response shows role=alert scoped to the row and preserves all row values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                method === 'PUT'
            ) {
                return jsonResponse(403, { message: 'Forbidden.' });
            }
            return fetchMock(url, init);
        });

        const scope = await renderOnLocationsPage(withPut);
        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        await firstRowScope.findByLabelText('Display name');
        fireEvent.click(firstRowScope.getByRole('button', { name: 'Save' }));

        expect(await firstRowScope.findByRole('alert')).toBeInTheDocument();
        fireEvent.click(firstRowScope.getByRole('button', { name: 'Cancel' }));

        const restoredRows = locationRows(scope);
        expect(within(restoredRows[0]).getByText(makeLocationA().display_name)).toBeInTheDocument();
        expect(within(restoredRows[1]).getByText(makeLocationB().display_name)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('a 404 response shows role=alert scoped to the row and preserves all row values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}` &&
                method === 'PUT'
            ) {
                return jsonResponse(404, { message: 'Not found.' });
            }
            return fetchMock(url, init);
        });

        const scope = await renderOnLocationsPage(withPut);
        const rows = locationRows(scope);
        const firstRowScope = within(rows[0]);
        fireEvent.click(
            firstRowScope.getByRole('button', { name: editButtonName(makeLocationA()) }),
        );
        await firstRowScope.findByLabelText('Display name');
        fireEvent.click(firstRowScope.getByRole('button', { name: 'Save' }));

        expect(await firstRowScope.findByRole('alert')).toBeInTheDocument();
        fireEvent.click(firstRowScope.getByRole('button', { name: 'Cancel' }));

        const restoredRows = locationRows(scope);
        expect(within(restoredRows[0]).getByText(makeLocationA().display_name)).toBeInTheDocument();
        expect(within(restoredRows[1]).getByText(makeLocationB().display_name)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
