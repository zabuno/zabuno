import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { OrderQueueDesktop } from './OrderQueueDesktop';

/**
 * MASAÜSTÜ KUYRUĞU — `docs/149` §6.
 *
 * Burada donan şey KUYRUĞUN VERİSİ DEĞİL: onu mobil kuyruğun testi zaten
 * donduruyor ve ikisi aynı `useOrderFeed`/`changeOrderStatus` yolunu
 * kullanıyor. Bu dosyanın sorusu tek: **masaüstüne özgü GİRİŞ KİPİ
 * gerçekten var mı?**
 *
 * Yani ölçülen şey klavyeyle gezinme, çoklu seçim, toplu işlem ve sağ tıkın
 * KLAVYE KARŞILIĞI. Bunlar dokunmada yoktur; biri sessizce kaybolursa ekran
 * yine çizilir ve kimse fark etmez — kasadaki kişi yalnız işini yavaş yapar.
 * Bir kısayolun yok olması ancak bir test tutuyorsa görülür.
 */

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function order(id: number, tableName: string) {
    return {
        id,
        status: 'pending',
        tableName,
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
                allergens: ['milk'],
            },
        ],
    };
}

const statusCalls: { url: string; body: unknown }[] = [];

function renderQueue() {
    return render(
        <OrderQueueDesktop
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            acceptsOrders={true}
            planIncludesOrdering={true}
            onNavigateToSettings={() => undefined}
            onNavigateToPlan={() => undefined}
        />,
    );
}

async function rows() {
    return within(await screen.findByRole('listbox')).findAllByRole('option');
}

describe('OrderQueueDesktop', () => {
    beforeEach(() => {
        statusCalls.length = 0;

        vi.stubGlobal(
            'fetch',
            vi.fn((input: RequestInfo | URL, init?: RequestInit) => {
                const url = String(input);

                if (url.includes('/orders/pending')) {
                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        json: async () => ({
                            data: [order(41, 'Masa 1'), order(42, 'Masa 2'), order(43, 'Masa 3')],
                            serverTime: new Date().toISOString(),
                        }),
                    } as unknown as Response);
                }

                if (url.includes('/status')) {
                    statusCalls.push({
                        url,
                        body: JSON.parse(String(init?.body ?? '{}')) as unknown,
                    });

                    return Promise.resolve({
                        ok: true,
                        status: 200,
                        json: async () => ({ status: 'confirmed' }),
                    } as unknown as Response);
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

    /*
        Liste bir SEÇİLEBİLİR listedir, bir kart yığını değil. Ekran
        okuyucunun "3 öğeden 1'i, seçili değil" diyebilmesi bu rollere bağlı;
        `div` yığınında o cümle hiç kurulmaz.
    */
    it('kuyruğu çoklu seçilebilir bir liste olarak sunar', async () => {
        renderQueue();

        const listbox = await screen.findByRole('listbox');

        expect(listbox).toHaveAttribute('aria-multiselectable', 'true');
        expect(await rows()).toHaveLength(3);
    });

    /*
        ROVING TABINDEX: listeye Tab ile BİR KEZ girilir. Her satır
        odaklanabilir olsaydı, on siparişlik kuyruk on Tab demekti — ve bu
        ekranın asıl kullanıcısı klavyeyle çalışıyor.
    */
    it('ok tuşlarıyla satır değiştirir ve odağı taşır', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        expect(all[0]).toHaveAttribute('tabindex', '0');
        expect(all[1]).toHaveAttribute('tabindex', '-1');

        all[0]?.focus();
        await user.keyboard('{ArrowDown}');

        await waitFor(() => {
            expect(document.activeElement).toBe(screen.getAllByRole('option')[1]);
        });
    });

    /*
        ÇOKLU SEÇİM bu ekranın var oluş sebebi. Sekiz siparişi tek tek
        onaylamak, kasadaki kişinin akşamıdır.
    */
    it('boşlukla seçer ve seçilenleri tek işlemde onaylar', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        all[0]?.focus();
        await user.keyboard(' ');
        await user.keyboard('{ArrowDown}');
        await user.keyboard(' ');

        await waitFor(() => {
            expect(screen.getByText('2 selected')).toBeInTheDocument();
        });

        await user.click(screen.getByRole('button', { name: 'Approve 2' }));

        await waitFor(() => {
            expect(statusCalls).toHaveLength(2);
        });

        expect(statusCalls.map((call) => call.body)).toEqual([
            { status: 'confirmed' },
            { status: 'confirmed' },
        ]);
    });

    it('Enter üzerinde durulan siparişi onaylar', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        all[0]?.focus();
        await user.keyboard('{Enter}');

        await waitFor(() => {
            expect(statusCalls).toHaveLength(1);
        });

        expect(statusCalls[0]?.url).toContain('/orders/41/status');
    });

    /*
        SAĞ TIKIN KLAVYE KARŞILIĞI — WCAG 2.2 AA (`docs/149` §7).

        Menü yalnız fareyle açılabilseydi, oradaki eylemler klavye
        kullanıcısı için HİÇ yok olurdu. Shift+F10 her masaüstü
        tarayıcısında bu işi yapar.
    */
    it('Shift+F10 ile bağlam menüsünü klavyeden açar', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        all[0]?.focus();

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();

        await user.keyboard('{Shift>}{F10}{/Shift}');

        const menu = await screen.findByRole('menu');

        expect(within(menu).getAllByRole('menuitem')).toHaveLength(2);
    });

    it('sağ tık aynı menüyü açar', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        await user.pointer({ keys: '[MouseRight]', target: all[0] as Element });

        expect(await screen.findByRole('menu')).toBeInTheDocument();
    });

    /*
        RET SEBEPSİZ OLAMAZ — mobil kuyrukla AYNI ürün kuralı, çünkü sebep
        misafirin ekranında görünür. Toplu rette de tek sebep sorulur ve
        gönderilmeden önce boş olamaz.
    */
    it('sebep yazılmadan ret göndermez', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        all[0]?.focus();
        await user.keyboard('r');

        await user.click(screen.getByRole('button', { name: 'Reject order' }));

        expect(await screen.findByText(/never rejected in silence/i)).toBeInTheDocument();
        expect(statusCalls).toHaveLength(0);
    });

    it('sebep yazılınca reti gönderir', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();

        all[0]?.focus();
        await user.keyboard('r');

        await user.type(screen.getByRole('textbox', { name: 'Reason' }), 'Ürün bitti');
        await user.click(screen.getByRole('button', { name: 'Reject order' }));

        await waitFor(() => {
            expect(statusCalls).toHaveLength(1);
        });

        expect(statusCalls[0]?.body).toEqual({ status: 'rejected', reason: 'Ürün bitti' });
    });

    /*
        KALICI AYRINTI BÖLMESİ: telefonda ikinci sütun yoktur ve aynı bilgi
        kartın içindedir. Burada ayrı durması, satırların tarama için kısa
        kalmasını sağlar.
    */
    it('üzerinde durulan siparişin ayrıntısını yan bölmede gösterir', async () => {
        const user = userEvent.setup();

        renderQueue();

        const all = await rows();
        const detail = screen.getByRole('complementary', { name: 'Selected order' });

        expect(within(detail).getByRole('heading', { name: 'Table Masa 1' })).toBeInTheDocument();

        all[0]?.focus();
        await user.keyboard('{ArrowDown}');

        await waitFor(() => {
            expect(
                within(screen.getByRole('complementary', { name: 'Selected order' })).getByRole(
                    'heading',
                    { name: 'Table Masa 2' },
                ),
            ).toBeInTheDocument();
        });
    });
});
