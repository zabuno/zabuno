import { describe, beforeEach, afterEach, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

/**
 * DESTEK MASASI, `/platform` KABUĞUNDA — `docs/122` §3 boşluk 3 ve Y7.
 *
 * Bölüm ADRESTEN gelir, fragment'ten değil (`docs/38` §4): destek görevlisi
 * bulduğu ekranın bağlantısını meslektaşına yollayabilmeli ve ölçüm bu
 * ekranı kendi adıyla görebilmeli.
 *
 * BÖLÜM GÖZETİM GRUBUNDA. Ticari grupta olsaydı bir destek aracı bir satış
 * işi gibi görünürdü; `docs/122` §5'in "en tehlikeli yetenek" cümlesi de
 * plan/abonelik komşuluğunda hafiflerdi.
 */

const SUPPORT_ACCESS_ENDPOINT = '/api/admin/support-access';
const SUPPORT_REQUESTS_ENDPOINT = '/api/admin/support-requests';
const WORKSPACES_ENDPOINT = '/api/admin/workspaces';

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        const parsed = new URL(String(url), 'http://localhost');

        if (parsed.pathname === SUPPORT_ACCESS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, { session: null, windowMinutes: 15, reasonMinLength: 12 });
        }

        if (parsed.pathname === SUPPORT_REQUESTS_ENDPOINT && method === 'GET') {
            return jsonResponse(200, []);
        }

        if (parsed.pathname === WORKSPACES_ENDPOINT && method === 'GET') {
            return jsonResponse(200, [
                { id: 7, name: 'Acme Corp', slug: 'acme-corp', state: 'active' },
            ]);
        }

        throw new Error(
            `Unhandled fetch in PlatformApp support desk test: ${method} ${String(url)}`,
        );
    });
}

async function importPlatformAppModule() {
    return import('./PlatformApp') as unknown as Promise<{ PlatformApp: React.ComponentType }>;
}

describe('PlatformApp — support desk', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=test-xsrf-token';
        vi.stubGlobal('fetch', buildFetchMock());
        history.replaceState(null, '', '/platform');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        history.replaceState(null, '', '/platform');
    });

    it('exposes a Support desk nav item', async () => {
        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        expect(await screen.findByRole('link', { name: /support desk/i })).toBeInTheDocument();
    });

    it('opens the support desk at /platform/support and asks for nothing until a tenant is picked', async () => {
        history.replaceState(null, '', '/platform/support');

        const { PlatformApp } = await importPlatformAppModule();
        render(<PlatformApp />);

        const nav = await screen.findByRole('link', { name: /support desk/i });
        expect(nav).toHaveAttribute('aria-current', 'page');

        // Kiracı seçilmeden hiçbir kiracı verisi okunmaz ve hiçbir bakma
        // kartı çizilmez: impersonation bir varsayılan değil, bir karardır.
        expect(
            await screen.findByText(/Pick a restaurant to see what its guests/),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('region', { name: /Look at this account as the tenant/ }),
        ).toBeNull();
    });
});
