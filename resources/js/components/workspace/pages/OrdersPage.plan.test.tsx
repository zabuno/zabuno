import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

import { OrdersPage } from './OrdersPage';

/**
 * SİPARİŞ BÖLÜMÜ, PLAN KAPISININ ALTINDA — `docs/115` Y3.
 *
 * Şalterin ve kuyruğun kendi testleri var; burada donan şey ÜÇ SEKMENİN
 * AYNI GERÇEĞİ söylemesi. Plan bilgisi tek bir okumadan geliyor ve sayfada
 * duruyor: her sekme kendi okumasını yapsaydı, sahip aynı akşam bir
 * sekmede "planında yok", ötekinde "bugün sipariş yok" okurdu.
 *
 * Mutfak monitörü ayrıca kritik: boş bir monitör aşçı için "bu akşam
 * sakin" demektir ve o cümle burada YANLIŞTIR — sipariş gelmiyor değil,
 * gelemiyor.
 */
const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function respond(body: unknown, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as unknown as Response;
}

/** Plan kapısı kapalı bir çalışma alanı: şalter açık, hak yok. */
function mockWorkspace(planIncludesOrdering: boolean) {
    vi.stubGlobal(
        'fetch',
        vi.fn((input: RequestInfo | URL) => {
            const url = String(input);

            if (url.endsWith('/ordering')) {
                return Promise.resolve(
                    respond({
                        locationId: LOCATION_ID,
                        acceptsOrders: true,
                        canManage: true,
                        planIncludesOrdering,
                        entitlement: 'ordering.basic',
                    }),
                );
            }

            return Promise.resolve(respond({ data: [], serverTime: new Date().toISOString() }));
        }),
    );
}

function renderOrders(subPath: string) {
    return render(
        <OrdersPage
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            subPath={subPath}
            onNavigate={() => undefined}
            can={() => true}
            renderKitchenMonitor={() => <p>KITCHEN MONITOR</p>}
        />,
    );
}

describe('OrdersPage plan kapısı', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    describe('plan sipariş almayı içermiyorken', () => {
        beforeEach(() => mockWorkspace(false));

        it('does not show the kitchen an empty monitor that reads as a quiet evening', async () => {
            renderOrders('kitchen');

            expect(await screen.findByText(/Nothing can reach the kitchen/)).toBeTruthy();
            // Monitör hiç çizilmez: üstünde tam ekran düğmesi ve "hiç iş
            // yok" cümlesi olan bir tahta, aşçıya bekleyeceğini söylerdi.
            expect(screen.queryByText('KITCHEN MONITOR')).toBeNull();
        });

        it('tells the queue the same truth the settings tab tells', async () => {
            renderOrders('');

            expect(await screen.findByText(/Orders cannot reach you/)).toBeTruthy();
        });
    });

    describe('plan sipariş almayı içeriyorken', () => {
        beforeEach(() => mockWorkspace(true));

        it('draws the monitor the kitchen was written for', async () => {
            renderOrders('kitchen');

            expect(await screen.findByText('KITCHEN MONITOR')).toBeTruthy();
        });
    });
});
