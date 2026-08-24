import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { RegisterForm } from '../../components/auth/RegisterForm';
import { LoginForm } from '../../components/auth/LoginForm';
import { VerificationPending } from '../../components/auth/VerificationPending';
import { LogoutButton } from '../../components/auth/LogoutButton';

/**
 * Blind RED test candidate for S1-WP02A-R1 (CSRF bootstrap correction).
 * Current implementation posts directly to /register, /login,
 * /email/verification-notification and /logout without first fetching
 * GET /sanctum/csrf-cookie, producing the observed 419. These specs assert
 * the corrected call order (bootstrap before mutation), bootstrap-failure
 * suppression via the existing accessible alert, and unchanged success
 * endpoints/payloads/navigation. They must fail RED against the current
 * (uncorrected) components.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function fillRegisterForm() {
    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Ada Lovelace' } });
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), {
        target: { value: 'correct-horse-battery-staple-1' },
    });
}

function fillLoginForm() {
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
}

describe('RegisterForm — CSRF cookie bootstrap before POST /register (S1WP02A-R1-CSRF-01)', () => {
    it('fetches the CSRF cookie before posting to /register, and navigates unchanged on success', async () => {
        const navigate = vi.fn();
        const calls: Array<{ url: string; credentials?: RequestCredentials }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), credentials: init?.credentials });

            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }

            return { ok: true, status: 200, json: async () => ({}) } as Response;
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<RegisterForm navigate={navigate} />);
        fillRegisterForm();
        fireEvent.click(screen.getByRole('button', { name: /register|sign up|create account/i }));

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('/email/verify');
        });

        expect(calls.length).toBeGreaterThanOrEqual(2);
        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        expect(calls[0].credentials).toBe('include');
        expect(calls[1].url).toBe('/register');

        vi.unstubAllGlobals();
    });

    it('suppresses the /register mutation and shows the existing accessible alert when the CSRF bootstrap fails', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }

            throw new Error('mutation must not be reached when bootstrap fails');
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<RegisterForm />);
        fillRegisterForm();
        fireEvent.click(screen.getByRole('button', { name: /register|sign up|create account/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        vi.unstubAllGlobals();
    });
});

describe('LoginForm — CSRF cookie bootstrap before POST /login (S1WP02A-R1-CSRF-01)', () => {
    it('fetches the CSRF cookie before posting to /login, and navigates unchanged on success', async () => {
        const navigate = vi.fn();
        const calls: Array<{ url: string; credentials?: RequestCredentials }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), credentials: init?.credentials });

            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }

            return { ok: true, status: 200, json: async () => ({}) } as Response;
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<LoginForm navigate={navigate} />);
        fillLoginForm();
        fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('/app');
        });

        expect(calls.length).toBeGreaterThanOrEqual(2);
        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        expect(calls[0].credentials).toBe('include');
        expect(calls[1].url).toBe('/login');

        vi.unstubAllGlobals();
    });

    it('suppresses the /login mutation and shows the existing accessible alert when the CSRF bootstrap fails', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }

            throw new Error('mutation must not be reached when bootstrap fails');
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<LoginForm />);
        fillLoginForm();
        fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        vi.unstubAllGlobals();
    });
});

describe('VerificationPending — CSRF cookie bootstrap before POST /email/verification-notification (S1WP02A-R1-CSRF-01)', () => {
    it('fetches the CSRF cookie before posting the resend request, unchanged endpoint on success', async () => {
        const calls: Array<{ url: string; credentials?: RequestCredentials }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), credentials: init?.credentials });

            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }

            return { ok: true, status: 200, json: async () => ({}) } as Response;
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<VerificationPending email="ada@example.com" />);
        fireEvent.click(screen.getByRole('button', { name: /resend/i }));

        await waitFor(() => {
            expect(calls.length).toBeGreaterThanOrEqual(2);
        });

        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        expect(calls[0].credentials).toBe('include');
        expect(calls[1].url).toBe('/email/verification-notification');

        vi.unstubAllGlobals();
    });

    it('suppresses the resend mutation when the CSRF bootstrap fails, without calling the resend endpoint', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }

            throw new Error('mutation must not be reached when bootstrap fails');
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<VerificationPending email="ada@example.com" />);
        fireEvent.click(screen.getByRole('button', { name: /resend/i }));

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalledTimes(1);
        });
        expect(screen.getByRole('status')).toHaveTextContent(/./);

        vi.unstubAllGlobals();
    });
});

describe('LogoutButton — CSRF cookie bootstrap before POST /logout (S1WP02A-R1-CSRF-01)', () => {
    it('fetches the CSRF cookie before posting to /logout, and navigates unchanged on success', async () => {
        const navigate = vi.fn();
        const calls: Array<{ url: string; credentials?: RequestCredentials }> = [];
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            calls.push({ url: String(url), credentials: init?.credentials });

            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: true, status: 204, json: async () => ({}) } as Response;
            }

            return { ok: true, status: 200, json: async () => ({}) } as Response;
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<LogoutButton navigate={navigate} />);
        fireEvent.click(screen.getByRole('button', { name: /log ?out/i }));

        await waitFor(() => {
            expect(navigate).toHaveBeenCalledWith('/login');
        });

        expect(calls.length).toBeGreaterThanOrEqual(2);
        expect(calls[0].url).toBe(CSRF_COOKIE_URL);
        expect(calls[0].credentials).toBe('include');
        expect(calls[1].url).toBe('/logout');

        vi.unstubAllGlobals();
    });

    it('suppresses the /logout mutation and shows the existing accessible alert when the CSRF bootstrap fails', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }

            throw new Error('mutation must not be reached when bootstrap fails');
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<LogoutButton />);
        fireEvent.click(screen.getByRole('button', { name: /log ?out/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();
        expect(fetchMock).toHaveBeenCalledTimes(1);

        vi.unstubAllGlobals();
    });
});

describe('CSRF bootstrap failure — no bearer/localStorage fallback introduced (S1WP02A-R1-CSRF-01)', () => {
    it('never writes an auth token to localStorage when the CSRF bootstrap fails', async () => {
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === CSRF_COOKIE_URL) {
                return { ok: false, status: 500, json: async () => ({}) } as Response;
            }

            throw new Error('mutation must not be reached when bootstrap fails');
        });
        vi.stubGlobal('fetch', fetchMock);

        render(<LoginForm />);
        fillLoginForm();
        fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

        await screen.findByRole('alert');

        expect(window.localStorage.getItem('token')).toBeNull();
        expect(window.localStorage.getItem('access_token')).toBeNull();

        vi.unstubAllGlobals();
    });
});
