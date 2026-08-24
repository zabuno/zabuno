import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * Blind RED test candidate for S1-WP02D (password-reset follow-on to
 * WP02A's CORE-01 identity/session journey). Neither ForgotPasswordForm
 * nor ResetPasswordForm exist yet in this snapshot's
 * resources/js/components/auth directory, and LoginForm.tsx carries no
 * discoverable forgot-password link, so every dynamic import/assertion
 * below fails RED (module-not-found or missing link) until S1-WP02D is
 * implemented. Follows the existing CsrfBootstrap.test.tsx bootstrap
 * pattern (PRD-07) and AuthJourney.test.tsx dynamic-import-for-missing-module
 * pattern so a missing production module fails its own it() block instead
 * of aborting the whole suite transform.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function importAuthModule<T extends Record<string, unknown> = Record<string, unknown>>(
  relativePath: string,
): Promise<T> {
  const base = '../../';

  return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
}

function fillResetForm() {
  fireEvent.change(screen.getByLabelText(/^password$/i), {
    target: { value: 'new-correct-horse-battery-2' },
  });
  fireEvent.change(screen.getByLabelText(/confirm/i), {
    target: { value: 'new-correct-horse-battery-2' },
  });
}

describe('ForgotPasswordForm — accessible English Flowbite-first UI (PRD-06)', () => {
  it('renders an email field with a Flowbite Label/for association and no aria-invalid by default', async () => {
    const { ForgotPasswordForm } = await importAuthModule<{ ForgotPasswordForm: React.ComponentType }>(
      'components/auth/ForgotPasswordForm',
    );
    render(<ForgotPasswordForm />);

    const email = screen.getByLabelText(/email/i);
    expect(email).toBeInTheDocument();
    expect(email).not.toHaveAttribute('aria-invalid', 'true');
  });

  it('is completable keyboard-only and associates validation errors with their input via aria-describedby', async () => {
    const { ForgotPasswordForm } = await importAuthModule<{ ForgotPasswordForm: React.ComponentType }>(
      'components/auth/ForgotPasswordForm',
    );
    render(<ForgotPasswordForm />);

    const submit = screen.getByRole('button', { name: /send|reset|submit/i });
    fireEvent.click(submit);

    const email = await screen.findByLabelText(/email/i);
    expect(email).toHaveAttribute('aria-invalid', 'true');
    const describedBy = email.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(String(describedBy))).toBeInTheDocument();
  });

  it('renders default copy in English sourced from a translation function, not a hardcoded literal', async () => {
    const source = await importAuthModule('components/auth/ForgotPasswordForm?raw');

    expect(String(source.default)).toMatch(/\bt\(['"]auth\./);
  });

  it('bootstraps the CSRF cookie before posting to /forgot-password, and shows a status message on success', async () => {
    const calls: Array<{ url: string; credentials?: RequestCredentials }> = [];
    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
      calls.push({ url: String(url), credentials: init?.credentials });

      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);

    const { ForgotPasswordForm } = await importAuthModule<{ ForgotPasswordForm: React.ComponentType }>(
      'components/auth/ForgotPasswordForm',
    );
    render(<ForgotPasswordForm />);

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.click(screen.getByRole('button', { name: /send|reset|submit/i }));

    await waitFor(() => {
      expect(calls.length).toBeGreaterThanOrEqual(2);
    });
    expect(calls[0].url).toBe(CSRF_COOKIE_URL);
    expect(calls[0].credentials).toBe('include');
    expect(calls[1].url).toBe('/forgot-password');
    expect(screen.getByRole('status')).toBeInTheDocument();

    vi.unstubAllGlobals();
  });

  it('suppresses the mutation and shows the existing accessible alert when the CSRF bootstrap fails', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: false, status: 500, json: async () => ({}) } as Response;
      }

      throw new Error('mutation must not be reached when bootstrap fails');
    });
    vi.stubGlobal('fetch', fetchMock);

    const { ForgotPasswordForm } = await importAuthModule<{ ForgotPasswordForm: React.ComponentType }>(
      'components/auth/ForgotPasswordForm',
    );
    render(<ForgotPasswordForm />);

    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'ada@example.com' } });
    fireEvent.click(screen.getByRole('button', { name: /send|reset|submit/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(1);

    vi.unstubAllGlobals();
  });

  it('never persists an auth token to localStorage', async () => {
    const { ForgotPasswordForm } = await importAuthModule<{ ForgotPasswordForm: React.ComponentType }>(
      'components/auth/ForgotPasswordForm',
    );
    render(<ForgotPasswordForm />);

    expect(window.localStorage.getItem('token')).toBeNull();
    expect(window.localStorage.getItem('access_token')).toBeNull();
  });
});

describe('ResetPasswordForm — accessible English Flowbite-first UI (PRD-06)', () => {
  it('renders password and confirmation fields with Flowbite Label/for association and no aria-invalid by default', async () => {
    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" />);

    const password = screen.getByLabelText(/^password$/i);
    const confirm = screen.getByLabelText(/confirm/i);

    expect(password).toBeInTheDocument();
    expect(confirm).toBeInTheDocument();
    expect(password).not.toHaveAttribute('aria-invalid', 'true');
  });

  it('is completable keyboard-only and associates validation errors with their input via aria-describedby', async () => {
    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" />);

    const submit = screen.getByRole('button', { name: /reset|submit|change/i });
    fireEvent.click(submit);

    const password = await screen.findByLabelText(/^password$/i);
    expect(password).toHaveAttribute('aria-invalid', 'true');
    const describedBy = password.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(String(describedBy))).toBeInTheDocument();
  });

  it('renders default copy in English sourced from a translation function, not a hardcoded literal', async () => {
    const source = await importAuthModule('components/auth/ResetPasswordForm?raw');

    expect(String(source.default)).toMatch(/\bt\(['"]auth\./);
  });

  it('bootstraps the CSRF cookie before posting to /reset-password, and navigates to login on success', async () => {
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

    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string; navigate?: (path: string) => void }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" navigate={navigate} />);

    fillResetForm();
    fireEvent.click(screen.getByRole('button', { name: /reset|submit|change/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/login');
    });

    expect(calls.length).toBeGreaterThanOrEqual(2);
    expect(calls[0].url).toBe(CSRF_COOKIE_URL);
    expect(calls[0].credentials).toBe('include');
    expect(calls[1].url).toBe('/reset-password');

    vi.unstubAllGlobals();
  });

  it('suppresses the mutation and shows the existing accessible alert when the CSRF bootstrap fails', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: false, status: 500, json: async () => ({}) } as Response;
      }

      throw new Error('mutation must not be reached when bootstrap fails');
    });
    vi.stubGlobal('fetch', fetchMock);

    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" />);

    fillResetForm();
    fireEvent.click(screen.getByRole('button', { name: /reset|submit|change/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(1);

    vi.unstubAllGlobals();
  });

  it('exposes an alert on a failed reset response (e.g. invalid/expired token)', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      return { ok: false, status: 422, json: async () => ({ message: 'invalid token' }) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);

    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" />);

    fillResetForm();
    fireEvent.click(screen.getByRole('button', { name: /reset|submit|change/i }));

    expect(await screen.findByRole('alert')).toBeInTheDocument();

    vi.unstubAllGlobals();
  });

  it('never persists an auth token to localStorage', async () => {
    const { ResetPasswordForm } = await importAuthModule<{
      ResetPasswordForm: React.ComponentType<{ token: string; email: string }>;
    }>('components/auth/ResetPasswordForm');
    render(<ResetPasswordForm token="test-token" email="ada@example.com" />);

    expect(window.localStorage.getItem('token')).toBeNull();
    expect(window.localStorage.getItem('access_token')).toBeNull();
  });
});

describe('LoginForm — discoverable forgot-password link (PRD-08)', () => {
  it('renders a link to /forgot-password reachable by keyboard', async () => {
    const { LoginForm } = await importAuthModule<{ LoginForm: React.ComponentType }>('components/auth/LoginForm');
    render(<LoginForm />);

    const link = screen.getByRole('link', { name: /forgot/i });
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/forgot-password');
  });
});

describe('auth.tsx — mounts forgot-password and reset-password data-auth-view values (PRD-08)', () => {
  it('wires the forgot-password and reset-password views to their components', () => {
    const source = readFileSync(resolve(__dirname, '../../auth.tsx'), 'utf-8');

    expect(source).toMatch(/['"]forgot-password['"]/);
    expect(source).toMatch(/ForgotPasswordForm/);
    expect(source).toMatch(/['"]reset-password['"]/);
    expect(source).toMatch(/ResetPasswordForm/);
  });
});
