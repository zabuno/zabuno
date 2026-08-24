import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Blind RED test candidate for S1-WP02A CORE-01 (docs/33 §4, §6, §11).
 * None of RegisterForm, LoginForm, VerificationPending, VerifiedDestination,
 * LogoutButton or lib/csrfHeader exist yet in this snapshot — every dynamic
 * import below fails RED (module-not-found, not a syntax/type error in this
 * file) until the accessible, English-default, Flowbite-first,
 * translation-key-driven auth journey is built (docs/33 §3 scope table, §6
 * "WCAG keyboard/form semantics", §6 "English default + translation key
 * policy"). Covers S1WP02A-A11Y-01 and S1WP02A-I18N-01, plus the stateful
 * cookie-session contract (no Authorization bearer token, no localStorage
 * token) for S1WP02A-CSRF-01/SESSION-01.
 */
const authComponentsDir = resolve(__dirname, '../../components/auth');

/**
 * Vite's import-analysis plugin statically resolves an import() call whose
 * argument is a literal string — even under /* @vite-ignore *\/ — and aborts
 * the whole file's transform on a missing target instead of letting the
 * call fail at runtime inside the test. Building the specifier from a
 * non-literal expression keeps resolution genuinely dynamic, so a missing
 * component fails its own it() block (RED) rather than the whole suite.
 */
function importAuthModule<T extends Record<string, unknown> = Record<string, unknown>>(
  relativePath: string,
): Promise<T> {
  const base = '../../';

  return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
}

describe('RegisterForm — accessible English Flowbite-first UI (S1WP02A-REG-01, A11Y-01)', () => {
  it('renders every field with a Flowbite Label/for association and no aria-invalid by default', async () => {
    const { RegisterForm } = await importAuthModule<{ RegisterForm: React.ComponentType }>('components/auth/RegisterForm');
    render(<RegisterForm />);

    const email = screen.getByLabelText(/email/i);
    const password = screen.getByLabelText(/^password$/i);

    expect(email).toBeInTheDocument();
    expect(password).toBeInTheDocument();
    expect(email).not.toHaveAttribute('aria-invalid', 'true');
  });

  it('is completable keyboard-only and associates validation errors with their input via aria-describedby', async () => {
    const { RegisterForm } = await importAuthModule<{ RegisterForm: React.ComponentType }>('components/auth/RegisterForm');
    render(<RegisterForm />);

    const submit = screen.getByRole('button', { name: /register|sign up|create account/i });
    fireEvent.click(submit);

    const email = await screen.findByLabelText(/email/i);
    expect(email).toHaveAttribute('aria-invalid', 'true');
    const describedBy = email.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(String(describedBy))).toBeInTheDocument();
  });

  it('renders default copy in English sourced from a translation function, not a hardcoded literal', async () => {
    const source = await importAuthModule('components/auth/RegisterForm?raw');

    expect(String(source.default)).toMatch(/\bt\(['"]auth\./);
  });
});

describe('VerificationPending screen — accessible English Flowbite-first UI (S1WP02A-VERIFY-01, A11Y-01)', () => {
  it('renders a keyboard-reachable resend action using Flowbite components', async () => {
    const { VerificationPending } = await importAuthModule<{ VerificationPending: React.ComponentType<{ email: string }> }>('components/auth/VerificationPending');
    render(<VerificationPending email="ada@example.com" />);

    const resend = screen.getByRole('button', { name: /resend/i });
    expect(resend).toBeInTheDocument();
    resend.focus();
    expect(resend).toHaveFocus();
  });

  it('announces the resend outcome through an aria-live status region', async () => {
    const { VerificationPending } = await importAuthModule<{ VerificationPending: React.ComponentType<{ email: string }> }>('components/auth/VerificationPending');
    render(<VerificationPending email="ada@example.com" />);

    expect(screen.getByRole('status')).toBeInTheDocument();
  });
});

describe('LoginForm — accessible English Flowbite-first UI (S1WP02A-SESSION-01/02, A11Y-01)', () => {
  it('renders every field with a Flowbite Label/for association and no aria-invalid by default', async () => {
    const { LoginForm } = await importAuthModule<{ LoginForm: React.ComponentType }>('components/auth/LoginForm');
    render(<LoginForm />);

    const email = screen.getByLabelText(/email/i);
    const password = screen.getByLabelText(/^password$/i);

    expect(email).toBeInTheDocument();
    expect(password).toBeInTheDocument();
    expect(email).not.toHaveAttribute('aria-invalid', 'true');
  });

  it('is completable keyboard-only and associates validation errors with their input via aria-describedby', async () => {
    const { LoginForm } = await importAuthModule<{ LoginForm: React.ComponentType }>('components/auth/LoginForm');
    render(<LoginForm />);

    const submit = screen.getByRole('button', { name: /log ?in|sign in/i });
    fireEvent.click(submit);

    const email = await screen.findByLabelText(/email/i);
    expect(email).toHaveAttribute('aria-invalid', 'true');
    const describedBy = email.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(String(describedBy))).toBeInTheDocument();
  });

  it('renders default copy in English sourced from a translation function, not a hardcoded literal', async () => {
    const source = await importAuthModule('components/auth/LoginForm?raw');

    expect(String(source.default)).toMatch(/\bt\(['"]auth\./);
  });
});

describe('VerifiedDestination screen — post-verification landing (S1WP02A-VERIFY-01, A11Y-01)', () => {
  it('renders an accessible confirmation heading sourced from a translation function', async () => {
    const { VerifiedDestination } = await importAuthModule<{ VerifiedDestination: React.ComponentType }>('components/auth/VerifiedDestination');
    render(<VerifiedDestination />);

    expect(screen.getByRole('heading')).toBeInTheDocument();

    const source = await importAuthModule('components/auth/VerifiedDestination?raw');
    expect(String(source.default)).toMatch(/\bt\(['"]auth\./);
  });
});

describe('LogoutButton — keyboard-reachable session termination (S1WP02A-LOGOUT-01, A11Y-01)', () => {
  it('renders a keyboard-focusable button with an accessible logout label', async () => {
    const { LogoutButton } = await importAuthModule<{ LogoutButton: React.ComponentType }>('components/auth/LogoutButton');
    render(<LogoutButton />);

    const button = screen.getByRole('button', { name: /log ?out/i });
    button.focus();
    expect(button).toHaveFocus();
  });
});

describe('CSRF header helper — stateful cookie session, never a bearer token (S1WP02A-CSRF-01)', () => {
  it('attaches credentials: include and an X-XSRF-TOKEN header sourced from the cookie, never an Authorization header', async () => {
    document.cookie = 'XSRF-TOKEN=test-xsrf-token-value';

    const { buildAuthRequestInit } = await importAuthModule<{ buildAuthRequestInit: () => RequestInit }>('lib/csrfHeader');
    const init = buildAuthRequestInit();

    expect(init.credentials).toBe('include');
    const headers = new Headers(init.headers);
    expect(headers.get('X-XSRF-TOKEN')).toBe('test-xsrf-token-value');
    expect(headers.has('Authorization')).toBe(false);
  });

  it('never persists an auth token to localStorage', async () => {
    const { buildAuthRequestInit } = await importAuthModule<{ buildAuthRequestInit: () => RequestInit }>('lib/csrfHeader');
    buildAuthRequestInit();

    expect(window.localStorage.getItem('token')).toBeNull();
    expect(window.localStorage.getItem('access_token')).toBeNull();
  });
});

describe('RegisterForm — navigates to pending on success, alerts on failure (review-correction RED)', () => {
  it('calls the injected onSuccess/navigate callback after a 2xx register response', async () => {
    const { RegisterForm } = await importAuthModule<{
      RegisterForm: React.ComponentType<{ navigate?: (path: string) => void }>;
    }>('components/auth/RegisterForm');

    const navigate = vi.fn();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) });
    vi.stubGlobal('fetch', fetchMock);

    render(<RegisterForm navigate={navigate} />);

    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Ada Lovelace' } });
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
    fireEvent.click(screen.getByRole('button', { name: /register|sign up|create account/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/email/verify');
    });

    vi.unstubAllGlobals();
  });

  it('exposes an alert on a failed register response', async () => {
    const { RegisterForm } = await importAuthModule<{ RegisterForm: React.ComponentType }>('components/auth/RegisterForm');

    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ message: 'error' }) });
    vi.stubGlobal('fetch', fetchMock);

    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Ada Lovelace' } });
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
    fireEvent.click(screen.getByRole('button', { name: /register|sign up|create account/i }));

    expect(await screen.findByRole('alert').catch(() => null)).toBeTruthy();

    vi.unstubAllGlobals();
  });
});

describe('LoginForm — navigates on success, alerts on failure (review-correction RED)', () => {
  it('calls the injected navigate callback after a 2xx login response', async () => {
    const { LoginForm } = await importAuthModule<{
      LoginForm: React.ComponentType<{ navigate?: (path: string) => void }>;
    }>('components/auth/LoginForm');

    const navigate = vi.fn();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) });
    vi.stubGlobal('fetch', fetchMock);

    render(<LoginForm navigate={navigate} />);

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
    fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/app');
    });

    vi.unstubAllGlobals();
  });

  it('exposes an alert on a failed login response', async () => {
    const { LoginForm } = await importAuthModule<{ LoginForm: React.ComponentType }>('components/auth/LoginForm');

    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ message: 'error' }) });
    vi.stubGlobal('fetch', fetchMock);

    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'wrong' } });
    fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

    expect(await screen.findByRole('alert').catch(() => null)).toBeTruthy();

    vi.unstubAllGlobals();
  });
});

describe('VerifiedDestination — exposes a LogoutButton (review-correction RED)', () => {
  it('renders a LogoutButton within the verified destination screen', async () => {
    const { VerifiedDestination } = await importAuthModule<{ VerifiedDestination: React.ComponentType }>('components/auth/VerifiedDestination');
    render(<VerifiedDestination />);

    expect(screen.getByRole('button', { name: /log ?out/i })).toBeInTheDocument();
  });
});

describe('LogoutButton — navigates to login on success, alerts on failure (review-correction RED)', () => {
  it('calls the injected navigate callback after a 2xx logout response', async () => {
    const { LogoutButton } = await importAuthModule<{
      LogoutButton: React.ComponentType<{ navigate?: (path: string) => void }>;
    }>('components/auth/LogoutButton');

    const navigate = vi.fn();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({}) });
    vi.stubGlobal('fetch', fetchMock);

    render(<LogoutButton navigate={navigate} />);
    fireEvent.click(screen.getByRole('button', { name: /log ?out/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/login');
    });

    vi.unstubAllGlobals();
  });

  it('exposes an alert on a failed logout response', async () => {
    const { LogoutButton } = await importAuthModule<{ LogoutButton: React.ComponentType }>('components/auth/LogoutButton');

    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 500, json: async () => ({}) });
    vi.stubGlobal('fetch', fetchMock);

    render(<LogoutButton />);
    fireEvent.click(screen.getByRole('button', { name: /log ?out/i }));

    expect(await screen.findByRole('alert').catch(() => null)).toBeTruthy();

    vi.unstubAllGlobals();
  });
});

describe('RegisterForm — network failure surfaces the existing accessible alert (final-correction RED)', () => {
  it('renders the existing role=alert translation-driven error when fetch rejects with a network TypeError', async () => {
    const { RegisterForm } = await importAuthModule<{ RegisterForm: React.ComponentType }>('components/auth/RegisterForm');

    const fetchMock = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    vi.stubGlobal('fetch', fetchMock);

    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText(/name/i), { target: { value: 'Ada Lovelace' } });
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
    fireEvent.click(screen.getByRole('button', { name: /register|sign up|create account/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();

    vi.unstubAllGlobals();
  });
});

describe('LoginForm — network failure surfaces the existing accessible alert (final-correction RED)', () => {
  it('renders the existing role=alert translation-driven error when fetch rejects with a network TypeError', async () => {
    const { LoginForm } = await importAuthModule<{ LoginForm: React.ComponentType }>('components/auth/LoginForm');

    const fetchMock = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    vi.stubGlobal('fetch', fetchMock);

    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText(/^password$/i), { target: { value: 'correct-horse-battery-staple-1' } });
    fireEvent.click(screen.getByRole('button', { name: /log ?in|sign in/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();

    vi.unstubAllGlobals();
  });
});

describe('LogoutButton — network failure surfaces the existing accessible alert (final-correction RED)', () => {
  it('renders the existing role=alert translation-driven error when fetch rejects with a network TypeError', async () => {
    const { LogoutButton } = await importAuthModule<{ LogoutButton: React.ComponentType }>('components/auth/LogoutButton');

    const fetchMock = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    vi.stubGlobal('fetch', fetchMock);

    render(<LogoutButton />);
    fireEvent.click(screen.getByRole('button', { name: /log ?out/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();

    vi.unstubAllGlobals();
  });
});

describe('Auth UI — translation-key policy, no hardcoded literals (S1WP02A-I18N-01)', () => {
  it('keeps the auth component directory free of hardcoded UI literal strings outside translation calls', () => {
    expect(existsSync(authComponentsDir)).toBe(true);

    const files = readdirSync(authComponentsDir).filter((f) => /\.tsx$/.test(f));
    expect(files.length).toBeGreaterThan(0);

    for (const file of files) {
      const contents = readFileSync(resolve(authComponentsDir, file), 'utf-8');
      const jsxTextLiterals = contents.match(/>[A-Z][a-zA-Z ,.'!-]{3,}</g) ?? [];

      expect(jsxTextLiterals).toEqual([]);
    }
  });
});
