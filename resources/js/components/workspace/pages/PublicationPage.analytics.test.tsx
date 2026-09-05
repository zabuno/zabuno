import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const trackEvent = vi.hoisted(() => vi.fn());
vi.mock('@/lib/analytics', () => ({ trackEvent }));

import { PublicationPage } from './PublicationPage';
import type { DashboardMenuTree } from './DashboardPage';
import { resetSignupAge, setSignupAgeMinutes } from '../../../lib/analyticsEvents';

/**
 * TIME TO FIRST QR (`docs/112` §4.1) — taksonominin en değerli satırı.
 *
 * Kullanıcı yolculuğu: Mehmet Usta kaydolur, menüsünü kurar ve "Yayınla"ya
 * basar. Bu iki an arasındaki süre bugüne kadar HİÇBİR YERDE ölçülmüyordu ve
 * `docs/110` §7'deki "kurulum 5 dakika mı 15 dakika mı" tartışması bu sayı
 * olmadan kapanamaz.
 *
 * Burada dondurulan üç karar: olayın YALNIZ ilk yayında basılması, ürün
 * sayısının yayınlanan kopyadan sayılması, ve süre bilinmiyorsa alanın HİÇ
 * gönderilmemesi.
 */
function makeMenuTree(): DashboardMenuTree {
    return {
        id: 42,
        workspaceId: 71,
        locationId: 923,
        name: 'Ana Menü',
        state: 'draft',
        categories: [
            {
                id: 5,
                menuId: 42,
                name: 'Starters',
                position: 0,
                menuItems: [
                    {
                        id: 101,
                        categoryId: 5,
                        productId: 901,
                        productName: 'Kahve',
                        priceMinorAmount: 4250,
                        currencyCode: 'TRY',
                        position: 0,
                        allergens: ['milk'],
                        isVisible: true,
                    },
                ],
            },
        ],
    };
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function publishedBody(version: number) {
    return {
        id: 900,
        workspaceId: 71,
        menuId: 42,
        locationId: 923,
        version,
        state: 'published',
        publishedAt: '2026-08-22T09:00:00Z',
        snapshot: {
            categories: [
                { name: 'Starters', menuItems: [{ productName: 'Kahve' }] },
                {
                    name: 'Mains',
                    menuItems: [{ productName: 'Köfte' }, { productName: 'Pilav' }],
                },
            ],
        },
    };
}

describe('first_publish_completed (docs/112 §4.1)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    function respondWith(version: number) {
        fetchSpy.mockImplementation(async (url: string) => {
            if (/sanctum\/csrf-cookie/.test(url)) return jsonResponse(204, null);
            if (/publications\/current/.test(url)) return jsonResponse(404, { message: 'none' });
            if (/publications$/.test(url)) return jsonResponse(201, publishedBody(version));

            return jsonResponse(404, { message: 'none' });
        });
    }

    async function publish() {
        const user = userEvent.setup();

        render(<PublicationPage workspaceId={71} dashboardMenuTree={makeMenuTree()} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const statusRegion = screen.getByRole('region', { name: /publication status/i });

        await user.click(
            within(statusRegion).getByRole('checkbox', {
                name: /reviewed the publish checklist/i,
            }),
        );
        await user.click(within(statusRegion).getByRole('button', { name: /publish/i }));

        await waitFor(() =>
            expect(fetchSpy.mock.calls.some(([url]) => /publications$/.test(url))).toBe(true),
        );
    }

    beforeEach(() => {
        trackEvent.mockClear();
        resetSignupAge();
        document.cookie = 'XSRF-TOKEN=ff167-test-token';
        fetchSpy = vi.fn().mockResolvedValue(jsonResponse(404, { message: 'none' }));
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        resetSignupAge();
    });

    it('measures how long the owner took, counting the menu the guest will actually see', async () => {
        setSignupAgeMinutes(23);
        respondWith(1);

        await publish();

        await waitFor(() =>
            expect(trackEvent).toHaveBeenCalledWith('first_publish_completed', {
                minutes_since_signup: 23,
                item_count: 3,
            }),
        );
    });

    /**
     * Sunucu "ilk"i söyler.
     *
     * Sahip paneli iki sekmede açmış ya da yayını bir ekip arkadaşı yapmış
     * olabilir; istemcinin elindeki "önce yayın var mıydı?" bilgisi bunu
     * güvenilir söyleyemez. Sürüm numarası yayının kendi kaydından gelir.
     */
    it('stays silent on every publish after the first', async () => {
        setSignupAgeMinutes(23);
        respondWith(4);

        await publish();

        expect(
            trackEvent.mock.calls.filter(([name]) => name === 'first_publish_completed'),
        ).toHaveLength(0);
    });

    /**
     * `docs/112` §3.4 — değeri olmayan alan HİÇ gönderilmez.
     *
     * Sunucu hesabın yaşını bildirmediyse (eski gövde) "0 dakika" yazmak,
     * "hemen yayınladı" diyen bir satır üretirdi ve ortalamayı aşağı çekerdi.
     */
    it('omits the duration entirely when the server never said how old the account is', async () => {
        respondWith(1);

        await publish();

        await waitFor(() =>
            expect(trackEvent).toHaveBeenCalledWith('first_publish_completed', {
                item_count: 3,
            }),
        );
    });
});
