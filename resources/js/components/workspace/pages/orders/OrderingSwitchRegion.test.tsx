import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { OrderingSwitchRegion } from './OrderingSwitchRegion';

/**
 * SİPARİŞ ALMA ŞALTERİ — `docs/115` S6, Y1 (FF-179).
 *
 * Göç sütunu VARSAYILAN KAPALI yazdı. Bu dosyanın sorduğu soru şu: sahip
 * panele baktığında kapalı olduğunu ANLIYOR ve açtığında ne söz verdiğini
 * biliyor mu? Yöneticinin ekranında ise basıldığında 403 dönecek bir düğme
 * hiç olmamalı.
 */

const WORKSPACE_ID = 7;
const LOCATION_ID = 3;

function switchState(acceptsOrders: boolean, canManage: boolean, planIncludesOrdering = true) {
    return {
        ok: true,
        status: 200,
        json: async () => ({
            locationId: LOCATION_ID,
            acceptsOrders,
            canManage,
            planIncludesOrdering,
            entitlement: 'ordering.basic',
        }),
    } as unknown as Response;
}

describe('OrderingSwitchRegion', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.resolve(switchState(false, true))),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('shows a new branch as switched off, the way the migration wrote it', async () => {
        render(<OrderingSwitchRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        expect(await screen.findByText('Ordering is off')).toBeTruthy();
        expect((await screen.findByRole('switch')).getAttribute('checked')).toBeNull();
    });

    it('says what turning it on commits the restaurant to', async () => {
        render(<OrderingSwitchRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        /*
            Sipariş alma, panelde birinin BAKMASINI gerektiren tek
            yetenektir. Bu cümle olmasaydı, sahip şalteri açar ve kimsenin
            bakmadığı bir kuyruğa sipariş düşerdi.
        */
        expect(await screen.findByText(/Somebody has to watch the queue/)).toBeTruthy();
    });

    it('turns ordering on and reports it back to the page', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(
            <OrderingSwitchRegion
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onChange={onChange}
            />,
        );

        await user.click(await screen.findByRole('switch'));

        await waitFor(() => {
            const call = vi
                .mocked(fetch)
                .mock.calls.find(([, init]) => (init as RequestInit | undefined)?.method === 'PUT');

            expect(call).toBeDefined();
            expect(String((call?.[1] as RequestInit).body)).toContain('"acceptsOrders":true');
        });

        await waitFor(() => expect(screen.getByText('Ordering is on')).toBeTruthy());
        // Kuyruk sekmesi boş listeyi doğru cümleyle açıklayabilsin diye.
        expect(onChange).toHaveBeenCalledWith(true);
    });

    it('does not draw a switch a manager would only get a 403 from', async () => {
        vi.mocked(fetch).mockImplementation(() => Promise.resolve(switchState(false, false)));

        render(<OrderingSwitchRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        // `docs/59`: yapılamayan iş çizilmez. Sunucu zaten 403 döner; ekranın
        // işi o 403'ü kullanıcıya yaşatmamak ve KİMİN yapabileceğini söylemek.
        expect(await screen.findByText(/Only the workspace owner/)).toBeTruthy();
        expect((await screen.findByRole('switch')).hasAttribute('disabled')).toBe(true);
    });

    /**
     * Y3 — PLANIN VERMEDİĞİ SÖZ, EKRANDA DA VERİLEMEZ.
     *
     * Ölçülen kusur: şalter açılıyordu, misafirin siparişi reddediliyordu.
     * Sahip hizmeti açtığını sanıyor, mutfağa hiçbir şey düşmüyordu. Burada
     * donan SONUÇ: şalter çevrilemez ve sebebi hakkın adıyla yazılır.
     */
    it('does not let the owner promise a service the plan cannot deliver', async () => {
        const user = userEvent.setup();
        const onNavigateToPlan = vi.fn();

        vi.mocked(fetch).mockImplementation(() => Promise.resolve(switchState(false, true, false)));

        render(
            <OrderingSwitchRegion
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onNavigateToPlan={onNavigateToPlan}
            />,
        );

        expect(await screen.findByText(/Taking orders from tables/)).toBeTruthy();
        expect((await screen.findByRole('switch')).hasAttribute('disabled')).toBe(true);
        // Bozulmuş bir şey yok: bu bir kısıt, hata değil (`docs/59`).
        expect(screen.queryByRole('alert')).toBeNull();

        // Çıkış yolu gerçek: "tekrar deneyin" değil, plan.
        await user.click(screen.getByRole('button', { name: /View your plan/ }));
        expect(onNavigateToPlan).toHaveBeenCalled();
    });

    /**
     * Y3 — HAK DÜŞTÜĞÜNDE EKRAN "AÇIK AMA ÇALIŞMIYOR" DER.
     *
     * Abonelik bitince şalter açık kalmış olabilir. Sessizce kapatmak daha
     * temiz görünürdü ve daha kötü olurdu: sahip ayarının arkasından
     * değiştiğini bilmez, planı geri geldiğinde neyi kaybettiğini de
     * bilmezdi.
     */
    it('says the switch is on while nothing can arrive, and does not close it behind the owner', async () => {
        vi.mocked(fetch).mockImplementation(() => Promise.resolve(switchState(true, true, false)));

        render(<OrderingSwitchRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        expect(await screen.findByText('Ordering is on')).toBeTruthy();
        expect(await screen.findByText(/no longer part of your plan/)).toBeTruthy();

        // Ekran kendi kendine hiçbir şey KAPATMADI.
        const wrote = vi
            .mocked(fetch)
            .mock.calls.some(([, init]) => (init as RequestInit | undefined)?.method === 'PUT');

        expect(wrote).toBe(false);

        // Ama sahip kapatabilir: planı düşmüş bir restoranı kendi hizmetini
        // kapatamadığı bir ekranda bırakmak, kilitlemek olurdu.
        expect((await screen.findByRole('switch')).hasAttribute('disabled')).toBe(false);
    });

    it('leaves the switch where it was when the save does not go through', async () => {
        const user = userEvent.setup();

        vi.mocked(fetch).mockImplementation((_input, init?: RequestInit) => {
            if (init?.method === 'PUT') {
                return Promise.resolve({
                    ok: false,
                    status: 500,
                    json: async () => ({}),
                } as unknown as Response);
            }

            return Promise.resolve(switchState(false, true));
        });

        render(<OrderingSwitchRegion workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await user.click(await screen.findByRole('switch'));

        /*
            Şalter ancak sunucu evet dedikten sonra hareket eder. İyimser bir
            çevirme, tutmayan bir istekten sonra sahibe "sipariş alıyorum"
            diyen KAPALI bir şube bırakırdı — ve o akşam hiç sipariş gelmezdi.
        */
        expect(await screen.findByText(/could not be changed/)).toBeTruthy();
        expect(screen.getByText('Ordering is off')).toBeTruthy();
    });
});
