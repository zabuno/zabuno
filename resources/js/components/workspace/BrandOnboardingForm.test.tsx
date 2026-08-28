import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

/**
 * BrandOnboardingForm kabul testi.
 *
 * Sözleşme 2026-08-27'de değişti. Eski form dört serbest metin alanı
 * soruyordu — ad, `timezone`, `currency`, `locale` — yani kullanıcıdan
 * `Europe/Istanbul` ve `TRY` yazmasını bekliyordu. Bunlar sütun değerleri;
 * restoran sahibinin dili değil. Sahibi "istantul" yazdı, sunucu reddetti,
 * ekran "Please try again" dedi ve marka kurulamadığı için konum, konum
 * olmadığı için menü açılamadı.
 *
 * Yeni sözleşme: kullanıcının BİLDİĞİ sorulur (ad, ülke), çıkarılabilen
 * ÇIKARILIR (saat dilimi, para birimi), ertelenebilen ERTELENİR (menü
 * içerik dili — menü eklenene kadar anlamı yok).
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const BRAND_URL = '/api/workspaces/5/brand';

function importWorkspaceModule<T extends Record<string, unknown> = Record<string, unknown>>(
    relativePath: string,
): Promise<T> {
    return import(/* @vite-ignore */ './' + relativePath) as Promise<T>;
}

type BrandProfile = {
    id: number;
    workspace_id: number;
    name: string;
    slug: string;
    locale: string;
    timezone: string;
    currency: string;
    description: string | null;
    contact_email: string | null;
    contact_phone: string | null;
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

/** Sunucunun döndürdüğü referans veri. */
const REFERENCE = {
    markets: [
        { code: 'TR', name: 'Türkiye' },
        { code: 'DE', name: 'Germany' },
    ],
    currencies: [
        { code: 'TRY', name: 'Turkish Lira', symbol: '₺' },
        { code: 'EUR', name: 'Euro', symbol: '€' },
    ],
    timezones: [{ id: 'Europe/Istanbul', label: 'Istanbul — UTC+03:00' }],
    defaults: { timezone: 'Europe/Istanbul', currency: 'TRY' },
    suggestedCountry: 'TR',
};

function makeBrandProfile(overrides: Partial<BrandProfile> = {}): BrandProfile {
    return {
        id: 3,
        workspace_id: 5,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        locale: 'en',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contact_email: null,
        contact_phone: null,
        ...overrides,
    };
}

type Call = { url: string; init?: RequestInit };

/**
 * Referans uç noktası her zaman cevaplanır; marka uç noktasının cevabı
 * teste göre değişir.
 */
function stubFetch(brandResponse: Response | (() => Promise<Response>)): Call[] {
    const calls: Call[] = [];

    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
        calls.push({ url, init });

        if (url.startsWith('/api/reference/markets')) return jsonResponse(200, REFERENCE);
        if (url === CSRF_COOKIE_URL) return { ok: true, status: 204 } as Response;
        if (url === BRAND_URL) {
            return typeof brandResponse === 'function' ? brandResponse() : brandResponse;
        }

        throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    return calls;
}

async function renderForm(onCreated = vi.fn()) {
    const { BrandOnboardingForm } = await importWorkspaceModule<{
        BrandOnboardingForm: React.ComponentType<{
            workspaceId: number;
            onCreated: (brand: BrandProfile) => void;
        }>;
    }>('BrandOnboardingForm');

    render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

    // Referans verisi geldiğinde ülke önerisi yerleşir.
    await waitFor(() => {
        expect(screen.getByLabelText(/main market/i)).toHaveValue('TR');
    });

    return { onCreated };
}

function submit() {
    fireEvent.click(screen.getByRole('button', { name: /create brand/i }));
}

describe('BrandOnboardingForm — asks what the user knows', () => {
    it('suggests the country from the browser time zone instead of asking for an IANA identifier', async () => {
        stubFetch(jsonResponse(201, makeBrandProfile()));
        await renderForm();

        // Öneri seçim değildir: kullanıcı listeden değiştirebilir.
        expect(screen.getByLabelText(/main market/i)).toHaveValue('TR');
        expect(screen.getByLabelText(/time zone/i)).toHaveValue('Europe/Istanbul');
        expect(screen.getByLabelText(/currency/i)).toHaveValue('TRY');

        vi.unstubAllGlobals();
    });

    it('never asks the user to type a timezone, currency or locale', async () => {
        stubFetch(jsonResponse(201, makeBrandProfile()));
        await renderForm();

        // Bunlar seçim kontrolü olmalı; serbest metin, kullanıcıyı
        // `Europe/Istanbul` yazmaya zorlar ve "istantul" ile çıkmaza sokar.
        expect(screen.getByLabelText(/time zone/i).tagName).toBe('SELECT');
        expect(screen.getByLabelText(/currency/i).tagName).toBe('SELECT');
        expect(screen.getByLabelText(/main market/i).tagName).toBe('SELECT');

        // Menü içerik dili ertelendi: markanın kurulması için gerekmiyor.
        expect(screen.queryByLabelText(/locale/i)).not.toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('bootstraps CSRF, posts the derived values, and hands the created brand back', async () => {
        const calls = stubFetch(jsonResponse(201, makeBrandProfile()));
        const { onCreated } = await renderForm();

        fireEvent.change(screen.getByLabelText(/brand name/i), {
            target: { value: 'Zeytin Restoranları' },
        });
        submit();

        await waitFor(() => {
            expect(onCreated).toHaveBeenCalled();
        });

        const csrfIndex = calls.findIndex((call) => call.url === CSRF_COOKIE_URL);
        const postIndex = calls.findIndex((call) => call.url === BRAND_URL);
        expect(csrfIndex).toBeGreaterThan(-1);
        expect(postIndex).toBeGreaterThan(csrfIndex);

        const body = JSON.parse(String(calls[postIndex].init?.body));
        expect(body).toEqual({
            name: 'Zeytin Restoranları',
            timezone: 'Europe/Istanbul',
            currency: 'TRY',
        });

        // Ertelenen alan gönderilmez; sunucu kendi varsayılanını uygular.
        expect(body).not.toHaveProperty('locale');

        vi.unstubAllGlobals();
    });

    it('reloads the derived values when the market changes', async () => {
        const calls = stubFetch(jsonResponse(201, makeBrandProfile()));
        await renderForm();

        fireEvent.change(screen.getByLabelText(/main market/i), { target: { value: 'DE' } });

        await waitFor(() => {
            expect(
                calls.some((call) => call.url.includes('/api/reference/markets?country=DE')),
            ).toBe(true);
        });

        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — failures say what happened', () => {
    it('shows the field-level reason the server gave instead of a generic retry message', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { name: ['A brand with this name already exists.'] },
            }),
        );
        await renderForm();

        fireEvent.change(screen.getByLabelText(/brand name/i), { target: { value: 'Olgax' } });
        submit();

        await waitFor(() => {
            expect(screen.getByText(/already exists/i)).toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    it('moves focus to the offending field so the user does not hunt for it', async () => {
        stubFetch(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: { name: ['A brand with this name already exists.'] },
            }),
        );
        await renderForm();

        fireEvent.change(screen.getByLabelText(/brand name/i), { target: { value: 'Olgax' } });
        submit();

        await waitFor(() => {
            expect(screen.getByLabelText(/brand name/i)).toHaveFocus();
        });

        vi.unstubAllGlobals();
    });

    it('does not blame the server when the user simply left the name empty', async () => {
        const calls = stubFetch(jsonResponse(201, makeBrandProfile()));
        await renderForm();

        submit();

        await waitFor(() => {
            expect(screen.getByText(/enter a name for your brand/i)).toBeInTheDocument();
        });

        // Boş ad için sunucuya gidilmez.
        expect(calls.some((call) => call.url === BRAND_URL)).toBe(false);

        vi.unstubAllGlobals();
    });

    it('says the connection failed rather than implying the data was rejected', async () => {
        stubFetch(() => Promise.reject(new Error('offline')));
        await renderForm();

        fireEvent.change(screen.getByLabelText(/brand name/i), { target: { value: 'Zeytin' } });
        submit();

        /*
            Metin ORTAK sözlükten geliyor artık (`docs/67`): dört form aynı
            arıza için aynı cümleyi söylüyor. Eskiden her form kendi cümlesini
            taşıyordu ve biri düzeldiğinde diğerleri eski hâlinde kalıyordu.

            Cümlenin kendisi de iyileşti: ürünü adıyla anıyor ve asıl korkuyu
            cevaplıyor — girilen veri duruyor.
        */
        await waitFor(() => {
            expect(screen.getByText(/could not reach Zabuno/i)).toBeInTheDocument();
        });
        expect(screen.getByText(/everything you typed is still here/i)).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('disables the submit action while the request is in flight', async () => {
        let release: (value: Response) => void = () => {};
        stubFetch(() => new Promise<Response>((resolve) => (release = resolve)));
        await renderForm();

        fireEvent.change(screen.getByLabelText(/brand name/i), { target: { value: 'Zeytin' } });
        submit();

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /creating brand/i })).toBeDisabled();
        });

        release(jsonResponse(201, makeBrandProfile()));
        vi.unstubAllGlobals();
    });
});
