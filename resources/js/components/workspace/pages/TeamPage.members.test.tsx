import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamPage } from './TeamPage';

/**
 * TEAM_MEMBERS_FRONTEND_RED
 *
 * RED suite for the S1-WP01A Team member-list slice. Correction: TeamPage
 * is frozen to a real, server-authoritative member list — GET
 * /api/workspaces/{workspaceId}/team/members on mount with credentials
 * same-origin — instead of the current always-unavailable UnavailableRegion
 * stub. TeamPage today accepts no workspaceId prop, performs no fetch, and
 * its "Team members" region only ever renders the static "not connected"
 * status line, so every assertion below must fail against current
 * production. Invite and Pending invitations stay explicitly disabled/
 * unavailable and are not touched by this suite. No production, i18n,
 * Storybook, backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 5;
const MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;

describe('TeamPage — S1-WP01A real member list (TEAM_MEMBERS_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => [],
                } as Response;
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return {
                    ok: true,
                    status: 200,
                    json: async () => [],
                } as Response;
            }
            throw new Error(`Unhandled fetch: ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches the workspace team member list on mount with credentials same-origin', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const [url, init] = fetchSpy.mock.calls[0];
        expect(String(url)).toBe(MEMBERS_ENDPOINT);
        expect(init).toMatchObject({ credentials: 'same-origin' });
    });

    it('renders a loading state before the member list resolves', () => {
        fetchSpy.mockImplementation(() => new Promise(() => {}));

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /team members/i });
        expect(within(membersRegion).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('renders an honest empty state when the workspace has zero members', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT) {
                return { ok: true, status: 200, json: async () => [] } as Response;
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return { ok: true, status: 200, json: async () => [] } as Response;
            }
            throw new Error(`Unhandled fetch: ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /team members/i });

        await waitFor(() => {
            expect(within(membersRegion).getByRole('status')).toHaveTextContent(/no members/i);
        });

        expect(within(membersRegion).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('renders real populated member rows with server name, email and role — no fabricated ids', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => [
                        { id: 1, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
                        { id: 2, name: 'Mehmet Demir', email: 'mehmet@example.test', role: 'member' },
                    ],
                } as Response;
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return { ok: true, status: 200, json: async () => [] } as Response;
            }
            throw new Error(`Unhandled fetch: ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /team members/i });

        await waitFor(() => {
            expect(within(membersRegion).getByText('Ayşe Yılmaz')).toBeInTheDocument();
        });

        expect(within(membersRegion).getByText('ayse@example.test')).toBeInTheDocument();
        expect(within(membersRegion).getByText(/owner/i)).toBeInTheDocument();

        expect(within(membersRegion).getByText('Mehmet Demir')).toBeInTheDocument();
        expect(within(membersRegion).getByText('mehmet@example.test')).toBeInTheDocument();
        expect(within(membersRegion).getByText(/member/i)).toBeInTheDocument();

        expect(within(membersRegion).queryAllByRole('listitem')).toHaveLength(2);
    });

    it('renders an honest error state when the fetch fails, without fabricating members', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT) {
                return { ok: false, status: 404, json: async () => ({}) } as Response;
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return { ok: true, status: 200, json: async () => [] } as Response;
            }
            throw new Error(`Unhandled fetch: ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /team members/i });

        await waitFor(() => {
            expect(within(membersRegion).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(within(membersRegion).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('renders the real member list independently of the pending-invitations region while both load', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => [{ id: 1, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' }],
                } as Response;
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return { ok: true, status: 200, json: async () => [] } as Response;
            }
            throw new Error(`Unhandled fetch: ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /^team members$/i });

        await waitFor(() => {
            expect(within(membersRegion).getByText('Ayşe Yılmaz')).toBeInTheDocument();
        });

        const pendingRegion = screen.getByRole('region', { name: /pending invitation/i });
        expect(pendingRegion).toBeInTheDocument();
    });
});

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * TEAM_ACTIONS_FRONTEND_RED — member remove slice.
 *
 * TeamMemberList today renders member rows as plain <span> elements with no
 * interactive control. Every assertion below must fail against current
 * production: there is no Remove control, no Owner-exclusion rule to prove
 * (there is nothing to exclude), no inline confirm/cancel step, and no
 * DELETE call to the members endpoint.
 */
describe('TeamPage — S1-WP01A member remove (TEAM_ACTIONS_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let currentMembers: unknown[];

    const OWNER_ID = 1;
    const EDITOR_ID = 2;

    beforeEach(() => {
        currentMembers = [
            { id: OWNER_ID, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
            { id: EDITOR_ID, name: 'Mehmet Demir', email: 'mehmet@example.test', role: 'editor' },
        ];
        document.cookie = 'XSRF-TOKEN=s1-wp01a-remove-test-token';

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === '/sanctum/csrf-cookie') {
                return jsonResponse(204, null);
            }
            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, currentMembers);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, []);
            }
            if (href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}` && method === 'DELETE') {
                currentMembers = currentMembers.filter((member) => (member as { id: number }).id !== EDITOR_ID);

                return jsonResponse(204, null);
            }

            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function membersRegion() {
        return screen.getByRole('region', { name: /team members/i });
    }

    function rowFor(name: string): HTMLElement {
        const nameNode = within(membersRegion()).getByText(name);
        const row = nameNode.closest('li');
        if (!row) {
            throw new Error(`expected a <li> row ancestor for ${name}`);
        }
        return row as HTMLElement;
    }

    it('never exposes a Remove control on the Owner row', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Ayşe Yılmaz')).toBeInTheDocument();
        });

        const ownerRow = rowFor('Ayşe Yılmaz');
        expect(within(ownerRow).queryByRole('button', { name: /remove/i })).not.toBeInTheDocument();
    });

    it('exposes an accessible Remove control on an Editor row keyed by the server membership id', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        expect(within(editorRow).getByRole('button', { name: /remove/i })).toBeInTheDocument();
    });

    it('entering remove shows an inline confirm/cancel step and issues no DELETE yet', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));

        expect(within(editorRow).getByRole('button', { name: /^confirm/i })).toBeInTheDocument();
        expect(within(editorRow).getByRole('button', { name: /^(keep|cancel)/i })).toBeInTheDocument();
        expect(
            fetchSpy.mock.calls.find(([, init]) => (init as RequestInit | undefined)?.method === 'DELETE'),
        ).toBeUndefined();
    });

    it('confirming remove bootstraps CSRF then DELETEs the membership with same-origin credentials, JSON Accept and X-XSRF-TOKEN', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));
        await user.click(within(editorRow).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            const deleteCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    String(url) === `${MEMBERS_ENDPOINT}/${EDITOR_ID}` &&
                    (init as RequestInit | undefined)?.method === 'DELETE',
            );
            expect(deleteCall).toBeDefined();
        });

        const deleteCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                String(url) === `${MEMBERS_ENDPOINT}/${EDITOR_ID}` &&
                (init as RequestInit | undefined)?.method === 'DELETE',
        );
        const deleteInit = deleteCall?.[1] as RequestInit;
        const deleteHeaders = new Headers(deleteInit.headers);

        expect(deleteInit.credentials).toBe('same-origin');
        expect(deleteHeaders.get('Accept')).toBe('application/json');
        expect(deleteHeaders.get('X-XSRF-TOKEN')).toBe(readCookie('XSRF-TOKEN'));

        const csrfCall = fetchSpy.mock.calls.find(([url]) => String(url) === '/sanctum/csrf-cookie');
        expect(csrfCall).toBeDefined();
    });

    it('after a successful remove, re-fetches members server-authoritatively, drops only the removed row, and announces status', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const getCallsBefore = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === MEMBERS_ENDPOINT && ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));
        await user.click(within(editorRow).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(membersRegion()).queryByText('Mehmet Demir')).not.toBeInTheDocument();
        });

        expect(within(membersRegion()).getByText('Ayşe Yılmaz')).toBeInTheDocument();
        expect(within(membersRegion()).queryAllByRole('listitem')).toHaveLength(1);

        const getCallsAfter = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === MEMBERS_ENDPOINT && ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;
        expect(getCallsAfter).toBeGreaterThan(getCallsBefore);

        expect(within(membersRegion()).getByRole('status')).toHaveTextContent(/removed|remove/i);
    });

    it('on a remove failure, keeps the member row, shows a row-specific error and re-enables retry', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === '/sanctum/csrf-cookie') return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, currentMembers);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}` && method === 'DELETE') {
                return jsonResponse(500, { message: 'Failed' });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));
        await user.click(within(editorRow).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(editorRow).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        expect(within(editorRow).getByRole('button', { name: /^confirm/i })).toBeEnabled();
    });

    it('uses no breakpoint-prefixed classes on the remove controls at a 320 fluid viewport', async () => {
        const user = userEvent.setup();
        const { container } = render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));

        const breakpointToken = /(?:^|\s)(sm|md|lg|xl|2xl):/;
        const classAttributes = Array.from(container.querySelectorAll('[class]')).map(
            (el) => el.getAttribute('class') ?? '',
        );
        for (const classAttribute of classAttributes) {
            expect(classAttribute).not.toMatch(breakpointToken);
        }
    });

    it('TEAM_ACTION_REFETCH_LIFECYCLE_RED: when the post-remove DELETE succeeds (204) but the first authoritative members refetch fails, keeps the row visible with a row-specific error/retry and makes no success claim; retry re-issues only the GET (never a second DELETE), and success is announced only once the server omission is observed', async () => {
        const user = userEvent.setup();
        let deleteCalls = 0;
        let getCallsAfterDelete = 0;
        let failNextGet = false;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === '/sanctum/csrf-cookie') return jsonResponse(204, null);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}` && method === 'DELETE') {
                deleteCalls += 1;
                currentMembers = currentMembers.filter((member) => (member as { id: number }).id !== EDITOR_ID);
                failNextGet = true;
                return jsonResponse(204, null);
            }
            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                if (failNextGet) {
                    getCallsAfterDelete += 1;
                    failNextGet = false;
                    return jsonResponse(500, { message: 'Refetch failed' });
                }
                if (deleteCalls > 0) {
                    getCallsAfterDelete += 1;
                }
                return jsonResponse(200, currentMembers);
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /remove/i }));
        await user.click(within(editorRow).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(editorRow).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        expect(within(editorRow).queryByText(/removed|success/i)).not.toBeInTheDocument();
        expect(deleteCalls).toBe(1);

        const retryButton = within(editorRow).getByRole('button', { name: /^retry$/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(within(membersRegion()).queryByText('Mehmet Demir')).not.toBeInTheDocument();
        });

        expect(deleteCalls).toBe(1);
        expect(getCallsAfterDelete).toBe(2);
        expect(within(membersRegion()).getByRole('status')).toHaveTextContent(/removed|remove/i);
    });
});
