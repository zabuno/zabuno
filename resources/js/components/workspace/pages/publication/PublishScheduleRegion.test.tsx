import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { PublishScheduleRegion } from './PublishScheduleRegion';

/**
 * PLANLA — zamanlanmış yayın (kanonik kaynaktaki "Planla" düğmesi; sahibin
 * 2026-09-05 kararı: çizilecek VE çalışacak).
 *
 * NEDEN BU TESTLER: çalışmayan bir "Planla" düğmesi, restoran sahibinin
 * gece 03:00'te yeni fiyatların yayına gireceğini SANMASINA yol açar;
 * sabah misafir hâlâ eski fiyatı okurken sahip bunu ancak kasada fark
 * eder. Bu yüzden düğme yalnız gerçek bir plan kurabildiğinde çizilir ve
 * kurduğu planı EKRANDA, İSTANBUL SAATİYLE gösterir.
 *
 * SAATLER SUNUCUDAN gelir; bu bileşen hesap yapmaz, yalnız okunabilir hâle
 * çevirir. Tarayıcıda hesaplansaydı Berlin'den panele giren bir ortak "bu
 * gece 03:00" dediğinde menü Türkiye'de 04:00'te değişirdi.
 */
const OPTIONS_RESPONSE = {
    timeZone: 'Europe/Istanbul',
    pending: null,
    options: [
        { key: 'tonight', scheduledFor: '2026-09-06T00:00:00.000000Z' },
        { key: 'tomorrowMorning', scheduledFor: '2026-09-06T06:00:00.000000Z' },
        { key: 'nextMonday', scheduledFor: '2026-09-07T06:00:00.000000Z' },
    ],
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as unknown as Response;
}

let fetchSpy: ReturnType<typeof vi.fn>;

beforeEach(() => {
    fetchSpy = vi.fn(async (url: string) => {
        if (/publications\/schedule$/.test(url)) {
            return jsonResponse(200, OPTIONS_RESPONSE);
        }

        return jsonResponse(404, { message: 'Not found' });
    });

    vi.stubGlobal('fetch', fetchSpy);
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

function region(): HTMLElement {
    return screen.getByRole('region', { name: /schedule the publish/i });
}

describe('PublishScheduleRegion — plan gerçekten kurulur', () => {
    it('sunucudan gelen saat seçeneklerini İstanbul saatiyle yazar', async () => {
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const options = await within(region()).findAllByRole('button', { name: /03:00|09:00/ });

        expect(options.length).toBeGreaterThanOrEqual(3);
    });

    it('bir saat seçildiğinde planı SUNUCUYA yazar', async () => {
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        fetchSpy.mockImplementation(async (_url: string, init?: RequestInit) => {
            if (init?.method === 'POST') {
                return jsonResponse(201, {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'pending',
                    timeZone: 'Europe/Istanbul',
                });
            }

            return jsonResponse(200, OPTIONS_RESPONSE);
        });

        const option = (await within(region()).findAllByRole('button', { name: /03:00/ }))[0];
        await userEvent.click(option);

        await waitFor(() => {
            expect(
                fetchSpy.mock.calls.some(
                    ([url, init]) =>
                        /publications\/schedule$/.test(String(url)) &&
                        (init as RequestInit | undefined)?.method === 'POST',
                ),
            ).toBe(true);
        });

        expect(await within(region()).findByText(/scheduled for/i)).toBeInTheDocument();
    });

    it('kurulu bir plan varsa onu ve İPTAL yolunu gösterir', async () => {
        fetchSpy.mockImplementation(async () =>
            jsonResponse(200, {
                ...OPTIONS_RESPONSE,
                pending: {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'pending',
                },
            }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        expect(await within(region()).findByText(/scheduled for/i)).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: /cancel this schedule/i }),
        ).toBeInTheDocument();
    });

    it('kuralın kendisini yazar: zamanlanmış yayın da bir yayındır, QR aynı kalır', async () => {
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const text = region().textContent ?? '';

        expect(text).toMatch(/next version number/i);
        expect(text).toMatch(/QR code stays the same/i);
        expect(text).toMatch(/Istanbul/i);
    });

    it('taslak hazır değilken saat SEÇTİRMEZ ve sebebini yazar', async () => {
        /*
            Plan, o anki içeriği DONDURUR. Hazır olmayan bir taslağı
            planlamak, gece 03:00'te sessizce başarısız olacak bir yayın
            kurmak olurdu — sahip sabaha kadar menüsünün değişeceğini
            sanardı.
        */
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady={false} />);

        expect(within(region()).queryAllByRole('button', { name: /03:00/ })).toHaveLength(0);
        expect(region().textContent ?? '').toMatch(/readiness list/i);
    });

    it('sabit piksel, kırılım noktası veya yasak sınıf taşımaz', async () => {
        const { container } = render(
            <PublishScheduleRegion workspaceId={71} menuId={42} draftReady />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const classNames = Array.from(container.querySelectorAll<HTMLElement>('*'))
            .map((element) => (typeof element.className === 'string' ? element.className : ''))
            .join(' ');

        expect(classNames).not.toMatch(/(^|\s)(sm|md|lg|xl|2xl):/);
        expect(classNames).not.toMatch(/\[\d+px\]/);
        expect(classNames).not.toMatch(/font-semibold/);
        expect(classNames).not.toMatch(/rounded-full/);
    });
});
