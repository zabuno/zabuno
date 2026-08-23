import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * RED contract for the invitation-acceptance journey (S1-WP01A foundation
 * scope). resources/js/components/auth/InvitationAcceptForm.tsx does not
 * exist yet in this snapshot, so every dynamic import below fails RED
 * (module-not-found) until it is built. Also asserts the raw auth.tsx entry
 * source supports an invitation-accept view and stays free of any
 * breakpoint-prefixed (sm:/md:/lg:/xl:/2xl:) Tailwind class — 320 CSS px is
 * a fluid starting proof, not a breakpoint — which fails RED today because
 * auth.tsx currently ships `sm:px-6` and `sm:p-8`.
 */

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const ACCEPT_URL = 'https://zabuno.test/invitations/accept/abc123token';

function importAuthModule<T extends Record<string, unknown> = Record<string, unknown>>(
  relativePath: string,
): Promise<T> {
  const base = '../../';

  return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
}

type InvitationAcceptFormProps = {
  invitation: {
    workspaceName: string;
    invitedEmail: string;
    role: string;
    acceptUrl: string;
  };
  status: 'available' | 'guest' | 'invalid' | 'expired' | 'consumed';
  authenticated?: boolean;
  navigate?: (path: string) => void;
  loginUrl?: string;
};

function baseInvitation() {
  return {
    workspaceName: 'Acme Restaurant Group',
    invitedEmail: 'invited-member@example.com',
    role: 'editor',
    acceptUrl: ACCEPT_URL,
  };
}

describe('InvitationAcceptForm — server-authoritative invitation details (S1WP01A-INVITE-01)', () => {
  it('renders the server-provided workspace name, invited email and role, and no accept action outside available state', async () => {
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated />);

    expect(screen.getByText('Acme Restaurant Group')).toBeInTheDocument();
    expect(screen.getByText('invited-member@example.com')).toBeInTheDocument();
    expect(screen.getByText('editor')).toBeInTheDocument();
  });
});

describe('InvitationAcceptForm — authenticated available state (S1WP01A-INVITE-01)', () => {
  it('exposes an accessible Accept invitation action reachable by keyboard', async () => {
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated />);

    const accept = screen.getByRole('button', { name: /accept.*invitation/i });
    expect(accept).toBeInTheDocument();
    accept.focus();
    expect(accept).toHaveFocus();
  });
});

describe('InvitationAcceptForm — guest state (S1WP01A-INVITE-01)', () => {
  it('offers a login link that preserves the invitation return target and performs no write', async () => {
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    render(
      <InvitationAcceptForm
        invitation={baseInvitation()}
        status="guest"
        authenticated={false}
        loginUrl="/login?redirect=%2Finvitations%2Faccept%2Fabc123token"
      />,
    );

    expect(screen.queryByRole('button', { name: /accept.*invitation/i })).not.toBeInTheDocument();

    const loginLink = screen.getByRole('link', { name: /log ?in|sign in/i });
    expect(loginLink).toHaveAttribute('href', '/login?redirect=%2Finvitations%2Faccept%2Fabc123token');
    expect(fetchMock).not.toHaveBeenCalled();

    vi.unstubAllGlobals();
  });
});

describe.each([
  ['invalid', 'invalid'],
  ['expired', 'expired'],
  ['consumed', 'consumed'],
] as const)('InvitationAcceptForm — %s invitation state (S1WP01A-INVITE-01)', (_label, status) => {
  it('renders a generic, non-enumerating message with no accept action', async () => {
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status={status} authenticated />);

    expect(screen.queryByRole('button', { name: /accept.*invitation/i })).not.toBeInTheDocument();
    expect(screen.queryByText('Acme Restaurant Group')).not.toBeInTheDocument();
    expect(screen.queryByText('invited-member@example.com')).not.toBeInTheDocument();

    const message = screen.getByText((_content, element) => element?.tagName === 'P' && /./.test(element.textContent ?? ''));
    expect(message.textContent ?? '').not.toMatch(/invalid token|expired at|consumed by|already used by/i);
  });
});

describe('InvitationAcceptForm — acceptance request contract (S1WP01A-INVITE-01, S1WP01A-CSRF-01)', () => {
  it('bootstraps the CSRF cookie once, then POSTs exactly once to the exact server-provided acceptUrl with credentialed, JSON, XSRF-headed, non-bearer auth', async () => {
    const calls: Array<{ url: string; init?: RequestInit }> = [];
    const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
      calls.push({ url: String(url), init });

      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);
    document.cookie = 'XSRF-TOKEN=test-xsrf-token-value';

    const navigate = vi.fn();
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated navigate={navigate} />);

    fireEvent.click(screen.getByRole('button', { name: /accept.*invitation/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/app#dashboard');
    });

    const postCalls = calls.filter((c) => c.url === ACCEPT_URL);
    expect(postCalls.length).toBe(1);
    expect(calls[0].url).toBe(CSRF_COOKIE_URL);
    expect(calls[0].init?.credentials).toBe('include');

    const acceptCall = postCalls[0];
    expect(acceptCall.init?.method).toBe('POST');
    expect(acceptCall.init?.credentials).toBe('include');

    const headers = new Headers(acceptCall.init?.headers);
    expect(headers.get('Accept')).toBe('application/json');
    expect(headers.get('Content-Type')).toBe('application/json');
    expect(headers.get('X-XSRF-TOKEN')).toBe('test-xsrf-token-value');
    expect(headers.has('Authorization')).toBe(false);

    vi.unstubAllGlobals();
  });

  it('cannot duplicate the POST when the accept action is activated twice while a request is pending', async () => {
    let resolveAccept: (() => void) | undefined;
    const acceptPending = new Promise<void>((resolve) => {
      resolveAccept = resolve;
    });

    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      await acceptPending;

      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);

    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated />);

    const accept = screen.getByRole('button', { name: /accept.*invitation/i });
    fireEvent.click(accept);
    fireEvent.click(accept);
    fireEvent.click(accept);

    resolveAccept?.();

    await waitFor(() => {
      const postCalls = fetchMock.mock.calls.filter(([url]) => String(url) === ACCEPT_URL);
      expect(postCalls.length).toBe(1);
    });

    vi.unstubAllGlobals();
  });

  it('navigates only to the fixed /app#dashboard destination on success', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);

    const navigate = vi.fn();
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated navigate={navigate} />);
    fireEvent.click(screen.getByRole('button', { name: /accept.*invitation/i }));

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledTimes(1);
    });
    expect(navigate).toHaveBeenCalledWith('/app#dashboard');

    vi.unstubAllGlobals();
  });
});

describe('InvitationAcceptForm — failure handling never navigates (S1WP01A-INVITE-01)', () => {
  it('exposes an accessible, honest error and never navigates on a non-2xx accept response', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      return { ok: false, status: 422, json: async () => ({ message: 'error' }) } as Response;
    });
    vi.stubGlobal('fetch', fetchMock);

    const navigate = vi.fn();
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated navigate={navigate} />);
    fireEvent.click(screen.getByRole('button', { name: /accept.*invitation/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(navigate).not.toHaveBeenCalled();

    vi.unstubAllGlobals();
  });

  it('exposes an accessible, honest error and never navigates when the accept request rejects with a network failure', async () => {
    const fetchMock = vi.fn(async (url: string) => {
      if (String(url) === CSRF_COOKIE_URL) {
        return { ok: true, status: 204, json: async () => ({}) } as Response;
      }

      throw new TypeError('Failed to fetch');
    });
    vi.stubGlobal('fetch', fetchMock);

    const navigate = vi.fn();
    const { InvitationAcceptForm } = await importAuthModule<{
      InvitationAcceptForm: React.ComponentType<InvitationAcceptFormProps>;
    }>('components/auth/InvitationAcceptForm');

    render(<InvitationAcceptForm invitation={baseInvitation()} status="available" authenticated navigate={navigate} />);
    fireEvent.click(screen.getByRole('button', { name: /accept.*invitation/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toBeInTheDocument();
    expect(navigate).not.toHaveBeenCalled();

    vi.unstubAllGlobals();
  });
});

describe('auth.tsx entry — invitation-accept view wiring, no breakpoint-prefixed classes (S1WP01A-INVITE-01, S1WP01A-FLUID-320-01)', () => {
  it('supports an invitation-accept auth view in the raw entry source', () => {
    const source = readFileSync(resolve(__dirname, '../../auth.tsx'), 'utf-8');

    expect(source).toMatch(/invitation-accept/);
    expect(source).toMatch(/InvitationAcceptForm/);
  });

  it('contains no breakpoint-prefixed Tailwind classes, because 320 CSS px is a fluid starting proof, not a breakpoint', () => {
    const source = readFileSync(resolve(__dirname, '../../auth.tsx'), 'utf-8');

    const breakpointClasses = source.match(/\b(?:sm|md|lg|xl|2xl):[a-zA-Z0-9[\]/.-]+/g) ?? [];

    expect(breakpointClasses).toEqual([]);
  });
});
