import { describe, beforeEach, afterEach, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';

/**
 * RED test freezing the Subscriptions journey inside the standalone
 * platform admin shell (S1-WP01A manual-payment frontend-first package).
 *
 * Today PlatformApp (resources/js/components/platform/PlatformApp.tsx)
 * hard-codes a single "plans" section and renders only PlanManagementPage;
 * there is no Subscriptions nav item, no /platform/subscriptions handling,
 * and no popstate listener at all. Every assertion below must fail against
 * current production.
 *
 * Frozen contract:
 * - A "Subscriptions" nav item exists alongside "Plans".
 * - #subscriptions selects Subscriptions and renders the real
 *   SubscriptionManagementPage (driving its own workspace-discovery fetch).
 * - The bare /platform address and any unknown section keeps Plans
 *   selected and rendered.
 * - A popstate event (not just initial location) switches the rendered
 *   page and the selected nav item.
 * - PlatformApp itself invents no workspace, plan, price, or subscription
 *   data — every value visible comes from the mocked network responses.
 */

const PLANS_ENDPOINT = '/api/admin/plans';
const WORKSPACES_ENDPOINT = '/api/admin/workspaces';

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

function makeActivePlans() {
    return [
        {
            id: 21,
            name: 'Starter',
            code: 'starter',
            version: 1,
            entitlements: ['1 brand'],
            amount_minor: 49900,
            currency: 'TRY',
            sort_order: 1,
            is_active: true,
        },
    ];
}

function makeWorkspaces() {
    return [{ id: 7, name: 'Acme Corp', slug: 'acme-corp', state: 'active' }];
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const parsed = new URL(String(url), 'http://localhost');

        if (parsed.pathname === PLANS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, makeActivePlans());
        }

        if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET') {
            return jsonResponse(200, makeWorkspaces());
        }

        throw new Error(
            `Unhandled fetch in PlatformApp subscriptions test: ${method} ${String(url)}`,
        );
    });
}

async function importPlatformAppModule() {
    return import('./PlatformApp') as unknown as Promise<{ PlatformApp: React.ComponentType }>;
}

describe('PlatformApp — Subscriptions journey (PLATFORM_SUBSCRIPTIONS_NAV_FRONTEND_RED)', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        vi.stubGlobal('fetch', buildFetchMock());
        // Her test panelin kök adresinde başlar; bir önceki testin bıraktığı
        // adres sonrakini sessizce başka bir ekranda açmasın.
        history.replaceState(null, '', '/platform');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        history.replaceState(null, '', '/platform');
    });

    it('exposes a Subscriptions nav item alongside Plans', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        expect(await screen.findByRole('link', { name: /^plans$/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /subscriptions/i })).toBeInTheDocument();
    });

    it('keeps Plans selected and rendered for the bare /platform address', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const plansNavItem = await screen.findByRole('link', { name: /^plans$/i });
        expect(plansNavItem).toHaveAttribute('aria-current', 'page');

        const fetchMock = vi.mocked(fetch);
        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledWith(
                PLANS_ENDPOINT,
                expect.objectContaining({ credentials: 'same-origin' }),
            );
        });
        expect(
            fetchMock.mock.calls.some(([calledUrl]) =>
                String(calledUrl).startsWith(WORKSPACES_ENDPOINT),
            ),
        ).toBe(false);
    });

    it('keeps Plans selected and rendered for an unknown section', async () => {
        history.replaceState(null, '', '/platform/not-a-real-section');

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const plansNavItem = await screen.findByRole('link', { name: /^plans$/i });
        expect(plansNavItem).toHaveAttribute('aria-current', 'page');
    });

    it('selects Subscriptions and renders SubscriptionManagementPage at /platform/subscriptions', async () => {
        history.replaceState(null, '', '/platform/subscriptions');

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const subscriptionsNavItem = await screen.findByRole('link', { name: /subscriptions/i });
        expect(subscriptionsNavItem).toHaveAttribute('aria-current', 'page');

        const fetchMock = vi.mocked(fetch);
        await waitFor(() => {
            expect(
                fetchMock.mock.calls.some(([calledUrl]) =>
                    String(calledUrl).startsWith(WORKSPACES_ENDPOINT),
                ),
            ).toBe(true);
        });

        expect(await screen.findByText('Acme Corp')).toBeInTheDocument();
    });

    it('switches the rendered page and selected nav item on back/forward, without a full reload', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        expect(await screen.findByRole('link', { name: /^plans$/i })).toHaveAttribute(
            'aria-current',
            'page',
        );

        history.replaceState(null, '', '/platform/subscriptions');
        window.dispatchEvent(new PopStateEvent('popstate'));

        await waitFor(() => {
            expect(screen.getByRole('link', { name: /subscriptions/i })).toHaveAttribute(
                'aria-current',
                'page',
            );
        });
        expect(await screen.findByText('Acme Corp')).toBeInTheDocument();

        history.replaceState(null, '', '/platform/plans');
        window.dispatchEvent(new PopStateEvent('popstate'));

        await waitFor(() => {
            expect(screen.getByRole('link', { name: /^plans$/i })).toHaveAttribute(
                'aria-current',
                'page',
            );
        });
    });

    it('never renders a workspace, plan, price, or subscription value that was not returned by the mocked network', async () => {
        history.replaceState(null, '', '/platform/subscriptions');

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        await screen.findByText('Acme Corp');

        expect(
            screen.queryByText(/invented|placeholder|sample workspace/i),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Enterprise')).not.toBeInTheDocument();
    });
});
