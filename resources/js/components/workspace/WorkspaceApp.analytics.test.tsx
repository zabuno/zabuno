import type React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * S1-WP05b1 RED — WorkspaceApp must pass the current selected real
 * workspace/location IDs into AnalyticsPage so it can call the real
 * location-scoped summary endpoint, and the summary fetch must fire only
 * after those IDs (and the location list) are resolved. Today
 * WorkspaceApp renders `<AnalyticsPage />` with no props (see
 * WorkspaceApp.tsx), so the summary request never happens and this
 * suite fails RED first.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const LOCATION_ID = 923;
const SUMMARY_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/analytics/summary`;

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

function buildFetchMock() {
    return async (url: string, init?: RequestInit) => {
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
        if (String(url) === `${SUMMARY_ENDPOINT}?range=today` && method === 'GET') {
            return jsonResponse(200, {
                range: 'today',
                qrResolveCount: 5,
                menuOpenCount: 4,
                generatedAt: '2026-08-22T09:00:00.000Z',
            });
        }

        throw new Error(`Unhandled fetch in WorkspaceApp analytics test: ${method} ${String(url)}`);
    };
}

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        configurable: true,
        writable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        configurable: true,
        writable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

async function renderCurrentWorkspace() {
    const originalFetch = window.fetch;
    Object.defineProperty(window, 'fetch', {
        configurable: true,
        writable: true,
        value: buildFetchMock(),
    });

    const { WorkspaceApp } = await importWorkspaceModule<{
        WorkspaceApp: React.ComponentType<typeof desktopChrome>;
    }>();
    const rendered = render(<WorkspaceApp {...desktopChrome} />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return {
        ...rendered,
        restoreFetch: () => {
            window.fetch = originalFetch;
        },
    };
}

describe('WorkspaceApp — Analytics destination wired to real workspace/location IDs (S1-WP05b1, RED)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
        setViewport(320, 480);
    });

    it('passes the current selected real workspace and location IDs into AnalyticsPage, which fetches the real scoped summary', async () => {
        const user = userEvent.setup();
        const { restoreFetch } = await renderCurrentWorkspace();

        const nav = screen.getByRole('navigation', { name: 'Restaurant admin' });
        await user.click(within(nav).getByRole('link', { name: 'Insights' }));

        const main = screen.getByRole('main');
        const analyticsRegion = main.querySelector('#section-analytics') as HTMLElement;
        expect(analyticsRegion).not.toBeNull();

        const region = await within(analyticsRegion).findByRole('region', {
            name: /metric|report/i,
        });
        await within(region).findByText('5');
        expect(within(region).getByText('4')).toBeInTheDocument();

        restoreFetch();
    });
});
