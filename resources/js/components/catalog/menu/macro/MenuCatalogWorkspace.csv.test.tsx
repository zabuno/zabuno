import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * MENÜYÜ ALMAK VE GERİ KOYMAK — `docs/80` (P0-05 CSV yolu, P0-09).
 *
 * 60 kalemlik bir menüyü tek tek elle yazmak, sahibin en başta kaçtığı iş.
 * Ve "menümü alıp gidebilir miyim?" sorusunun cevabı bir bağlantı olmalı,
 * bir destek talebi değil.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;
const MENU_ID = 42;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function tree() {
    return {
        id: MENU_ID,
        workspaceId: WORKSPACE_ID,
        locationId: LOCATION_ID,
        name: 'Ana Menü',
        state: 'draft',
        categories: [{ id: 5, menuId: MENU_ID, name: 'Kebaplar', position: 1, menuItems: [] }],
    };
}

type Call = { url: string; method: string };

async function renderWorkspace(importResponse: { status: number; body: unknown }) {
    const calls: Call[] = [];

    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();
        calls.push({ url: String(url), method });

        if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (String(url).endsWith('/brand') && method === 'GET') {
            return jsonResponse(200, { id: 1, workspaceId: WORKSPACE_ID, currency: 'TRY' });
        }
        if (String(url).endsWith(`/locations/${LOCATION_ID}/menu`) && method === 'GET') {
            return jsonResponse(200, tree());
        }
        if (String(url).endsWith('/import') && method === 'POST') {
            return jsonResponse(importResponse.status, importResponse.body);
        }

        return jsonResponse(200, { ok: true });
    });

    vi.stubGlobal('fetch', fetchMock);

    const { MenuCatalogWorkspace } = (await import('./MenuCatalogWorkspace')) as unknown as {
        MenuCatalogWorkspace: React.ComponentType<{ workspaceId: number; locationId: number }>;
    };

    render(<MenuCatalogWorkspace workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);
    await screen.findByRole('heading', { name: 'Kebaplar' });

    return { calls, user: userEvent.setup() };
}

function csvFile(): File {
    return new File(
        [
            'category,product,price,currency,allergens,description,visible\nKebaplar,Adana,380.00,TRY,,,yes',
        ],
        'menu.csv',
        { type: 'text/csv' },
    );
}

describe('menüyü almak ve geri koymak (docs/80)', () => {
    it('indirme düz bir bağlantıdır', async () => {
        await renderWorkspace({ status: 200, body: {} });

        const link = screen.getByRole('link', { name: 'Download menu (CSV)' });

        // Tarayıcının kendi indirme yolu, bizim yeniden ürettiğimiz herhangi
        // bir yoldan güvenilirdir.
        expect(link).toHaveAttribute(
            'href',
            `/api/workspaces/${WORKSPACE_ID}/menu/${MENU_ID}/export.csv`,
        );
        expect(link).toHaveAttribute('download');

        vi.unstubAllGlobals();
    });

    it('aktarım sonucu sayıyla ve reddedilen satırlarla anlatılır', async () => {
        const { calls, user } = await renderWorkspace({
            status: 200,
            body: {
                importedItems: 60,
                importedCategories: 1,
                rejectedRows: [{ line: 62, reason: 'Fiyat boş.' }],
            },
        });

        await user.upload(screen.getByLabelText('Choose a file'), csvFile());

        await waitFor(() => {
            expect(calls.some((call) => call.url.endsWith('/import'))).toBe(true);
        });

        expect(
            await screen.findByText('Imported 60 items into 1 new categories.'),
        ).toBeInTheDocument();

        // Satır NUMARASI olmadan sahip 60 satırı gözle taramak zorunda kalır.
        expect(screen.getByText('Line 62: Fiyat boş.')).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('hiçbir satır okunamadıysa bu bir başarı gibi gösterilmez', async () => {
        const { user } = await renderWorkspace({
            status: 422,
            body: { message: 'Dosyadaki hiçbir satır okunamadı.' },
        });

        await user.upload(screen.getByLabelText('Choose a file'), csvFile());

        expect(await screen.findByText('Dosyadaki hiçbir satır okunamadı.')).toBeInTheDocument();
        expect(screen.queryByText(/Imported/)).toBeNull();

        vi.unstubAllGlobals();
    });
});
