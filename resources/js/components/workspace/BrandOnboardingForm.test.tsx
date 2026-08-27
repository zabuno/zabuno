import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

/**
 * RED test candidate for S1-WP02B/docs 34. No BrandOnboardingForm module
 * exists in this snapshot, so every dynamic import below fails RED
 * (module-not-found) until the component is built. Contract frozen by the
 * ZABUNO_PROFILE_FORMS_RED_FROZEN delivery contract: props workspaceId +
 * onCreated(BrandProfile); required StoreBrandRequest fields name/timezone/
 * currency, optional locale only when entered; POST
 * /api/workspaces/{workspaceId}/brand after CSRF bootstrap; disabled/busy
 * state; 403/422 inline role=alert; 201 invokes callback with server
 * response; no demo IDs/default business data.
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function importWorkspaceModule<T extends Record<string, unknown> = Record<string, unknown>>(
    relativePath: string,
): Promise<T> {
    const base = './';

    return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
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
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeBrandProfile(overrides: Partial<BrandProfile> = {}): BrandProfile {
    return {
        id: 3,
        workspace_id: 5,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        locale: 'tr-TR',
        timezone: 'Europe/Istanbul',
        currency: 'TRY',
        description: null,
        contact_email: null,
        contact_phone: null,
        ...overrides,
    };
}

function fillRequiredFields() {
    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Zeytin Restoranları' } });
    fireEvent.change(screen.getByLabelText(/timezone/i), { target: { value: 'Europe/Istanbul' } });
    fireEvent.change(screen.getByLabelText(/currency/i), { target: { value: 'TRY' } });
}

describe('BrandOnboardingForm — CSRF bootstrap and POST /api/workspaces/{workspaceId}/brand', () => {
    it('bootstraps the CSRF cookie before POST, sends exactly the required fields, and invokes onCreated with the server response on 201', async () => {
        const onCreated = vi.fn();
        const calls: Array<{ url: string; init?: RequestInit }> = [];
        const created = makeBrandProfile();
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), init });

            if (String(url) === CSRF_COOKIE_URL) {
                return jsonResponse(204, {});
            }
            if (String(url) === '/api/workspaces/5/brand') {
                return jsonResponse(201, created);
            }

            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        await waitFor(() => {
            expect(onCreated).toHaveBeenCalledWith(created);
        });

        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        const postCall = calls.find((call) => call.url === '/api/workspaces/5/brand');
        expect(postCall).toBeDefined();
        expect((postCall?.init?.method ?? '').toUpperCase()).toBe('POST');
        expect(postCall?.init?.credentials).toBe('include');

        const headers = new Headers(postCall?.init?.headers);
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('Content-Type')).toBe('application/json');

        const body = JSON.parse(String(postCall?.init?.body));
        expect(body).toEqual({
            name: 'Zeytin Restoranları',
            timezone: 'Europe/Istanbul',
            currency: 'TRY',
        });

        vi.unstubAllGlobals();
    });

    it('includes locale in the payload only when the user enters it', async () => {
        const onCreated = vi.fn();
        const calls: Array<{ url: string; init?: RequestInit }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), init });
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand')
                return jsonResponse(201, makeBrandProfile({ locale: 'tr' }));
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.change(screen.getByLabelText(/locale/i), { target: { value: 'tr-TR' } });
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        await waitFor(() => {
            expect(onCreated).toHaveBeenCalled();
        });

        const postCall = calls.find((call) => call.url === '/api/workspaces/5/brand');
        const body = JSON.parse(String(postCall?.init?.body));
        expect(body).toEqual({
            name: 'Zeytin Restoranları',
            timezone: 'Europe/Istanbul',
            currency: 'TRY',
            locale: 'tr',
        });

        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — client-side required validation', () => {
    it('shows an accessible validation error and does not call the create endpoint when required fields are empty', async () => {
        const onCreated = vi.fn();
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();

        const createCalls = fetchMock.mock.calls.filter(
            (call) => String(call[0]) === '/api/workspaces/5/brand',
        );
        expect(createCalls).toHaveLength(0);
        expect(onCreated).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — disabled/busy state while the request is in flight', () => {
    it('disables the submit action while awaiting the POST response', async () => {
        const onCreated = vi.fn();
        let resolveCreate: (value: Response) => void = () => {};
        const pendingCreate = new Promise<Response>((resolve) => {
            resolveCreate = resolve;
        });
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand') return pendingCreate;
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        const submit = screen.getByRole('button', { name: /create|save|continue/i });
        fireEvent.click(submit);

        await waitFor(() => {
            expect(submit).toBeDisabled();
        });

        resolveCreate(jsonResponse(201, makeBrandProfile()));
        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — server error rendering', () => {
    it('renders an inline role=alert on 422 validation error without invoking onCreated', async () => {
        const onCreated = vi.fn();
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand') {
                return jsonResponse(422, {
                    message: 'The given data was invalid.',
                    errors: { name: ['The name field is required.'] },
                });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(onCreated).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });

    it('renders an inline role=alert on 403 forbidden without invoking onCreated', async () => {
        const onCreated = vi.fn();
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand') {
                return jsonResponse(403, { message: 'This action is unauthorized.' });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(onCreated).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — no demo IDs or default business data', () => {
    it('does not prefill the name field with placeholder demo business data', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={vi.fn()} />);

        const nameInput = screen.getByLabelText(/name/i) as HTMLInputElement;
        expect(nameInput.value).toBe('');

        vi.unstubAllGlobals();
    });
});

describe('BrandOnboardingForm — the server said what was wrong, so the form must say it too', () => {
    /**
     * Owner bu formu "istantul" yazarak denedi. Sunucu 422 ile
     * "The timezone must be a valid IANA timezone identifier." dedi. Ekranda
     * yalnız "We could not create your brand. Please try again." göründü.
     *
     * Bu bir döngüdür: kullanıcı neyi düzelteceğini bilmez, aynı veriyi
     * tekrar gönderir, aynı cevabı alır. Marka kurulamadığı için konum
     * eklenemez; konum olmadığı için menü açılmaz. Yeni hesap tam burada
     * durur.
     */
    async function renderForm(response: Response) {
        const fetchMock = vi.fn(async (url: string) => {
            if (url === CSRF_COOKIE_URL) return { ok: true, status: 204 } as Response;
            if (url === '/api/workspaces/5/brand') return response;
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { BrandOnboardingForm } = await importWorkspaceModule<{
            BrandOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (brand: BrandProfile) => void;
            }>;
        }>('BrandOnboardingForm');
        render(<BrandOnboardingForm workspaceId={5} onCreated={vi.fn()} />);

        fillRequiredFields();
        fireEvent.change(screen.getByLabelText(/timezone/i), { target: { value: 'istantul' } });
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));
    }

    it('shows the field-level reason the server gave instead of a generic retry message', async () => {
        await renderForm(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: {
                    timezone: ['The timezone must be a valid IANA timezone identifier.'],
                },
            }),
        );

        await waitFor(() => {
            expect(screen.getByText(/valid IANA timezone identifier/i)).toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    it('marks the offending field so the user can see which one to fix', async () => {
        await renderForm(
            jsonResponse(422, {
                message: 'The given data was invalid.',
                errors: {
                    timezone: ['The timezone must be a valid IANA timezone identifier.'],
                },
            }),
        );

        await waitFor(() => {
            expect(screen.getByLabelText(/timezone/i)).toHaveAttribute('aria-invalid', 'true');
        });

        // Hatasız alan suçlanmaz.
        expect(screen.getByLabelText(/currency/i)).not.toHaveAttribute('aria-invalid');

        vi.unstubAllGlobals();
    });

    it('still says something when the failure carries no field detail', async () => {
        await renderForm(jsonResponse(500, {}));

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });
});
