import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { KitchenMonitor } from './KitchenMonitor';

/**
 * MUTFAK MONİTÖRÜ — `docs/115` S5, K1–K5 (FF-179).
 *
 * Sahibin cümlesi: "Admin panelde bu monitör içeriğinin tam ekran olabilmesi
 * için gereken UI olmalıdır." Bu dosya o cümleyi ve etrafındaki dört kuralı
 * koda bağlar: uzaktan okunurluk, alerjenin fişte durması, tek dokunuşla
 * ilerletme, ve teslim düğmesinin aşçıya HİÇ çizilmemesi.
 */

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function ticket(overrides: Record<string, unknown> = {}) {
    return {
        id: 88,
        status: 'confirmed',
        tableName: 'Masa 7',
        areaLabel: 'Salon',
        totalMinorAmount: 4250,
        currencyCode: 'TRY',
        rejectionReason: null,
        placedAt: new Date(Date.now() - 4 * 60_000).toISOString(),
        statusChangedAt: new Date().toISOString(),
        timeZone: 'Europe/Istanbul',
        lines: [
            {
                productName: 'Mercimek Çorbası',
                quantity: 2,
                unitPriceMinorAmount: 2125,
                lineTotalMinorAmount: 4250,
                currencyCode: 'TRY',
                allergens: ['celery'],
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

function renderMonitor(props: Partial<Parameters<typeof KitchenMonitor>[0]> = {}) {
    return render(
        <KitchenMonitor
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            canAdvance
            canDeliver={false}
            {...props}
        />,
    );
}

describe('KitchenMonitor', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn((input: RequestInfo | URL) => {
                if (String(input).includes('/orders/kitchen')) {
                    return Promise.resolve(feed([ticket()]));
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

    it('reads only the kitchen feed — waiting orders are not its business', async () => {
        renderMonitor();

        await waitFor(() => {
            expect(fetch).toHaveBeenCalledWith(
                `/api/workspaces/${String(WORKSPACE_ID)}/locations/${String(LOCATION_ID)}/orders/kitchen`,
                expect.anything(),
            );
        });

        // K1: sunucu zaten yalnız onaylanmışı gönderir; ekran ikinci bir
        // süzme YAPMAZ. Süzme iki yerde olsaydı, biri eskidiğinde mutfak
        // onaylanmamış bir işe başlardı.
        const pendingCalls = vi
            .mocked(fetch)
            .mock.calls.filter(([input]) => String(input).includes('/orders/pending'));

        expect(pendingCalls).toHaveLength(0);
    });

    it('puts the allergen copy on the ticket itself', async () => {
        renderMonitor();

        // K4: alerjen ürünün sayfasında değil, FİŞTE. "Ürün sayfasında
        // yazıyordu" bir savunma değildir.
        expect(await screen.findByText(/celery/)).toBeTruthy();
    });

    it('offers a full-screen control for the screen on the wall', async () => {
        const requestFullscreen = vi.fn(() => Promise.resolve());
        Object.defineProperty(HTMLElement.prototype, 'requestFullscreen', {
            configurable: true,
            writable: true,
            value: requestFullscreen,
        });

        const user = userEvent.setup();
        renderMonitor();

        await user.click(await screen.findByRole('button', { name: /Full screen/ }));

        expect(requestFullscreen).toHaveBeenCalled();
    });

    it('says so honestly when the browser has no full-screen mode', async () => {
        // @ts-expect-error — yeteneği kaldırmak testin konusu.
        delete HTMLElement.prototype.requestFullscreen;
        Object.defineProperty(document.documentElement, 'requestFullscreen', {
            configurable: true,
            value: undefined,
        });

        renderMonitor();

        /*
            Basıldığında hiçbir şey yapmayan bir düğme, mutfakta bir kez
            denenip bir daha güvenilmeyen bir arayüz bırakır. Yetenek yoksa
            düğme de yoktur; yerine ne yapılacağını söyleyen bir cümle durur.
        */
        expect(await screen.findByText(/does not offer full screen/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Full screen/ })).toBeNull();
    });

    it('advances an approved ticket to cooking with one press', async () => {
        Object.defineProperty(HTMLElement.prototype, 'requestFullscreen', {
            configurable: true,
            writable: true,
            value: vi.fn(() => Promise.resolve()),
        });

        const user = userEvent.setup();
        renderMonitor();

        await user.click(await screen.findByRole('button', { name: /Start cooking/ }));

        await waitFor(() => {
            const call = vi
                .mocked(fetch)
                .mock.calls.find(([input]) => String(input).includes('/status'));

            expect(call).toBeDefined();
            expect(String((call?.[1] as RequestInit).body)).toContain('preparing');
        });
    });

    it('never draws the “handed to the table” button for the kitchen', async () => {
        vi.mocked(fetch).mockImplementation((input: RequestInfo | URL) => {
            if (String(input).includes('/orders/kitchen')) {
                return Promise.resolve(feed([ticket({ status: 'ready' })]));
            }

            return Promise.resolve({
                ok: true,
                status: 204,
                json: async () => ({}),
            } as unknown as Response);
        });

        renderMonitor({ canDeliver: false });

        await screen.findByText(/Masa 7/);

        /*
            "Teslim edildi" MUTFAĞIN değil servisin cümlesidir: tabağı masaya
            götüren kişi bilir. Aşçıda `order.confirm` yok; sunucu 403 döner
            ve ekranın işi o 403'ü ona hiç yaşatmamaktır (`docs/59`).
        */
        expect(screen.queryByRole('button', { name: /Handed to the table/ })).toBeNull();
    });

    it('explains an empty board instead of looking broken', async () => {
        vi.mocked(fetch).mockImplementation(() => Promise.resolve(feed([])));

        renderMonitor();

        expect(await screen.findByText(/Nothing to cook/)).toBeTruthy();
        // Aşçı garsona "sipariş gelmiyor mu" diye sormasın: bekleyen sipariş
        // buraya hiç düşmez ve bu ekranda yazılıdır.
        expect(screen.getByText(/Waiting orders are not shown/)).toBeTruthy();
    });
});
