import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrDestinationRegion } from './QrDestinationRegion';

/**
 * KODU BAŞKA ŞUBEYE TAŞI — `docs/81` P1-03, ekranı `docs/98` FF-64.
 *
 * Arka uç (`PUT .../qr-codes/{id}/destination`) vardı; ekran yalnız
 * kapat/aç sunuyordu. Kart fiziksel olarak başka şubeye gittiğinde tek çare
 * yeniden bastırmaktı — ürünün "bir kez bas" vaadinin ihlali.
 */

const WORKSPACE_ID = 9;

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

const CODE = {
    id: 5,
    workspaceId: WORKSPACE_ID,
    locationId: 1,
    menuId: 10,
    token: 'abc123',
    resolverUrl: 'https://zabuno.test/q/abc123',
    destinationType: 'published_menu',
    state: 'active',
};

function stubFetch(locations: unknown[]) {
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
            if (String(url).endsWith('/brand/locations')) return jsonResponse(200, locations);
            if (String(url).endsWith('/qr-codes')) return jsonResponse(200, [CODE]);
            if (String(url).endsWith('/destination')) {
                return jsonResponse(200, { ...CODE, locationId: 2, menuId: 20 });
            }
            return jsonResponse(200, {});
        }),
    );
    return calls;
}

describe('QrDestinationRegion — move to another location (docs/98 FF-64)', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('offers only the OTHER locations and moves the code by naming the branch', async () => {
        const calls = stubFetch([
            { id: 1, display_name: 'Kadıköy' },
            { id: 2, display_name: 'Beşiktaş' },
        ]);

        render(
            <QrDestinationRegion
                workspaceId={WORKSPACE_ID}
                locationId={1}
                menuId={10}
                hasCurrentPublication
            />,
        );

        await waitFor(() => {
            // FF-110: satırda kısaltılmış adres yazar; sözleşme `href`'tedir.
            expect(
                within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
            ).toHaveAttribute('href', CODE.resolverUrl);
        });
        // Şube listesi "Taşı" istenene kadar yüklenmez.
        expect(calls.some((call) => call.url.endsWith('/brand/locations'))).toBe(false);
        /*
            İKİ ADIM (FF-110): "Taşı" artık satırın altında değil, taşma
            menüsünde. Yıkıcı "kapat" ile sıradan "taşı" yan yana iki küçük
            hedefti ve yalnız renkle ayrılıyordu.
        */
        await userEvent.click(screen.getByRole('button', { name: /more actions for/i }));
        await userEvent.click(screen.getByRole('menuitem', { name: 'Move to another location' }));

        const select = await screen.findByLabelText('Move this code to');
        // Kodun kendi şubesi listede YOK — "olduğu yere taşı" diye bir şey yok.
        expect(within(select).queryByRole('option', { name: 'Kadıköy' })).not.toBeInTheDocument();
        expect(within(select).getByRole('option', { name: 'Beşiktaş' })).toBeInTheDocument();

        expect(screen.getByRole('button', { name: 'Move' })).toBeDisabled();
        await userEvent.selectOptions(select, '2');
        await userEvent.click(screen.getByRole('button', { name: 'Move' }));

        await waitFor(() => {
            const put = calls.find((call) => call.url.endsWith('/qr-codes/5/destination'));
            expect(put?.method).toBe('PUT');
            // Şube adıyla — menü kimlikleri için N istek atılmaz.
            expect(put?.body).toEqual({ locationId: 2 });
        });

        // Token DEĞİŞMEDİ: basılı kâğıt aynı kâğıt.
        expect(
            within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
        ).toHaveAttribute('href', CODE.resolverUrl);
    });

    it('says plainly there is nowhere to move when there is a single location', async () => {
        stubFetch([{ id: 1, display_name: 'Kadıköy' }]);

        render(
            <QrDestinationRegion
                workspaceId={WORKSPACE_ID}
                locationId={1}
                menuId={10}
                hasCurrentPublication
            />,
        );

        await waitFor(() => {
            // FF-110: satırda kısaltılmış adres yazar; sözleşme `href`'tedir.
            expect(
                within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
            ).toHaveAttribute('href', CODE.resolverUrl);
        });
        /*
            İKİ ADIM (FF-110): "Taşı" artık satırın altında değil, taşma
            menüsünde. Yıkıcı "kapat" ile sıradan "taşı" yan yana iki küçük
            hedefti ve yalnız renkle ayrılıyordu.
        */
        await userEvent.click(screen.getByRole('button', { name: /more actions for/i }));
        await userEvent.click(screen.getByRole('menuitem', { name: 'Move to another location' }));

        expect(
            await screen.findByText(
                'This is your only location — there is nowhere else to move the code.',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByLabelText('Move this code to')).not.toBeInTheDocument();
    });
});
