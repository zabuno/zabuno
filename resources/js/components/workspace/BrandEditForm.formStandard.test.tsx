import { describe, expect, it, vi, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrandEditForm, type BrandProfile } from './BrandEditForm';

/**
 * FORM STANDARDI — `docs/47` ve marka formu raporu.
 *
 * Raporun kök tespiti: veri tabanı ve API alanları olduğu gibi kullanıcı
 * formuna çıkarılmıştı. Kullanıcı marka sahibi olmak isterken sistem ondan
 * `Europe/Istanbul`, `TRY` ve `tr` gibi geliştirici kodlarını BİLMESİNİ ve
 * YAZMASINI bekliyordu.
 */

const WORKSPACE_ID = 41;

const brand: BrandProfile = {
    id: 3,
    workspace_id: WORKSPACE_ID,
    name: 'Olga Restaurant',
    slug: 'olga-restaurant-6x4f08',
    locale: 'tr',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: null,
    contact_email: null,
    contact_phone: null,
};

function jsonResponse(
    status: number,
    body: unknown,
    headers: Record<string, string> = {},
): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: { get: (name: string) => headers[name] ?? null },
        json: async () => body,
    } as unknown as Response;
}

const REFERENCE = {
    markets: [
        { code: 'TR', name: 'Türkiye' },
        { code: 'DE', name: 'Germany' },
    ],
    currencies: [
        { code: 'TRY', name: 'Turkish lira', symbol: '₺' },
        { code: 'EUR', name: 'Euro', symbol: '€' },
    ],
    locales: [
        { code: 'tr', name: 'Turkish' },
        { code: 'en', name: 'English' },
    ],
    timezones: [{ id: 'Europe/Istanbul', label: 'İstanbul — UTC+03:00' }],
    defaults: { timezone: 'Europe/Istanbul', currency: 'TRY' },
    suggestedCountry: 'TR',
};

function stubFetch(onSave: (init?: RequestInit) => Response, reference: unknown = REFERENCE) {
    const spy = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
        const url = String(input);

        if (url === '/sanctum/csrf-cookie') return jsonResponse(204, {});
        if (url.startsWith('/api/reference/markets')) return jsonResponse(200, reference);

        return onSave(init);
    });

    vi.stubGlobal('fetch', spy);

    return spy;
}

async function openEditor(): Promise<ReturnType<typeof userEvent.setup>> {
    const user = userEvent.setup();

    render(<BrandEditForm workspaceId={WORKSPACE_ID} brand={brand} onSaved={vi.fn()} />);
    await user.click(screen.getByRole('button', { name: /edit/i }));

    return user;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('BrandEditForm — form standardı', () => {
    /**
     * Kullanıcı bir SEÇİM yapar, kod YAZMAZ.
     *
     * Serbest metin kutusuyla kullanıcı `ISTANBUL`, `Turkey`, `GMT+3` ya da
     * `TL` yazabilirdi. Sunucu bunların hiçbirini kabul etmez (IANA ve
     * ISO-4217 doğrular) ve kullanıcı ne yazması gerektiğini hiçbir yerden
     * öğrenemezdi.
     */
    it('dil, saat dilimi ve para birimi serbest metin değil, seçenek listesidir', async () => {
        stubFetch(() => jsonResponse(200, brand));
        await openEditor();

        await waitFor(() => {
            expect(screen.getByLabelText(/time zone/i).tagName).toBe('SELECT');
        });

        expect(screen.getByLabelText(/currency/i).tagName).toBe('SELECT');
        expect(screen.getByLabelText(/menu language/i).tagName).toBe('SELECT');
    });

    /**
     * Ekranda AD görünür, sistemde KOD saklanır.
     */
    it('kod yerine insan tarafından okunabilir ad gösterir', async () => {
        stubFetch(() => jsonResponse(200, brand));
        await openEditor();

        await waitFor(() => {
            expect(
                screen.getByRole('option', { name: 'İstanbul — UTC+03:00' }),
            ).toBeInTheDocument();
        });

        expect(screen.getByRole('option', { name: 'Turkish lira — TRY — ₺' })).toBeInTheDocument();
        expect(screen.getByRole('option', { name: 'Turkish' })).toBeInTheDocument();
    });

    /**
     * Bir `<select>`, değeri seçeneklerinde bulamazsa sessizce İLK seçeneğe
     * atlar. Kullanıcı hiçbir şey yapmadan kaydettiğinde markasının dili
     * değişmiş olur ve bunun ekranda hiçbir belirtisi olmaz.
     */
    it('mevcut değer sunucu listesinde yoksa onu KAYBETMEZ', async () => {
        stubFetch(() => jsonResponse(200, brand), {
            ...REFERENCE,
            // Sunucu artık Türkçeyi sunmuyor (ICU sürümü değişti diyelim).
            locales: [{ code: 'en', name: 'English' }],
        });

        await openEditor();

        await waitFor(() => {
            expect(screen.getByLabelText(/menu language/i)).toHaveValue('tr');
        });
    });

    /**
     * Yetki hatasında "tekrar deneyin" YANLIŞ tavsiyedir: tekrar denemek
     * hiçbir zaman işe yaramaz.
     */
    it('yetki hatasını sunucu hatasından ayırır', async () => {
        stubFetch(() => jsonResponse(403, {}));
        const user = await openEditor();

        await user.click(screen.getByRole('button', { name: /save/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/do not have permission/i);
        });

        expect(screen.queryByText(/try again in a few seconds/i)).toBeNull();
    });

    it('çakışmayı, yeniden yüklemesi gerektiğini söyleyerek bildirir', async () => {
        stubFetch(() => jsonResponse(409, {}));
        const user = await openEditor();

        await user.click(screen.getByRole('button', { name: /save/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/someone else changed this/i);
        });
    });

    /**
     * Destek kodu UYDURULMAZ. Destek ekibinin arayamayacağı bir kod, hiç kod
     * olmamasından kötüdür — kullanıcı onu iletir ve karşılığı çıkmaz.
     */
    it('sunucu izleme kimliği gönderdiyse gösterir, göndermediyse uydurmaz', async () => {
        stubFetch(() => jsonResponse(500, {}, { 'X-Request-Id': 'req-8Q4M' }));
        const user = await openEditor();

        await user.click(screen.getByRole('button', { name: /save/i }));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toHaveTextContent(/req-8Q4M/);
        });
    });

    /**
     * Herkese açık adreste geçen teknik değer gösterilebilir — ama NE OLDUĞU
     * söylenmeli. "Slug" bir kullanıcı sözcüğü değildir.
     */
    it('teknik tanımlayıcıyı geliştirici adıyla değil, işleviyle sunar', async () => {
        stubFetch(() => jsonResponse(200, brand));
        await openEditor();

        expect(screen.queryByLabelText('Slug')).toBeNull();

        const field = screen.getByLabelText(/menu web address/i);
        expect(field).toBeDisabled();
        expect(field).toHaveValue(brand.slug);
    });
});
