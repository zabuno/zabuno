import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrDestinationRegion } from './QrDestinationRegion';

/**
 * QR-HONEST-STATE — FF-108, `docs/104` Döngü 4: "sayfa yalan söylemez".
 *
 * Somut arıza: `useCurrentPublication`, cevap YOLDAYKEN de sunucu 500
 * DÖNDÜĞÜNDE de `current: null` verir. Ekran yalnız ona bakıyordu ve üç ayrı
 * dünyayı tek cümleye indiriyordu — "önce menünüzü yayınlayın". Yayında bir
 * menüsü, masalarında basılı ve çalışan kartları olan restoran sahibine,
 * kodlarının hiç var olmadığı söyleniyordu; sahibin oradan çıkardığı sonuç
 * "yeniden yayınlayayım" ya da "yeniden basayım" olurdu.
 *
 * İkinci arıza aynı kökten: kod listesi de `hasCurrentPublication` false iken
 * hiç ÇEKİLMİYORDU. `loaded` false kaldığı için ne "yükleniyor" ne de "boş"
 * yazıyordu — ekranda hiçbir şey yoktu ve hiçbir açıklama da yoktu.
 *
 * Üçüncüsü: sunucu toplu üretim için bilerek 402 + `entitlement` döndürüyor,
 * istemci ise 201 olmayan her cevabı "Tekrar deneyin." diye gösteriyordu.
 * Tekrar denemek hiçbir zaman işe yaramaz; çıkış yolu plan yükseltmesidir.
 */

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

const ITEM = {
    id: 4021,
    workspaceId: 71,
    locationId: 923,
    menuId: 42,
    token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    resolverUrl: 'https://zabuno.test/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
    destinationType: 'published_menu',
    state: 'active',
};

describe('QrDestinationRegion — üç hâl ayrı (QR-HONEST-STATE)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('yayın bilgisi yoldayken "önce yayınlayın" DEMEZ, beklemeyi söyler', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoading
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByText(/checking whether your menu/i)).toBeInTheDocument();
        });

        expect(region.textContent ?? '').not.toMatch(/publish your menu first/i);
    });

    it('yayın sorgusu başarısızsa basılı kodların çalışmaya devam ettiğini söyler', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoadFailed
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByRole('alert')).toHaveTextContent(
                /could not reach the server/i,
            );
        });

        expect(within(region).getByRole('alert')).toHaveTextContent(/printed codes keep working/i);
        expect(region.textContent ?? '').not.toMatch(/publish your menu first/i);
    });

    it('yayın bilinmese bile VAR OLAN kodları çeker ve gösterir', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, [ITEM]));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
                publicationLoadFailed
            />,
        );

        await waitFor(() => {
            expect(screen.getByRole('link', { name: new RegExp(ITEM.token) })).toBeInTheDocument();
        });

        expect(
            fetchSpy.mock.calls.some(([url]) =>
                /brand\/locations\/923\/qr-codes$/.test(String(url)),
            ),
        ).toBe(true);
    });

    it('yayın gerçekten yokken sebep "önce yayınlayın" olur', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            expect(within(region).getByText(/publish your menu first/i)).toBeInTheDocument();
        });

        expect(within(region).getByRole('button', { name: /create/i })).toBeDisabled();
    });
});

describe('BulkQrWizardFields — plan kısıtı hata değildir (QR-HONEST-STATE)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('402 gelince "tekrar deneyin" DEMEZ; plan yükseltme yolunu gösterir', async () => {
        const user = userEvent.setup();
        const onUpgrade = vi.fn();

        fetchSpy.mockImplementation((url: unknown, init?: RequestInit) => {
            const href = String(url);

            if (/tables\/bulk$/.test(href) && init?.method === 'POST') {
                return Promise.resolve(
                    jsonResponse(402, {
                        message: 'Plan does not include bulk QR generation.',
                        entitlement: 'qr.bulk_generation',
                    }),
                );
            }

            return Promise.resolve(jsonResponse(200, []));
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication
                onUpgrade={onUpgrade}
            />,
        );

        await user.type(screen.getByLabelText(/table count/i), '12');
        await user.click(screen.getByRole('button', { name: /create table qr codes/i }));

        await waitFor(() => {
            expect(screen.getByText(/not included in your current plan/i)).toBeInTheDocument();
        });

        expect(screen.queryByText(/could not create/i)).toBeNull();

        await user.click(screen.getByRole('button', { name: /see plans/i }));
        expect(onUpgrade).toHaveBeenCalledTimes(1);
    });

    it('toplu sihirbazın düğmesi kapalıyken sebebi yazılıdır', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
            />,
        );

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });

        await waitFor(() => {
            expect(within(wizard).getByText(/publish your menu first/i)).toBeInTheDocument();
        });
    });
});
