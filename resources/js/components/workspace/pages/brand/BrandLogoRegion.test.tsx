import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { BrandLogoRegion } from './BrandLogoRegion';

/**
 * MARKA LOGOSU BAĞLAMA — `docs/98` FF-64.
 *
 * Arka uç (`PUT .../brand/logo`) vardı, bağlayan ekran yoktu. Bu bölüm
 * yüklemeyi yeniden icat etmez: Media'daki `logo` slotundan hazır olanları
 * listeler, birini bağlar.
 */

const WORKSPACE_ID = 41;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function stubFetch(media: unknown[], putStatus = 200) {
    const calls: { url: string; method: string; body: unknown }[] = [];
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            calls.push({
                url: String(url),
                method,
                body: init?.body ? JSON.parse(String(init.body)) : null,
            });
            if (String(url) === '/sanctum/csrf-cookie') return jsonResponse(204, {});
            if (String(url).endsWith('/media')) return jsonResponse(200, { data: media });
            if (String(url).endsWith('/brand/logo')) {
                return jsonResponse(
                    putStatus,
                    putStatus === 200
                        ? { brandId: 3 }
                        : { message: 'Bu görsel henüz kullanıma hazır değil.' },
                );
            }
            return jsonResponse(200, {});
        }),
    );
    return calls;
}

const READY_LOGO = { id: 71, altText: 'Zeytin logosu', slot: 'logo', status: 'ready' };

describe('BrandLogoRegion (docs/98 FF-64)', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('lists only ready logo-slot media and binds the chosen one', async () => {
        const calls = stubFetch([
            READY_LOGO,
            { id: 72, altText: 'Ürün fotoğrafı', slot: 'itemImage', status: 'ready' },
            { id: 73, altText: 'İşleniyor', slot: 'logo', status: 'processing' },
        ]);

        render(<BrandLogoRegion workspaceId={WORKSPACE_ID} initialMediaAssetId={null} />);

        const select = await screen.findByLabelText('Choose a logo');
        // Yalnız hazır + logo slotu: ürün fotoğrafı ve işlenmekte olan yok.
        expect(screen.getByRole('option', { name: 'Zeytin logosu' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'Ürün fotoğrafı' })).not.toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'İşleniyor' })).not.toBeInTheDocument();

        // Değişiklik yokken düğme kapalı.
        expect(screen.getByRole('button', { name: 'Use this logo' })).toBeDisabled();

        await userEvent.selectOptions(select, '71');
        await userEvent.click(screen.getByRole('button', { name: 'Use this logo' }));

        await waitFor(() => {
            const put = calls.find((call) => call.url.endsWith('/brand/logo'));
            expect(put?.method).toBe('PUT');
            expect(put?.body).toEqual({ mediaAssetId: 71 });
        });
        expect(
            await screen.findByText('Logo saved. It goes live with your next publish.'),
        ).toBeInTheDocument();
    });

    it('opens with the current logo selected and can remove it', async () => {
        const calls = stubFetch([READY_LOGO]);

        render(<BrandLogoRegion workspaceId={WORKSPACE_ID} initialMediaAssetId={71} />);

        const select = (await screen.findByLabelText('Choose a logo')) as HTMLSelectElement;
        expect(select.value).toBe('71');

        await userEvent.selectOptions(select, '');
        await userEvent.click(screen.getByRole('button', { name: 'Use this logo' }));

        await waitFor(() => {
            const put = calls.find((call) => call.url.endsWith('/brand/logo'));
            expect(put?.body).toEqual({ mediaAssetId: null });
        });
    });

    it('sends the owner to the Media page when no logo has been processed yet', async () => {
        stubFetch([]);

        render(<BrandLogoRegion workspaceId={WORKSPACE_ID} initialMediaAssetId={null} />);

        expect(
            await screen.findByText(
                'No processed logo yet. Upload one on the Media page (slot: Logo) first.',
            ),
        ).toBeInTheDocument();
    });

    it("shows the server's reason when binding is refused", async () => {
        stubFetch([READY_LOGO], 422);

        render(<BrandLogoRegion workspaceId={WORKSPACE_ID} initialMediaAssetId={null} />);

        await userEvent.selectOptions(await screen.findByLabelText('Choose a logo'), '71');
        await userEvent.click(screen.getByRole('button', { name: 'Use this logo' }));

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Bu görsel henüz kullanıma hazır değil.',
        );
    });
});
