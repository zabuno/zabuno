import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { OrderQueueRegion } from './OrderQueueRegion';

/**
 * GARSON KUYRUĞU EKRANI — `docs/115` S4, G1–G5 (FF-179).
 *
 * Sunucu tarafındaki sınırlar PHP testlerinde donduruldu. Burada donan şey,
 * garsonun GÖZÜNE gelen: masa, satırlar, alerjen, tutar ve süre; onay tek
 * dokunuş, ret ise sebepsiz gönderilemez.
 */

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function order(overrides: Record<string, unknown> = {}) {
    return {
        id: 41,
        status: 'pending',
        tableName: 'Masa 7',
        areaLabel: 'Salon',
        totalMinorAmount: 4250,
        currencyCode: 'TRY',
        rejectionReason: null,
        placedAt: new Date(Date.now() - 9 * 60_000).toISOString(),
        statusChangedAt: new Date().toISOString(),
        timeZone: 'Europe/Istanbul',
        lines: [
            {
                productName: 'Fırın Sütlaç',
                quantity: 2,
                unitPriceMinorAmount: 2125,
                lineTotalMinorAmount: 4250,
                currencyCode: 'TRY',
                allergens: ['milk', 'gluten'],
            },
        ],
        ...overrides,
    };
}

function feed(rows: unknown[]) {
    return {
        ok: true,
        status: 200,
        json: async () => ({ data: rows, serverTime: new Date().toISOString() }),
    } as unknown as Response;
}

function renderQueue(acceptsOrders: boolean | null = true) {
    return render(
        <OrderQueueRegion
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            acceptsOrders={acceptsOrders}
            onNavigateToSettings={() => undefined}
        />,
    );
}

describe('OrderQueueRegion', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn((input: RequestInfo | URL) => {
                const url = String(input);

                if (url.includes('/orders/pending')) {
                    return Promise.resolve(feed([order()]));
                }

                return Promise.resolve({
                    ok: true,
                    status: 204,
                    json: async () => ({}),
                } as unknown as Response);
            }),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('shows the table, the lines, the allergens, the total and how long it waited', async () => {
        renderQueue();

        expect(await screen.findByText(/Masa 7/)).toBeTruthy();
        expect(screen.getByText(/Fırın Sütlaç/)).toBeTruthy();
        // K4: alerjen sipariş satırından okunur, üründen değil.
        expect(screen.getByText(/milk, gluten/)).toBeTruthy();
        // Süre garsonun hangi masaya önce gideceğini belirler (G1).
        expect(screen.getByText(/Waiting 9 min/)).toBeTruthy();
    });

    it('never claims to be live — it writes when it last refreshed', async () => {
        renderQueue();

        // `docs/115` §6: mutfakta donmuş bir ekranla dolu bir ekran aynı
        // görünür. "Anlık" demek bir yalandı; son güncelleme anı gerçektir.
        const line = await screen.findByTestId('orders-updated-at');

        expect(line.textContent).toMatch(/^Updated /);
    });

    it('approves an order with a single press', async () => {
        const user = userEvent.setup();
        renderQueue();

        await user.click(await screen.findByRole('button', { name: /Approve/ }));

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                `/api/workspaces/${String(WORKSPACE_ID)}/locations/${String(LOCATION_ID)}/orders/41/status`,
                expect.objectContaining({ method: 'PUT' }),
            );
        });
    });

    it('refuses to send a rejection without a reason', async () => {
        const user = userEvent.setup();
        renderQueue();

        await user.click(await screen.findByRole('button', { name: /^Reject$/ }));
        await user.click(screen.getByRole('button', { name: /Reject order/ }));

        /*
            G3: sebep misafirin ekranında görünür. Sebepsiz bir ret ona
            yalnız "olmadı" der. Ekran bunu SUNUCUYA SORMADAN durdurur —
            gerekçe ağdan tasarruf değil: garson ne yazacağını burada
            öğrenir.
        */
        expect(await screen.findByText(/Write a reason/)).toBeTruthy();

        const statusCalls = vi
            .mocked(fetch)
            .mock.calls.filter(([input]) => String(input).includes('/status'));

        expect(statusCalls).toHaveLength(0);
    });

    it('sends the reason the waiter typed', async () => {
        const user = userEvent.setup();
        renderQueue();

        await user.click(await screen.findByRole('button', { name: /^Reject$/ }));
        await user.type(screen.getByLabelText('Reason'), 'Nobody is sitting at that table.');
        await user.click(screen.getByRole('button', { name: /Reject order/ }));

        await waitFor(() => {
            const call = vi
                .mocked(fetch)
                .mock.calls.find(([input]) => String(input).includes('/status'));

            expect(call).toBeDefined();
            expect(String((call?.[1] as RequestInit).body)).toContain(
                'Nobody is sitting at that table.',
            );
        });
    });

    it('tells the waiter when somebody else was faster instead of pretending it worked', async () => {
        const user = userEvent.setup();

        vi.mocked(fetch).mockImplementation((input: RequestInfo | URL) => {
            const url = String(input);

            if (url.includes('/orders/pending')) {
                return Promise.resolve(feed([order()]));
            }

            if (url.includes('/status')) {
                return Promise.resolve({
                    ok: false,
                    status: 409,
                    json: async () => ({ status: 'confirmed' }),
                } as unknown as Response);
            }

            // CSRF çerezi: bu testin konusu değil, başarılı döner.
            return Promise.resolve({
                ok: true,
                status: 204,
                json: async () => ({}),
            } as unknown as Response);
        });

        renderQueue();

        await user.click(await screen.findByRole('button', { name: /Approve/ }));

        // G5: ikinci onay denemesi sessizce geçmez, siparişin O ANKİ
        // durumunu söyler.
        expect(await screen.findByText(/Someone was faster/)).toBeTruthy();
        expect(screen.getByText(/Approved/)).toBeTruthy();
    });

    it('separates a quiet evening from a switched-off service', async () => {
        vi.mocked(fetch).mockImplementation(() => Promise.resolve(feed([])));

        renderQueue(false);

        /*
            Y1. Boş bir liste iki farklı şey olabilir ve çıkış yolları
            farklıdır: sessiz bir akşamda beklenir, kapalı bir şalterde
            Ayarlar açılır. İkisini aynı boş kutuya toplamak, sahibin
            şalteri açmayı hiç akıl etmemesi demekti.
        */
        expect(await screen.findByText(/Ordering is switched off/)).toBeTruthy();
    });

    it('shows a plain empty state when ordering is on and nothing is waiting', async () => {
        vi.mocked(fetch).mockImplementation(() => Promise.resolve(feed([])));

        renderQueue(true);

        expect(await screen.findByText(/No orders waiting/)).toBeTruthy();
    });
});
