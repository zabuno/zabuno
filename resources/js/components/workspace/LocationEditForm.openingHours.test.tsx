import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { LocationEditForm, type LocationProfile } from './LocationEditForm';

/**
 * ŞUBE DÜZENLEME FORMU — ÇALIŞMA SAATİ GÖNDERİMİ (`docs/109` §6.4).
 *
 * NEDEN KIRMIZI: form saatleri HER kaydette gönderiyordu — saati hiç
 * olmayan, sahibinin de hiç dokunmadığı bir şubede bile boş dizi
 * yollayarak. Bu, sunucunun "alan yoksa DOKUNMA" sözleşmesini boşa
 * düşürür: istemci hiçbir şey söylemek istemediği hâlde her seferinde
 * "sil" diyordu.
 *
 * Zararı bugün görünmez çünkü silinecek bir şey yok. Ama sözleşme tam da
 * bunun için var: yarın saatleri başka bir yüzeyden (kurulum sihirbazı,
 * içe aktarma, mobil) girilen bir şube, adresini düzelten bu formla
 * sessizce saatlerini kaybederdi.
 *
 * KURAL: alan yalnız SÖYLENECEK BİR ŞEY VARSA gönderilir —
 *   - taslak doluysa (hafta girilmiş/değiştirilmiş), ya da
 *   - şubede kayıtlı saat VARDI ve sahip onu kapattıysa (silme isteği).
 */
const BASE: LocationProfile = {
    id: 42,
    workspace_id: 7,
    brand_id: 3,
    display_name: 'Zeytin Kadıköy',
    country_code: 'TR',
    timezone: 'Europe/Istanbul',
    city: 'İstanbul',
    address_line1: 'Moda Caddesi 12',
    address_line2: null,
    postal_code: null,
};

const WEEK = [1, 2, 3, 4, 5, 6, 7].map((day) => ({
    day,
    closed: false,
    opens_minute: 540,
    closes_minute: 1380,
}));

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
    fetchMock = vi.fn(async (url: unknown) => {
        if (String(url).includes('/sanctum/csrf-cookie')) {
            return { headers: new Headers(), ok: true, status: 204, json: async () => ({}) };
        }

        return {
            headers: new Headers(),
            ok: true,
            status: 200,
            json: async () => BASE,
        };
    });

    vi.stubGlobal('fetch', fetchMock);
});

afterEach(() => {
    vi.unstubAllGlobals();
    cleanup();
});

/** Formu düzenleme kipine alır ve Kaydet'e basar; giden gövdeyi döndürür. */
async function submitAndReadBody(location: LocationProfile): Promise<Record<string, unknown>> {
    const user = userEvent.setup();

    render(<LocationEditForm workspaceId={7} location={location} onSaved={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: `Edit ${location.display_name}` }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    const put = fetchMock.mock.calls.find(
        (call) => (call[1] as RequestInit | undefined)?.method === 'PUT',
    );

    return JSON.parse(String((put?.[1] as RequestInit).body)) as Record<string, unknown>;
}

describe('LocationEditForm — çalışma saati gönderimi', () => {
    it('saati olmayan ve dokunulmayan şubede alanı HİÇ göndermez', async () => {
        const body = await submitAndReadBody(BASE);

        expect('opening_hours' in body).toBe(false);
    });

    it('kayıtlı saatleri olan şube haftayı geri gönderir', async () => {
        const body = await submitAndReadBody({ ...BASE, opening_hours: WEEK });

        expect(body.opening_hours).toEqual(WEEK);
    });

    /**
     * Sahip "artık söylemiyorum" diyebilmeli. Kutuyu kaldırdığında BOŞ DİZİ
     * gider ve sunucu kayıtlı saatleri siler — kartta o satır bir daha
     * çizilmez.
     */
    it('kayıtlı saatler kapatılırsa silme isteği gönderir', async () => {
        const user = userEvent.setup();

        render(
            <LocationEditForm
                workspaceId={7}
                location={{ ...BASE, opening_hours: WEEK }}
                onSaved={vi.fn()}
            />,
        );

        await user.click(screen.getByRole('button', { name: `Edit ${BASE.display_name}` }));
        await user.click(screen.getByRole('checkbox', { name: 'This location has opening hours' }));
        await user.click(screen.getByRole('button', { name: 'Save' }));

        const put = fetchMock.mock.calls.find(
            (call) => (call[1] as RequestInit | undefined)?.method === 'PUT',
        );
        const body = JSON.parse(String((put?.[1] as RequestInit).body)) as Record<string, unknown>;

        expect(body.opening_hours).toEqual([]);
    });
});
