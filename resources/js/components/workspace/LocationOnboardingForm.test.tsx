import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';

/**
 * RED test candidate for S1-WP02B/docs 34. No LocationOnboardingForm module
 * exists in this snapshot, so every dynamic import below fails RED
 * (module-not-found) until the component is built. Contract frozen by the
 * ZABUNO_PROFILE_FORMS_RED_FROZEN delivery contract: props workspaceId +
 * onCreated(LocationProfile); required fields display_name/country_code/
 * city/address_line1, optional address_line2/postal_code only when entered;
 * POST /api/workspaces/{workspaceId}/brand/locations after CSRF bootstrap;
 * disabled/busy state; 403/422 inline role=alert; 201 invokes callback with
 * server response; no demo IDs/default business data.
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function importWorkspaceModule<T extends Record<string, unknown> = Record<string, unknown>>(
    relativePath: string,
): Promise<T> {
    const base = './';

    return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
}

type LocationProfile = {
    id: number;
    workspace_id: number;
    brand_id: number;
    display_name: string;
    country_code: string;
    city: string;
    address_line1: string;
    address_line2: string | null;
    postal_code: string | null;
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeLocationProfile(overrides: Partial<LocationProfile> = {}): LocationProfile {
    return {
        id: 9,
        workspace_id: 5,
        brand_id: 3,
        display_name: 'Kadıköy Şube',
        country_code: 'TR',
        city: 'İstanbul',
        address_line1: 'Moda Cd. No:12',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

function fillRequiredFields() {
    fireEvent.change(screen.getByLabelText(/display name/i), { target: { value: 'Kadıköy Şube' } });
    fireEvent.change(screen.getByLabelText(/country/i), { target: { value: 'TR' } });
    fireEvent.change(screen.getByLabelText(/city/i), { target: { value: 'İstanbul' } });
    fireEvent.change(screen.getByLabelText(/address line ?1|^address$/i), {
        target: { value: 'Moda Cd. No:12' },
    });
}

describe('LocationOnboardingForm — CSRF bootstrap and POST /api/workspaces/{workspaceId}/brand/locations', () => {
    it('bootstraps the CSRF cookie before POST, sends exactly the required fields, and invokes onCreated with the server response on 201', async () => {
        const onCreated = vi.fn();
        const calls: Array<{ url: string; init?: RequestInit }> = [];
        const created = makeLocationProfile();
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), init });

            if (String(url) === CSRF_COOKIE_URL) {
                return jsonResponse(204, {});
            }
            if (String(url) === '/api/workspaces/5/brand/locations') {
                return jsonResponse(201, created);
            }

            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        await waitFor(() => {
            expect(onCreated).toHaveBeenCalledWith(created);
        });

        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        const postCall = calls.find((call) => call.url === '/api/workspaces/5/brand/locations');
        expect(postCall).toBeDefined();
        expect((postCall?.init?.method ?? '').toUpperCase()).toBe('POST');
        expect(postCall?.init?.credentials).toBe('include');

        const headers = new Headers(postCall?.init?.headers);
        expect(headers.get('Accept')).toBe('application/json');
        expect(headers.get('Content-Type')).toBe('application/json');

        const body = JSON.parse(String(postCall?.init?.body));
        expect(body).toEqual({
            display_name: 'Kadıköy Şube',
            country_code: 'TR',
            city: 'İstanbul',
            address_line1: 'Moda Cd. No:12',
        });

        vi.unstubAllGlobals();
    });

    it('includes address_line2 and postal_code in the payload only when the user enters them', async () => {
        const onCreated = vi.fn();
        const calls: Array<{ url: string; init?: RequestInit }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), init });
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand/locations')
                return jsonResponse(201, makeLocationProfile());
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.change(screen.getByLabelText(/address line ?2/i), { target: { value: 'Kat 2' } });
        fireEvent.change(screen.getByLabelText(/postal code/i), { target: { value: '34710' } });
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        await waitFor(() => {
            expect(onCreated).toHaveBeenCalled();
        });

        const postCall = calls.find((call) => call.url === '/api/workspaces/5/brand/locations');
        const body = JSON.parse(String(postCall?.init?.body));
        expect(body).toEqual({
            display_name: 'Kadıköy Şube',
            country_code: 'TR',
            city: 'İstanbul',
            address_line1: 'Moda Cd. No:12',
            address_line2: 'Kat 2',
            postal_code: '34710',
        });

        vi.unstubAllGlobals();
    });
});

describe('LocationOnboardingForm — client-side required validation', () => {
    it('shows an accessible validation error and does not call the create endpoint when required fields are empty', async () => {
        const onCreated = vi.fn();
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();

        const createCalls = fetchMock.mock.calls.filter(
            (call) => String(call[0]) === '/api/workspaces/5/brand/locations',
        );
        expect(createCalls).toHaveLength(0);
        expect(onCreated).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});

describe('LocationOnboardingForm — disabled/busy state while the request is in flight', () => {
    it('disables the submit action while awaiting the POST response', async () => {
        const onCreated = vi.fn();
        let resolveCreate: (value: Response) => void = () => {};
        const pendingCreate = new Promise<Response>((resolve) => {
            resolveCreate = resolve;
        });
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand/locations') return pendingCreate;
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        const submit = screen.getByRole('button', { name: /create|save|continue/i });
        fireEvent.click(submit);

        await waitFor(() => {
            expect(submit).toBeDisabled();
        });

        resolveCreate(jsonResponse(201, makeLocationProfile()));
        vi.unstubAllGlobals();
    });
});

describe('LocationOnboardingForm — server error rendering', () => {
    it('renders an inline role=alert on 422 validation error without invoking onCreated', async () => {
        const onCreated = vi.fn();
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/workspaces/5/brand/locations') {
                return jsonResponse(422, {
                    message: 'The given data was invalid.',
                    errors: { display_name: ['The display name field is required.'] },
                });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

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
            if (String(url) === '/api/workspaces/5/brand/locations') {
                return jsonResponse(403, { message: 'This action is unauthorized.' });
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={onCreated} />);

        fillRequiredFields();
        fireEvent.click(screen.getByRole('button', { name: /create|save|continue/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(onCreated).not.toHaveBeenCalled();

        vi.unstubAllGlobals();
    });
});

describe('LocationOnboardingForm — no demo IDs or default business data', () => {
    it('does not prefill the display name field with placeholder demo business data', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { LocationOnboardingForm } = await importWorkspaceModule<{
            LocationOnboardingForm: React.ComponentType<{
                workspaceId: number;
                onCreated: (location: LocationProfile) => void;
            }>;
        }>('LocationOnboardingForm');
        render(<LocationOnboardingForm workspaceId={5} onCreated={vi.fn()} />);

        const nameInput = screen.getByLabelText(/display name/i) as HTMLInputElement;
        expect(nameInput.value).toBe('');

        vi.unstubAllGlobals();
    });
});
