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
 * kurduğu planı EKRANDA, ŞUBENİN SAATİYLE gösterir (`docs/62`).
 *
 * SAATLER VE SAAT DİLİMİ SUNUCUDAN gelir; bu bileşen hesap yapmaz, yalnız
 * okunabilir hâle çevirir. Dilimi kendi sabitlemişti ve sonuç şuydu: sunucu
 * Berlin şubesi için 03:00'i kuruyor, ekran aynı anı "04:00" yazıyordu.
 * Sahibin okuduğu saat ile menünün değişeceği an ayrıldığı sürece, ikisi de
 * doğru olsa bile ürün yalan söylemiş olur.
 */
const OPTIONS_RESPONSE = {
    timeZone: 'Europe/Istanbul',
    plan: null,
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

    it('Berlin şubesinde saatleri BERLİN saatiyle yazar, İstanbul saatiyle değil', async () => {
        /*
            EKRANDA YAZAN SAAT ile MENÜNÜN GERÇEKTEN DEĞİŞECEĞİ AN aynı
            olmak zorundadır (`docs/62`: saat dilimi şubenindir). Ekran
            sabit `Europe/Istanbul` ile biçimlendirdiği sürece, Berlin
            şubesinin sahibi sunucunun kurduğu 03:00'lik planı "04:00"
            olarak okurdu — sayılar tutmadığı için de hangisine
            güveneceğini bilemezdi.

            `01:00Z` yaz saatinde Berlin'de 03:00, İstanbul'da 04:00'tür;
            ikisini ayıran tam da bu bir saattir.
        */
        fetchSpy.mockImplementation(async (url: string) =>
            /publications\/schedule$/.test(url)
                ? jsonResponse(200, {
                      timeZone: 'Europe/Berlin',
                      plan: null,
                      options: [{ key: 'tonight', scheduledFor: '2026-09-06T01:00:00.000000Z' }],
                  })
                : jsonResponse(404, { message: 'Not found' }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        const chip = await within(region()).findByRole('button', { name: /03:00/ });
        const label = chip.textContent ?? '';

        // Çipin iki yarısı da 03:00 der: sabit etiket ("Tonight 03:00") ve
        // sunucunun ANINDAN çevrilen saat. İkisi ayrıştığı an sahip hangisine
        // güveneceğini bilemez.
        expect(label.match(/03:00/g) ?? []).toHaveLength(2);
        expect(label).not.toMatch(/04:00/);
    });

    it('şubenin saat dilimi okunamıyorsa saat SEÇTİRMEZ', async () => {
        /*
            Saat dilimi bilinmeden "bu gece 03:00" demek, hangi gece hangi
            03:00 olduğunu bilmeden söz vermektir. Sessizce İstanbul'a
            düşmek, düzeltmeye çalıştığımız hatanın ta kendisidir; bu yüzden
            seçenek hiç çizilmez. Hemen yayınlamak her zaman açıktır.
        */
        fetchSpy.mockImplementation(async (url: string) =>
            /publications\/schedule$/.test(url)
                ? jsonResponse(200, { timeZone: null, plan: null, options: [] })
                : jsonResponse(404, { message: 'Not found' }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        expect(within(region()).queryAllByRole('button', { name: /\d\d:\d\d/ })).toHaveLength(0);
    });

    it('bir saat seçildiğinde planı SUNUCUYA yazar', async () => {
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        /*
            Plan kurulduktan sonra ekran hâli SUNUCUDAN yeniden okunur;
            POST cevabından "kuruldu" diye uydurulmaz.
        */
        let stored = false;

        fetchSpy.mockImplementation(async (_url: string, init?: RequestInit) => {
            if (init?.method === 'POST') {
                stored = true;

                return jsonResponse(201, {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'pending',
                    timeZone: 'Europe/Istanbul',
                });
            }

            return jsonResponse(200, {
                ...OPTIONS_RESPONSE,
                plan: stored
                    ? {
                          id: 9,
                          scheduledFor: '2026-09-06T00:00:00.000000Z',
                          state: 'pending',
                          status: 'scheduled',
                          needsAttention: false,
                      }
                    : null,
            });
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
                plan: {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'pending',
                    status: 'scheduled',
                    needsAttention: false,
                },
            }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        expect(await within(region()).findByText(/scheduled for/i)).toBeInTheDocument();
        expect(
            within(region()).getByRole('button', { name: /cancel this schedule/i }),
        ).toBeInTheDocument();
    });

    it('vakti geçmiş ama çıkmamış yayını SAKLAMAZ; menünün değişmediğini söyler', async () => {
        /*
            EN PAHALI YALAN BURADAYDI: zamanlayıcı ölmüşse kayıt `pending`
            kalır ve saat geçer. Ekran o an hâlâ "yarın 09:00 için
            zamanlandı" yazsaydı, sahip menüsünün değiştiğini sanırdı;
            misafir eski fiyatı okurken sahip bunu ancak kasada fark
            ederdi. Cümle SÖZ VERMEZ — "birazdan çıkacak" ya da tahmini bir
            süre yok, çünkü zamanlayıcının ne zaman döneceğini bilmiyoruz.
        */
        fetchSpy.mockImplementation(async () =>
            jsonResponse(200, {
                ...OPTIONS_RESPONSE,
                plan: {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'pending',
                    status: 'overdue',
                    needsAttention: true,
                },
            }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        const alert = await within(region()).findByRole('alert');

        expect(alert.textContent ?? '').toMatch(/did not happen/i);
        expect(alert.textContent ?? '').toMatch(/menu did not change/i);
        expect(alert.textContent ?? '').not.toMatch(/soon|shortly|in a few|will be published/i);

        // Sağlıklı bir planın sakin cümlesi ORTADA YOK.
        expect(region().textContent ?? '').not.toMatch(/scheduled for/i);
    });

    it('başarısız yayını sahip görür ve KAPATABİLİR', async () => {
        fetchSpy.mockImplementation(async () =>
            jsonResponse(200, {
                ...OPTIONS_RESPONSE,
                plan: {
                    id: 9,
                    scheduledFor: '2026-09-06T00:00:00.000000Z',
                    state: 'failed',
                    status: 'failed',
                    needsAttention: true,
                },
            }),
        );

        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        const alert = await within(region()).findByRole('alert');

        expect(alert.textContent ?? '').toMatch(/could not be saved/i);
        expect(
            within(region()).getByRole('button', { name: /dismiss this notice/i }),
        ).toBeInTheDocument();
    });

    it('kuralın kendisini yazar: zamanlanmış yayın da bir yayındır, QR aynı kalır', async () => {
        render(<PublishScheduleRegion workspaceId={71} menuId={42} draftReady />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const text = region().textContent ?? '';

        expect(text).toMatch(/next version number/i);
        expect(text).toMatch(/QR code stays the same/i);
        /*
            Cümle bir ŞEHİR adı vermez, ŞUBEYİ işaret eder (`docs/62`).
            "Istanbul" yazdığı sürece Berlin şubesinin sahibi doğru
            hesaplanmış bir saati yanlış şehirle okurdu.
        */
        expect(text).toMatch(/location's own time zone/i);
        expect(text).not.toMatch(/Istanbul/i);
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
