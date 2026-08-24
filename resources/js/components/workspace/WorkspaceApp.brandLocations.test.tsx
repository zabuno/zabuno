import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen, fireEvent, waitFor, within } from '@testing-library/react';

/**
 * Freezes the exclusive Brand (#brand) and Locations (#locations) page
 * contract for WorkspaceApp (S1-WP01A foundation): full brand detail
 * (locale/timezone/currency/description/contact) on the Brand page, and
 * the real location list on the Locations page. Split from the legacy
 * combined "Brand & Locations" page/nav item (SPEED_BATCH_LEGACY_TESTS_ALIGNED_AND_HASH_RED):
 * brand assertions click Brand, location assertions click Locations —
 * there is no combined nav item, alias link, or #brand-locations root.
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
        address_line2: null,
        postal_code: null,
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
            return jsonResponse(200, [makeLocationA(), makeLocationB()]);
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(404, {});
        }
        if (
            String(url) ===
                `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_B}/menu` &&
            method === 'GET'
        ) {
            return jsonResponse(404, {});
        }

        throw new Error(
            `Unhandled fetch in WorkspaceApp brandLocations test: ${method} ${String(url)}`,
        );
    });
}

describe('WorkspaceApp — Brand & Locations pages (S1-WP01A foundation)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
    });

    it('exposes distinct Brand and Locations nav items once a brand with 2+ real locations has loaded', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType;
        }>();
        render(<WorkspaceApp />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        expect(within(nav).getByRole('link', { name: 'Brand' })).toBeInTheDocument();
        expect(within(nav).getByRole('link', { name: 'Locations' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('renders real brand detail on the Brand page and fires no write requests', async () => {
        history.replaceState(null, '', `${window.location.pathname}#brand`);

        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType;
        }>();
        render(<WorkspaceApp />);

        const destination = await screen
            .findByText('Menekşe')
            .then(() => document.querySelector('#brand'));
        expect(destination).not.toBeNull();
        const scope = within(destination as HTMLElement);

        const brand = makeBrand();
        expect(scope.getByText(brand.name)).toBeInTheDocument();
        expect(scope.getByText(brand.slug)).toBeInTheDocument();
        expect(scope.getByText(brand.locale)).toBeInTheDocument();
        expect(scope.getByText(brand.timezone)).toBeInTheDocument();
        expect(scope.getByText(brand.currency)).toBeInTheDocument();
        expect(scope.getByText(brand.description as string)).toBeInTheDocument();
        expect(scope.getByText(brand.contact_email)).toBeInTheDocument();
        expect(scope.getByText(brand.contact_phone)).toBeInTheDocument();

        const writeCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method === 'PUT' || method === 'POST';
        });
        expect(writeCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('renders every real location on the Locations page and fires no write requests', async () => {
        history.replaceState(null, '', `${window.location.pathname}#locations`);

        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType;
        }>();
        render(<WorkspaceApp />);

        const locationA = makeLocationA();
        const locationB = makeLocationB();

        await waitFor(() => {
            expect(document.querySelector('#locations')).not.toBeNull();
        });
        const destination = document.querySelector('#locations');
        expect(destination).not.toBeNull();
        const scope = within(destination as HTMLElement);
        await scope.findByText(locationA.display_name);

        for (const location of [locationA, locationB]) {
            expect(scope.getByText(location.display_name)).toBeInTheDocument();
            expect(scope.getByText(location.city)).toBeInTheDocument();
            expect(scope.getByText(location.address_line1)).toBeInTheDocument();
        }

        expect(scope.getAllByText(locationA.country_code).length).toBeGreaterThanOrEqual(1);
        expect(destination?.querySelectorAll('[data-testid="brand-location-row"]')).toHaveLength(2);

        const writeCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method === 'PUT' || method === 'POST';
        });
        expect(writeCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    async function renderOnBrandPage() {
        const { WorkspaceApp } = await importWorkspaceModule<{
            WorkspaceApp: React.ComponentType;
        }>();
        render(<WorkspaceApp />);

        const nav = await screen.findByRole('navigation', { name: 'Restaurant admin' });
        const navLink = within(nav).getByRole('link', { name: 'Brand' });
        fireEvent.click(navLink);

        const destination = document.querySelector('#brand') as HTMLElement;
        expect(destination).not.toBeNull();

        return within(destination);
    }

    function findEditButton(scope: ReturnType<typeof within>) {
        return scope.findByRole('button', { name: 'Edit' });
    }

    it('Edit reveals fields prefilled from the fetched brand', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const scope = await renderOnBrandPage();
        const brand = makeBrand();

        fireEvent.click(await findEditButton(scope));

        expect((await scope.findByLabelText('Name')) as HTMLInputElement).toHaveValue(brand.name);
        expect(scope.getByLabelText('Locale')).toHaveValue(brand.locale);
        expect(scope.getByLabelText('Timezone')).toHaveValue(brand.timezone);
        expect(scope.getByLabelText('Currency')).toHaveValue(brand.currency);
        expect(scope.getByLabelText('Description')).toHaveValue(brand.description);
        expect(scope.getByLabelText('Contact email')).toHaveValue(brand.contact_email);
        expect(scope.getByLabelText('Contact phone')).toHaveValue(brand.contact_phone);

        vi.unstubAllGlobals();
    });

    it('Cancel closes the edit form without issuing any write request', async () => {
        const fetchMock = buildFetchMock();
        vi.stubGlobal('fetch', fetchMock);

        const scope = await renderOnBrandPage();

        fireEvent.click(await findEditButton(scope));
        await scope.findByLabelText('Name');

        fireEvent.click(scope.getByRole('button', { name: 'Cancel' }));

        expect(scope.queryByLabelText('Name')).not.toBeInTheDocument();
        expect(await findEditButton(scope)).toBeInTheDocument();

        const writeCalls = fetchMock.mock.calls.filter(([, init]) => {
            const method = ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase();
            return method === 'PUT' || method === 'POST';
        });
        expect(writeCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('Save bootstraps the CSRF cookie then PUTs exactly once with credentials, headers, and payload, updating the displayed brand from the 200 response with no /api/user or /api/workspace-context refetch', async () => {
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();

            if (String(url) === CSRF_COOKIE_URL) {
                document.cookie = 'XSRF-TOKEN=test-xsrf-token';
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
            if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'PUT') {
                return jsonResponse(200, {
                    ...makeBrand(),
                    name: 'Menekşe Güncel',
                    description: 'Güncellenmiş açıklama.',
                    contact_email: 'yeni@menekse.example',
                    contact_phone: '+90 212 555 02 02',
                });
            }
            if (
                String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` &&
                method === 'GET'
            ) {
                return jsonResponse(200, [makeLocationA(), makeLocationB()]);
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_A}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(404, {});
            }
            if (
                String(url) ===
                    `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID_B}/menu` &&
                method === 'GET'
            ) {
                return jsonResponse(404, {});
            }

            throw new Error(
                `Unhandled fetch in WorkspaceApp brandLocations save test: ${method} ${String(url)}`,
            );
        });
        vi.stubGlobal('fetch', fetchMock);
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC';

        const scope = await renderOnBrandPage();

        fireEvent.click(await findEditButton(scope));
        const nameInput = await scope.findByLabelText('Name');
        fireEvent.change(nameInput, { target: { value: 'Menekşe Güncel' } });
        fireEvent.change(scope.getByLabelText('Description'), {
            target: { value: 'Güncellenmiş açıklama.' },
        });
        fireEvent.change(scope.getByLabelText('Contact email'), {
            target: { value: 'yeni@menekse.example' },
        });
        fireEvent.change(scope.getByLabelText('Contact phone'), {
            target: { value: '+90 212 555 02 02' },
        });

        const saveButton = scope.getByRole('button', { name: 'Save' });
        expect(saveButton).not.toBeDisabled();
        fireEvent.click(saveButton);

        expect(saveButton).toBeDisabled();

        const putCalls = await (async () => {
            const deadline = Date.now() + 2000;
            while (Date.now() < deadline) {
                const calls = fetchMock.mock.calls.filter(
                    ([url, requestInit]) =>
                        String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` &&
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
            name: 'Menekşe Güncel',
            locale: 'tr',
            timezone: 'Europe/Istanbul',
            currency: 'TRY',
            description: 'Güncellenmiş açıklama.',
            contact_email: 'yeni@menekse.example',
            contact_phone: '+90 212 555 02 02',
        });

        const csrfCallIndex = fetchMock.mock.calls.findIndex(
            ([url]) => String(url) === CSRF_COOKIE_URL,
        );
        const putCallIndex = fetchMock.mock.calls.findIndex(
            ([url, requestInit]) =>
                String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` &&
                ((requestInit as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );
        expect(csrfCallIndex).toBeGreaterThanOrEqual(0);
        expect(csrfCallIndex).toBeLessThan(putCallIndex);

        expect(await scope.findByText('Menekşe Güncel')).toBeInTheDocument();
        expect(scope.getByText('Güncellenmiş açıklama.')).toBeInTheDocument();
        expect(scope.getByText('yeni@menekse.example')).toBeInTheDocument();
        expect(scope.getByText('+90 212 555 02 02')).toBeInTheDocument();

        const userRefetches = fetchMock.mock.calls.filter(([url]) => String(url) === '/api/user');
        const contextRefetches = fetchMock.mock.calls.filter(
            ([url]) => String(url) === '/api/workspace-context',
        );
        expect(userRefetches).toHaveLength(1);
        expect(contextRefetches).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    it('a 422 validation response shows role=alert and preserves the displayed brand values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'PUT') {
                return jsonResponse(422, {
                    message: 'The name field is required.',
                    errors: { name: ['The name field is required.'] },
                });
            }
            return fetchMock(url, init);
        });
        vi.stubGlobal('fetch', withPut);

        const scope = await renderOnBrandPage();
        const brand = makeBrand();

        fireEvent.click(await findEditButton(scope));
        await scope.findByLabelText('Name');
        fireEvent.click(scope.getByRole('button', { name: 'Save' }));

        expect(await scope.findByRole('alert')).toBeInTheDocument();

        fireEvent.click(scope.getByRole('button', { name: 'Cancel' }));
        expect(scope.getByText(brand.name)).toBeInTheDocument();
        expect(scope.getByText(brand.description as string)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('a 403 response shows role=alert and preserves the displayed brand values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'PUT') {
                return jsonResponse(403, { message: 'Forbidden.' });
            }
            return fetchMock(url, init);
        });
        vi.stubGlobal('fetch', withPut);

        const scope = await renderOnBrandPage();
        const brand = makeBrand();

        fireEvent.click(await findEditButton(scope));
        await scope.findByLabelText('Name');
        fireEvent.click(scope.getByRole('button', { name: 'Save' }));

        expect(await scope.findByRole('alert')).toBeInTheDocument();
        fireEvent.click(scope.getByRole('button', { name: 'Cancel' }));
        expect(scope.getByText(brand.name)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('a 404 response shows role=alert and preserves the displayed brand values', async () => {
        const fetchMock = buildFetchMock();
        const withPut = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'PUT') {
                return jsonResponse(404, { message: 'Not found.' });
            }
            return fetchMock(url, init);
        });
        vi.stubGlobal('fetch', withPut);

        const scope = await renderOnBrandPage();
        const brand = makeBrand();

        fireEvent.click(await findEditButton(scope));
        await scope.findByLabelText('Name');
        fireEvent.click(scope.getByRole('button', { name: 'Save' }));

        expect(await scope.findByRole('alert')).toBeInTheDocument();
        fireEvent.click(scope.getByRole('button', { name: 'Cancel' }));
        expect(scope.getByText(brand.name)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
