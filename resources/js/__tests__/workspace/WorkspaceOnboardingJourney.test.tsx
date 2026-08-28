import type React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';

/**
 * Blind RED test candidate for S1-WP02C. Frozen scope hash
 * a1676c2ab292fc622a46b12717edf1d8afa1a0cdbb026733902edb0bdd5f3f65. No
 * resources/js/components/workspace directory exists in this snapshot, so
 * every dynamic import below fails RED (module-not-found) until the
 * WorkspaceApp shell is built. Covers: /api/user, /api/workspaces,
 * /api/workspace-context data fetch; zero-workspace one-name creation with
 * CSRF bootstrap before POST; workspaces/no-current chooser + PUT switch;
 * current-context render (name/slug/state/email, switcher, logout);
 * loading, retryable error, validation, disabled busy actions, ARIA live.
 */
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';

function importWorkspaceModule<T extends Record<string, unknown> = Record<string, unknown>>(
    relativePath: string,
): Promise<T> {
    const base = '../../';

    return import(/* @vite-ignore */ base + relativePath) as Promise<T>;
}

type MockUser = { id: number; name: string; email: string };
type MockWorkspace = { id: number; name: string; slug: string; state: string };

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function makeUser(): MockUser {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace(overrides: Partial<MockWorkspace> = {}): MockWorkspace {
    return { id: 7, name: 'Zeytin Restoranları', slug: 'zeytin-restoranlari', state: 'onboarding', ...overrides };
}

/**
 * Routes a fetch mock over the fixed contract surface used by the
 * WorkspaceApp shell, keyed by exact URL + method so each scenario only has
 * to override the handlers it cares about.
 */
function buildFetchMock(handlers: {
    user?: () => Response;
    workspaces?: () => Response;
    context?: () => Response;
    create?: (body: unknown) => Response;
    switchContext?: (body: unknown) => Response;
    logout?: () => Response;
}) {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return handlers.user ? handlers.user() : jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return handlers.workspaces ? handlers.workspaces() : jsonResponse(200, []);
        }
        if (String(url) === '/api/workspaces' && method === 'POST') {
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            return handlers.create ? handlers.create(body) : jsonResponse(201, makeWorkspace());
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return handlers.context ? handlers.context() : jsonResponse(409, { error: 'workspace_context_required' });
        }
        if (String(url) === '/api/workspace-context' && method === 'PUT') {
            const body = init?.body ? JSON.parse(String(init.body)) : {};
            return handlers.switchContext ? handlers.switchContext(body) : jsonResponse(200, makeWorkspace());
        }
        if (String(url) === '/logout' && method === 'POST') {
            return handlers.logout ? handlers.logout() : jsonResponse(200, {});
        }

        throw new Error(`Unhandled fetch in WorkspaceOnboardingJourney test: ${method} ${String(url)}`);
    });
}

describe('GET /app route guard (S1-WP02C, docs frozen scope)', () => {
    it('exposes a WorkspaceApp component that fetches /api/user, /api/workspaces and /api/workspace-context on mount', async () => {
        const fetchMock = buildFetchMock({ context: () => jsonResponse(200, makeWorkspace()) });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        await waitFor(() => {
            const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
            expect(calledUrls).toContain('/api/user');
            expect(calledUrls).toContain('/api/workspaces');
            expect(calledUrls).toContain('/api/workspace-context');
        });

        vi.unstubAllGlobals();
    });
});

describe('WorkspaceApp — loading state', () => {
    it('renders a status region while the initial fetches are in flight', async () => {
        let resolveUser: (value: Response) => void = () => {};
        const pendingUser = new Promise<Response>((resolve) => {
            resolveUser = resolve;
        });
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === '/api/user') return pendingUser;
            if (String(url) === '/api/workspaces') return jsonResponse(200, []);
            if (String(url) === '/api/workspace-context') return jsonResponse(409, { error: 'workspace_context_required' });
            return jsonResponse(200, {});
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        expect(screen.getByRole('status')).toBeInTheDocument();

        resolveUser(jsonResponse(200, makeUser()));
        await waitFor(() => {
            expect(screen.queryByRole('status', { name: /loading/i })).not.toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });
});

describe('WorkspaceApp — retryable error', () => {
    it('shows an alert with a retry action when the initial data fetch fails, and refetches on retry', async () => {
        let userCallCount = 0;
        const fetchMock = vi.fn(async (url: string) => {
            if (String(url) === '/api/user') {
                userCallCount += 1;
                if (userCallCount === 1) throw new TypeError('Failed to fetch');
                return jsonResponse(200, makeUser());
            }
            if (String(url) === '/api/workspaces') return jsonResponse(200, []);
            if (String(url) === '/api/workspace-context') return jsonResponse(409, { error: 'workspace_context_required' });
            return jsonResponse(200, {});
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();

        const retry = screen.getByRole('button', { name: /retry|try again/i });
        fireEvent.click(retry);

        await waitFor(() => {
            expect(userCallCount).toBeGreaterThanOrEqual(2);
        });

        vi.unstubAllGlobals();
    });
});

describe('WorkspaceApp — zero workspaces, one-name creation (S1-WP02C, docs/34 CREATE-01)', () => {
    it('bootstraps the CSRF cookie before POST /api/workspaces, and renders the created workspace as current on success', async () => {
        const fetchMock = buildFetchMock({
            context: () => jsonResponse(409, { error: 'workspace_context_required' }),
            create: (body) => {
                expect((body as { name: string }).name).toBe('Zeytin Restoranları');
                return jsonResponse(201, makeWorkspace());
            },
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        const nameInput = await screen.findByLabelText(/workspace name/i);
        fireEvent.change(nameInput, { target: { value: 'Zeytin Restoranları' } });
        fireEvent.click(screen.getByRole('button', { name: /create/i }));

        const createdBanner = await screen.findByRole('banner');
        await within(createdBanner).findByRole('button', { name: 'Zeytin Restoranları' });

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        const csrfIndex = calledUrls.indexOf(CSRF_COOKIE_URL);
        const createIndex = fetchMock.mock.calls.findIndex(
            (call) => String(call[0]) === '/api/workspaces' && ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
        );
        expect(csrfIndex).toBeGreaterThanOrEqual(0);
        expect(createIndex).toBeGreaterThan(csrfIndex);

        vi.unstubAllGlobals();
    });

    it('shows an accessible validation error and does not call the create endpoint when the name is empty', async () => {
        const fetchMock = buildFetchMock({ context: () => jsonResponse(409, { error: 'workspace_context_required' }) });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        await screen.findByLabelText(/workspace name/i);
        fireEvent.click(screen.getByRole('button', { name: /create/i }));

        const alert = await screen.findByRole('alert');
        expect(alert).toBeInTheDocument();

        const postCalls = fetchMock.mock.calls.filter(
            (call) => String(call[0]) === '/api/workspaces' && ((call[1] as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
        );
        expect(postCalls).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    it('disables the create action while the create request is in flight', async () => {
        let resolveCreate: (value: Response) => void = () => {};
        const pendingCreate = new Promise<Response>((resolve) => {
            resolveCreate = resolve;
        });
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/user') return jsonResponse(200, makeUser());
            if (String(url) === '/api/workspaces' && method === 'GET') return jsonResponse(200, []);
            if (String(url) === '/api/workspace-context') return jsonResponse(409, { error: 'workspace_context_required' });
            if (String(url) === '/api/workspaces' && method === 'POST') return pendingCreate;
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        const nameInput = await screen.findByLabelText(/workspace name/i);
        fireEvent.change(nameInput, { target: { value: 'Zeytin Restoranları' } });
        const createButton = screen.getByRole('button', { name: /create/i });
        fireEvent.click(createButton);

        await waitFor(() => {
            expect(createButton).toBeDisabled();
        });

        resolveCreate(jsonResponse(201, makeWorkspace()));
        vi.unstubAllGlobals();
    });
});

describe('WorkspaceApp — workspaces exist but no current context (S1-WP02C)', () => {
    it('offers an accessible chooser listing every workspace and PUTs the selected id to /api/workspace-context', async () => {
        const workspaces = [makeWorkspace({ id: 7, name: 'Zeytin Restoranları' }), makeWorkspace({ id: 8, name: 'Deniz Kebap' })];
        const fetchMock = buildFetchMock({
            workspaces: () => jsonResponse(200, workspaces),
            context: () => jsonResponse(409, { error: 'workspace_context_required' }),
            switchContext: (body) => {
                expect((body as { workspace_id: number }).workspace_id).toBe(8);
                return jsonResponse(200, workspaces[1]);
            },
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        const option = await screen.findByRole('button', { name: /Deniz Kebap/i });
        fireEvent.click(option);

        await waitFor(() => {
            const putCalls = fetchMock.mock.calls.filter(
                (call) => String(call[0]) === '/api/workspace-context' && (call[1] as RequestInit | undefined)?.method === 'PUT',
            );
            expect(putCalls).toHaveLength(1);
        });

        vi.unstubAllGlobals();
    });
});

describe('WorkspaceApp — current workspace context render (S1-WP02C)', () => {
    // SÖZLEŞME DEĞİŞTİ (2026-08-27): `slug` ve `state` artık İÇERİK
    // ALANINA basılmıyor. Üç etiketsiz satır — slug, durum kodu ve
    // kullanıcının e-postası — her sayfanın tepesinde duruyordu; bu içerik
    // değil, hata ayıklama dökümüydü ve kullanıcının asıl işini aşağı
    // itiyordu (docs/37 §1: görev tamamlama → içerik).
    //
    // Bilgi SİLİNMEDİ, yerine taşındı: çalışma alanı kimliği üst çubukta
    // (ad + şube seçici), oturum sahibinin e-postası da kimlik alanında.
    // "Hangi hesaptayım?" çok kiracılı bir panelde gerçek bir sorudur;
    // cevabı içerik akışında değil, kimlik alanında durur.
    it('renders workspace name, the signed-in email in the identity area, a workspace switcher and a logout control', async () => {
        const workspaces = [makeWorkspace({ id: 7, name: 'Zeytin Restoranları' }), makeWorkspace({ id: 8, name: 'Deniz Kebap' })];
        const fetchMock = buildFetchMock({
            workspaces: () => jsonResponse(200, workspaces),
            context: () => jsonResponse(200, workspaces[0]),
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        const contextBanner = await screen.findByRole('banner');
        await within(contextBanner).findByRole('button', { name: 'Zeytin Restoranları' });
        // E-posta kimlik alanında (üst çubuk), içerik akışında değil.
        expect(within(contextBanner).getByText('ada@example.com')).toBeInTheDocument();
        expect(screen.queryByText('zeytin-restoranlari')).not.toBeInTheDocument();
        // Hesap kontrolleri kenar çubuğunun dibinden kimlik alanındaki hesap
        // menüsüne taşındı: gezinti değildirler ve görev maddelerinin arasına
        // karıştıklarında ikisi de okunmaz olur. Kontroller kaybolmadı, bu
        // yüzden test menüyü AÇIP arıyor.
        fireEvent.click(within(contextBanner).getByRole('button', { name: 'Account' }));
        expect(
            await screen.findByRole('menuitem', { name: /switch workspace|change workspace/i }),
        ).toBeInTheDocument();
        expect(screen.getByRole('menuitem', { name: /log ?out/i })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('announces context transitions through an ARIA live region', async () => {
        const fetchMock = buildFetchMock({ context: () => jsonResponse(200, makeWorkspace()) });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        const { container } = render(<WorkspaceApp />);

        const liveBanner = await screen.findByRole('banner');
        await within(liveBanner).findByRole('button', { name: 'Zeytin Restoranları' });

        const liveRegion = container.querySelector('[aria-live]');
        expect(liveRegion).not.toBeNull();
        expect(liveRegion).toHaveTextContent('Zeytin Restoranları is now the current workspace.');

        vi.unstubAllGlobals();
    });

    it('posts to /logout on logout click and disables the logout control while the request is in flight', async () => {
        let resolveLogout: (value: Response) => void = () => {};
        const pendingLogout = new Promise<Response>((resolve) => {
            resolveLogout = resolve;
        });
        const fetchMock = vi.fn(async (url: string, init?: RequestInit) => {
            const method = (init?.method ?? 'GET').toUpperCase();
            if (String(url) === CSRF_COOKIE_URL) return jsonResponse(204, {});
            if (String(url) === '/api/user') return jsonResponse(200, makeUser());
            if (String(url) === '/api/workspaces') return jsonResponse(200, [makeWorkspace()]);
            if (String(url) === '/api/workspace-context' && method === 'GET') return jsonResponse(200, makeWorkspace());
            if (String(url) === '/logout') return pendingLogout;
            throw new Error(`Unhandled fetch: ${method} ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchMock);

        const { WorkspaceApp } = await importWorkspaceModule<{ WorkspaceApp: React.ComponentType }>(
            'components/workspace/WorkspaceApp',
        );
        render(<WorkspaceApp />);

        // Hesap menüsü önce açılır (kontrol kimlik alanına taşındı).
        fireEvent.click(
            within(await screen.findByRole('banner')).getByRole('button', { name: 'Account' }),
        );
        const logoutButton = await screen.findByRole('menuitem', { name: /log ?out/i });
        fireEvent.click(logoutButton);

        await waitFor(() => {
            expect(logoutButton).toBeDisabled();
        });

        const calledUrls = fetchMock.mock.calls.map((call) => String(call[0]));
        expect(calledUrls).toContain('/logout');

        resolveLogout(jsonResponse(200, {}));
        vi.unstubAllGlobals();
    });
});
