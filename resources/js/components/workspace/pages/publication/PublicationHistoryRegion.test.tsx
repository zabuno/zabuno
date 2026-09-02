import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { PublicationHistoryRegion } from './PublicationHistoryRegion';

/**
 * YANLIŞ YAYINDAN DÖNMEK — `docs/81` (P1-05).
 *
 * Sahip yanlış fiyat listesini yayınladı ve misafirler şu anda onu okuyor.
 * Taslağı düzeltip yeniden yayınlamak, panik anında en yavaş yol.
 */
function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

const HISTORY = [
    { id: 92, version: 2, state: 'published', publishedAt: '2026-08-28T18:00:00Z', isLive: true },
    { id: 91, version: 1, state: 'published', publishedAt: '2026-08-27T18:00:00Z', isLive: false },
];

function mountWith(restoreStatus = 201) {
    const calls: { url: string; method: string }[] = [];

    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({ url: String(url), method });

            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/restore')) return jsonResponse(restoreStatus, {});

            return jsonResponse(200, { data: HISTORY });
        }),
    );

    render(
        <PublicationHistoryRegion
            workspaceId={7}
            menuId={42}
            refreshToken={0}
            onRestored={() => {}}
        />,
    );

    return { calls, user: userEvent.setup() };
}

describe('yayın geçmişi (docs/81)', () => {
    it('canlı sürüm işaretlidir ve geri alınamaz', async () => {
        mountWith();

        expect(await screen.findByText('Version 2')).toBeInTheDocument();
        expect(screen.getByText('Live')).toBeInTheDocument();

        // Canlı sürüme "geri dön" düğmesi konmaz: kendine dönmek bir eylem
        // değil, gürültüdür.
        expect(screen.queryByLabelText('Go back to version 2')).toBeNull();
        expect(screen.getByLabelText('Go back to version 1')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('geri alma yeni bir yayın olarak gönderilir', async () => {
        const { calls, user } = mountWith();

        await user.click(await screen.findByLabelText('Go back to version 1'));

        await waitFor(() => {
            expect(
                calls.some(
                    (call) =>
                        call.method === 'POST' &&
                        call.url === '/api/workspaces/7/menu/42/publications/91/restore',
                ),
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });

    it('geri alma başarısızsa sessiz kalınmaz', async () => {
        const { user } = mountWith(500);

        await user.click(await screen.findByLabelText('Go back to version 1'));

        expect(await screen.findByText('This version could not be restored.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });
});
