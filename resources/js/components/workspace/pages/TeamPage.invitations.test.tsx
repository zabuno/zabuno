import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamPage } from './TeamPage';

/**
 * TEAM_INVITATIONS_FRONTEND_RED
 *
 * RED suite for the S1-WP01A owner Team invitation slice. TeamPage today
 * renders a permanently-disabled Invite button, a static "not available"
 * status line, and a static UnavailableRegion for "Pending invitations" —
 * no invitation fetch, no POST, no Owner/Editor split enforcement. This
 * suite freezes the real contract instead:
 *
 *  - GET  /api/workspaces/{workspaceId}/team/invitations on mount
 *    (pending invitations list, server-authoritative — no fabricated rows).
 *  - Only an Editor invitation may be created from this UI; the Owner
 *    invitation control is removed (ownership transfer is a separate flow),
 *    so the invite form must NOT expose an "Owner" role radio/option.
 *  - Submitting a valid email bootstraps the CSRF cookie
 *    (GET /sanctum/csrf-cookie) before POSTing
 *    POST /api/workspaces/{workspaceId}/team/invitations with credentials
 *    same-origin, Accept/Content-Type: application/json, X-XSRF-TOKEN, and
 *    a body of exactly { email, role: 'editor' }.
 *  - A successful POST triggers a fresh GET of the invitations endpoint and
 *    renders only the server-returned pending rows (no client-fabricated
 *    row), then clears the email field and announces success.
 *  - Loading/empty/load-error/submitting/submit-error states are all real
 *    and honest; a submit error keeps the typed email in the input.
 *  - No breakpoint-prefixed classes anywhere in the invite/pending surface
 *    (320 fluid, no responsive tokens).
 *
 * Every assertion below must fail against current production: TeamPage has
 * no invitations fetch, an unconditionally disabled Invite button, and a
 * static UnavailableRegion in place of a real pending list. No production,
 * i18n, Storybook, backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 5;
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;
const MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const BREAKPOINT_TOKEN = /(?:^|\s)(sm|md|lg|xl|2xl):/;

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

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

function allClassAttributes(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[class]')).map(
        (el) => el.getAttribute('class') ?? '',
    );
}

describe('TeamPage — S1-WP01A owner invitation creation + pending list (TEAM_INVITATIONS_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let pendingInvitations: unknown[];

    beforeEach(() => {
        pendingInvitations = [];
        document.cookie = 'XSRF-TOKEN=s1-wp01a-invite-test-token';

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === CSRF_COOKIE_URL) {
                return jsonResponse(204, null);
            }
            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, []);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, pendingInvitations);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'POST') {
                const submitted = JSON.parse(String(init?.body ?? '{}')) as {
                    email: string;
                    role: string;
                };
                pendingInvitations = [
                    ...pendingInvitations,
                    { id: 900, email: submitted.email, role: submitted.role, status: 'pending' },
                ];

                return jsonResponse(201, {
                    id: 900,
                    email: submitted.email,
                    role: submitted.role,
                    status: 'pending',
                });
            }

            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function pendingRegion() {
        return screen.getByRole('region', { name: /pending invitation/i });
    }

    it('fetches the workspace pending invitations on mount with credentials same-origin', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            const call = fetchSpy.mock.calls.find(([url]) => String(url) === INVITATIONS_ENDPOINT);
            expect(call).toBeDefined();
        });

        const call = fetchSpy.mock.calls.find(([url]) => String(url) === INVITATIONS_ENDPOINT);
        const init = call?.[1] as RequestInit | undefined;
        expect(init).toMatchObject({ credentials: 'same-origin' });
    });

    it('renders a loading state for pending invitations before the fetch resolves', () => {
        fetchSpy.mockImplementation(() => new Promise(() => {}));

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(/loading/i);
    });

    it('renders an honest empty pending-invitations state with zero fabricated rows', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(
                /no pending|empty/i,
            );
        });

        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('renders real server pending invitation rows — no fabricated ids', async () => {
        pendingInvitations = [
            { id: 41, email: 'aday@example.test', role: 'editor', status: 'pending' },
            { id: 42, email: 'ikinci@example.test', role: 'editor', status: 'pending' },
        ];

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        expect(within(pendingRegion()).getByText('ikinci@example.test')).toBeInTheDocument();
        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(2);
    });

    it('renders an honest error state when the pending invitations fetch fails', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(500, {});
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(
                /error|failed|unable/i,
            );
        });

        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('does not expose an Owner invitation control — Editor is the only invitable role', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        expect(screen.queryByRole('radio', { name: /owner/i })).not.toBeInTheDocument();
        expect(screen.queryByLabelText(/owner/i)).not.toBeInTheDocument();
    });

    it('enables the Invite action once a valid email is entered, and disables it while empty/invalid', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const inviteButton = screen.getByRole('button', { name: /invite/i });
        expect(inviteButton).toBeDisabled();

        const emailInput = screen.getByLabelText(/invite.*email/i);
        await user.type(emailInput, 'not-an-email');
        expect(inviteButton).toBeDisabled();

        await user.clear(emailInput);
        await user.type(emailInput, 'valid@example.test');
        expect(inviteButton).toBeEnabled();
    });

    it('bootstraps CSRF then POSTs the invitation with credentials same-origin, JSON headers, X-XSRF-TOKEN and body { email, role: "editor" }', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const emailInput = screen.getByLabelText(/invite.*email/i);
        await user.type(emailInput, 'newmember@example.test');
        await user.click(screen.getByRole('button', { name: /invite/i }));

        await waitFor(() => {
            const csrfCall = fetchSpy.mock.calls.find(([url]) => String(url) === CSRF_COOKIE_URL);
            expect(csrfCall).toBeDefined();
        });

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    String(url) === INVITATIONS_ENDPOINT &&
                    (init as RequestInit | undefined)?.method === 'POST',
            );
            expect(postCall).toBeDefined();
        });

        const postCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                String(url) === INVITATIONS_ENDPOINT &&
                (init as RequestInit | undefined)?.method === 'POST',
        );
        const postInit = postCall?.[1] as RequestInit;
        const postHeaders = new Headers(postInit.headers);

        expect(postInit.credentials).toBe('same-origin');
        expect(postHeaders.get('Accept')).toBe('application/json');
        expect(postHeaders.get('Content-Type')).toMatch(/application\/json/);
        expect(postHeaders.get('X-XSRF-TOKEN')).toBe(readCookie('XSRF-TOKEN'));

        const body = JSON.parse(String(postInit.body));
        expect(body).toEqual({ email: 'newmember@example.test', role: 'editor' });
    });

    it('shows a submitting state while the invite POST is in flight', async () => {
        const user = userEvent.setup();
        let resolvePost: (() => void) | undefined;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET')
                return jsonResponse(200, pendingInvitations);
            if (href === INVITATIONS_ENDPOINT && method === 'POST') {
                return new Promise((resolve) => {
                    resolvePost = () =>
                        resolve(
                            jsonResponse(201, {
                                id: 1,
                                email: 'x@example.test',
                                role: 'editor',
                                status: 'pending',
                            }),
                        );
                });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const emailInput = screen.getByLabelText(/invite.*email/i);
        await user.type(emailInput, 'inflight@example.test');
        const inviteButton = screen.getByRole('button', { name: /invite/i });
        await user.click(inviteButton);

        await waitFor(() => expect(inviteButton).toBeDisabled());
        expect(screen.getByText(/sending|submitting/i)).toBeInTheDocument();

        resolvePost?.();
    });

    it('after a successful invite, re-fetches invitations and renders only the fresh server rows, then clears the email and announces success', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const emailInput = screen.getByLabelText(/invite.*email/i) as HTMLInputElement;
        await user.type(emailInput, 'fresh@example.test');
        await user.click(screen.getByRole('button', { name: /invite/i }));

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('fresh@example.test')).toBeInTheDocument();
        });

        const getCallsAfter = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === INVITATIONS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        );
        expect(getCallsAfter.length).toBeGreaterThanOrEqual(2);

        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(1);
        expect(
            within(pendingRegion()).queryByText('newmember@example.test'),
        ).not.toBeInTheDocument();

        await waitFor(() => expect(emailInput.value).toBe(''));

        const inviteForm = emailInput.closest('fieldset') as HTMLElement;
        expect(within(inviteForm).getByText(/invited|success|sent/i)).toBeInTheDocument();
    });

    it('on a submit error, keeps the typed email in the input and shows an honest error without fabricating a pending row', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'POST')
                return jsonResponse(422, { message: 'Invalid' });
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const emailInput = screen.getByLabelText(/invite.*email/i) as HTMLInputElement;
        await user.type(emailInput, 'willfail@example.test');
        await user.click(screen.getByRole('button', { name: /invite/i }));

        await waitFor(() => {
            expect(screen.getByText(/error|failed|unable|could not/i)).toBeInTheDocument();
        });

        expect(emailInput.value).toBe('willfail@example.test');
        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(0);
    });

    it('uses no breakpoint-prefixed classes in the invite/pending surface at a 320 fluid viewport', async () => {
        const { container } = render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });
});

/**
 * TEAM_ACTIONS_FRONTEND_RED — invitation cancel slice.
 *
 * TeamPage today renders pending invitation rows with no interactive
 * control at all (TeamInvitationList emits three <span> elements per <li>,
 * no button). Every assertion below must fail against current production:
 * there is no Cancel invitation control, no inline confirm/keep step, and
 * no DELETE call to the invitations endpoint.
 */
describe('TeamPage — S1-WP01A pending invitation cancel (TEAM_ACTIONS_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let pendingInvitations: unknown[];

    const INVITATION_ID = 41;

    beforeEach(() => {
        pendingInvitations = [
            { id: INVITATION_ID, email: 'aday@example.test', role: 'editor', status: 'pending' },
            { id: 42, email: 'ikinci@example.test', role: 'editor', status: 'pending' },
        ];
        document.cookie = 'XSRF-TOKEN=s1-wp01a-cancel-test-token';

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === CSRF_COOKIE_URL) {
                return jsonResponse(204, null);
            }
            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, []);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, pendingInvitations);
            }
            if (href === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` && method === 'DELETE') {
                pendingInvitations = pendingInvitations.filter(
                    (invitation) => (invitation as { id: number }).id !== INVITATION_ID,
                );

                return jsonResponse(204, null);
            }

            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function pendingRegion() {
        return screen.getByRole('region', { name: /pending invitation/i });
    }

    function rowFor(email: string): HTMLElement {
        const emailNode = within(pendingRegion()).getByText(email);
        const row = emailNode.closest('li');
        if (!row) {
            throw new Error(`expected a <li> row ancestor for ${email}`);
        }
        return row as HTMLElement;
    }

    it('exposes an accessible Cancel invitation control on each pending row', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        expect(within(row).getByRole('button', { name: /cancel invitation/i })).toBeInTheDocument();
    });

    it('entering cancel shows an inline confirm/keep step without a native confirm dialog, and issues no DELETE yet', async () => {
        const user = userEvent.setup();
        const confirmSpy = vi.spyOn(window, 'confirm');
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));

        expect(confirmSpy).not.toHaveBeenCalled();
        expect(within(row).getByRole('button', { name: /^confirm/i })).toBeInTheDocument();
        expect(within(row).getByRole('button', { name: /^keep/i })).toBeInTheDocument();
        expect(
            fetchSpy.mock.calls.find(
                ([, init]) => (init as RequestInit | undefined)?.method === 'DELETE',
            ),
        ).toBeUndefined();

        confirmSpy.mockRestore();
    });

    it('confirming cancel bootstraps CSRF then DELETEs the invitation with same-origin credentials, JSON Accept and X-XSRF-TOKEN', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            const deleteCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    String(url) === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` &&
                    (init as RequestInit | undefined)?.method === 'DELETE',
            );
            expect(deleteCall).toBeDefined();
        });

        const deleteCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                String(url) === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` &&
                (init as RequestInit | undefined)?.method === 'DELETE',
        );
        const deleteInit = deleteCall?.[1] as RequestInit;
        const deleteHeaders = new Headers(deleteInit.headers);

        expect(deleteInit.credentials).toBe('same-origin');
        expect(deleteHeaders.get('Accept')).toBe('application/json');
        expect(deleteHeaders.get('X-XSRF-TOKEN')).toBe(readCookie('XSRF-TOKEN'));

        const csrfCall = fetchSpy.mock.calls.find(([url]) => String(url) === CSRF_COOKIE_URL);
        expect(csrfCall).toBeDefined();
    });

    it('clicking Keep aborts the cancel without issuing a DELETE and restores the Cancel invitation control', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^keep/i }));

        expect(within(row).getByRole('button', { name: /cancel invitation/i })).toBeInTheDocument();
        expect(
            fetchSpy.mock.calls.find(
                ([, init]) => (init as RequestInit | undefined)?.method === 'DELETE',
            ),
        ).toBeUndefined();
    });

    it('only the row being cancelled is busy/disabled while a DELETE is in flight', async () => {
        const user = userEvent.setup();
        let resolveDelete: (() => void) | undefined;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET')
                return jsonResponse(200, pendingInvitations);
            if (href === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` && method === 'DELETE') {
                return new Promise((resolve) => {
                    resolveDelete = () => resolve(jsonResponse(204, null));
                });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const busyRow = rowFor('aday@example.test');
        const otherRow = rowFor('ikinci@example.test');

        await user.click(within(busyRow).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(busyRow).getByRole('button', { name: /^confirm/i }));

        await waitFor(() =>
            expect(within(busyRow).getByRole('button', { name: /^confirm/i })).toBeDisabled(),
        );
        expect(within(otherRow).getByRole('button', { name: /cancel invitation/i })).toBeEnabled();

        resolveDelete?.();
    });

    it('after a successful cancel, re-fetches invitations, removes only the cancelled row once the server list omits it, and announces status', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const getCallsBefore = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === INVITATIONS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(
                within(pendingRegion()).queryByText('aday@example.test'),
            ).not.toBeInTheDocument();
        });

        expect(within(pendingRegion()).getByText('ikinci@example.test')).toBeInTheDocument();
        expect(within(pendingRegion()).queryAllByRole('listitem')).toHaveLength(1);

        const getCallsAfter = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === INVITATIONS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;
        expect(getCallsAfter).toBeGreaterThan(getCallsBefore);

        expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(/cancel|removed/i);
    });

    it('on a cancel failure, keeps the invitation row, shows a row-specific error and re-enables retry', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET')
                return jsonResponse(200, pendingInvitations);
            if (href === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` && method === 'DELETE') {
                return jsonResponse(500, { message: 'Failed' });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(row).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        expect(within(row).getByRole('button', { name: /^confirm/i })).toBeEnabled();
    });

    it('uses no breakpoint-prefixed classes on the cancel controls at a 320 fluid viewport', async () => {
        const user = userEvent.setup();
        const { container } = render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });

    it('TEAM_ACTION_REFETCH_LIFECYCLE_RED: when the post-cancel DELETE succeeds (204) but the first authoritative refetch fails, keeps the row visible with a row-specific error/retry and makes no success claim; retry re-issues only the GET (never a second DELETE), and success is announced only once the server omission is observed', async () => {
        const user = userEvent.setup();
        let deleteCalls = 0;
        let getCallsAfterDelete = 0;
        let failNextGet = false;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` && method === 'DELETE') {
                deleteCalls += 1;
                pendingInvitations = pendingInvitations.filter(
                    (invitation) => (invitation as { id: number }).id !== INVITATION_ID,
                );
                failNextGet = true;
                return jsonResponse(204, null);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                if (failNextGet) {
                    getCallsAfterDelete += 1;
                    failNextGet = false;
                    return jsonResponse(500, { message: 'Refetch failed' });
                }
                if (deleteCalls > 0) {
                    getCallsAfterDelete += 1;
                }
                return jsonResponse(200, pendingInvitations);
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(row).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        expect(within(row).queryByText(/cancelled|removed|success/i)).not.toBeInTheDocument();
        expect(deleteCalls).toBe(1);

        const retryButton = within(row).getByRole('button', { name: /^retry$/i });
        await user.click(retryButton);

        await waitFor(() => {
            expect(
                within(pendingRegion()).queryByText('aday@example.test'),
            ).not.toBeInTheDocument();
        });

        expect(deleteCalls).toBe(1);
        expect(getCallsAfterDelete).toBe(2);
        expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(/cancel|removed/i);
    });

    it('TEAM_ACTION_REFETCH_LIFECYCLE_RED: a later real invitation create clears a stale cancel-success announcement while preserving the create success', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === `${INVITATIONS_ENDPOINT}/${INVITATION_ID}` && method === 'DELETE') {
                pendingInvitations = pendingInvitations.filter(
                    (invitation) => (invitation as { id: number }).id !== INVITATION_ID,
                );
                return jsonResponse(204, null);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return jsonResponse(200, pendingInvitations);
            }
            if (href === INVITATIONS_ENDPOINT && method === 'POST') {
                const submitted = JSON.parse(String(init?.body ?? '{}')) as {
                    email: string;
                    role: string;
                };
                pendingInvitations = [
                    ...pendingInvitations,
                    { id: 901, email: submitted.email, role: submitted.role, status: 'pending' },
                ];
                return jsonResponse(201, {
                    id: 901,
                    email: submitted.email,
                    role: submitted.role,
                    status: 'pending',
                });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        await user.click(within(row).getByRole('button', { name: /cancel invitation/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(pendingRegion()).getByRole('status')).toHaveTextContent(
                /cancel|removed/i,
            );
        });

        const emailInput = screen.getByLabelText(/invite.*email/i) as HTMLInputElement;
        await user.type(emailInput, 'staleannouncement@example.test');
        await user.click(screen.getByRole('button', { name: /invite/i }));

        await waitFor(() => {
            expect(
                within(pendingRegion()).getByText('staleannouncement@example.test'),
            ).toBeInTheDocument();
        });

        const statusAfterCreate = within(pendingRegion()).queryByRole('status');
        if (statusAfterCreate) {
            expect(statusAfterCreate).not.toHaveTextContent(/cancel|removed/i);
        }

        const inviteForm = emailInput.closest('fieldset') as HTMLElement;
        expect(within(inviteForm).getByText(/invited|success|sent/i)).toBeInTheDocument();
    });
});

/**
 * FF-160 RED — davet e-postası çıkmadığında sahip ne görür, ne yapabilir?
 *
 * Bugün TeamPage bekleyen davet satırında yalnız e-posta/rol/durum ve iptal
 * düğmesi çiziyor. Taşıyıcı düştüğünde satır "pending" diyor ve başarılı bir
 * davetle BİREBİR aynı görünüyor; yeniden gönderme yolu ise hiç yok. Sahibin
 * tek hamlesi daveti iptal edip yeniden kurmak — yani ekibini kurabilmek için
 * önce onu bozmak (`docs/110` P0-06).
 *
 * Aşağıdaki iddiaların hepsi mevcut üretime karşı düşer: ne `delivery`
 * okunuyor, ne "Send again" düğmesi var, ne de `/resend` ucuna bir çağrı.
 */
describe('TeamPage — FF-160 davet teslimatı görünür ve yeniden gönderilebilir', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let pendingInvitations: unknown[];
    let resendDelivery: 'sent' | 'failed';

    const INVITATION_ID = 71;
    const RESEND_ENDPOINT = `${INVITATIONS_ENDPOINT}/${INVITATION_ID}/resend`;

    beforeEach(() => {
        resendDelivery = 'sent';
        pendingInvitations = [
            {
                id: INVITATION_ID,
                email: 'aday@example.test',
                role: 'editor',
                status: 'pending',
                delivery: 'failed',
            },
        ];
        document.cookie = 'XSRF-TOKEN=ff160-resend-test-token';

        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET')
                return jsonResponse(200, pendingInvitations);
            if (href === RESEND_ENDPOINT && method === 'POST') {
                pendingInvitations = [
                    {
                        id: INVITATION_ID,
                        email: 'aday@example.test',
                        role: 'editor',
                        status: 'pending',
                        delivery: resendDelivery,
                    },
                ];

                return jsonResponse(200, {
                    id: INVITATION_ID,
                    email: 'aday@example.test',
                    role: 'editor',
                    status: 'pending',
                    delivery: resendDelivery,
                });
            }

            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    function pendingRegion() {
        return screen.getByRole('region', { name: /pending invitation/i });
    }

    function rowFor(email: string): HTMLElement {
        const emailNode = within(pendingRegion()).getByText(email);
        const row = emailNode.closest('li');
        if (!row) {
            throw new Error(`expected a <li> row ancestor for ${email}`);
        }
        return row as HTMLElement;
    }

    it('says on the row that the email did not go out, instead of showing a silent pending row', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        expect(
            within(rowFor('aday@example.test')).getByText(/did not go out/i),
        ).toBeInTheDocument();
    });

    it('separates "never attempted" from "attempted and failed" instead of collapsing both into one sentence', async () => {
        pendingInvitations = [
            {
                id: INVITATION_ID,
                email: 'aday@example.test',
                role: 'editor',
                status: 'pending',
                delivery: 'unknown',
            },
        ];

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        expect(within(row).getByText(/cannot tell whether/i)).toBeInTheDocument();
        expect(within(row).queryByText(/did not go out/i)).not.toBeInTheDocument();
    });

    it('adds no delivery notice to a row whose email the provider accepted', async () => {
        pendingInvitations = [
            {
                id: INVITATION_ID,
                email: 'aday@example.test',
                role: 'editor',
                status: 'pending',
                delivery: 'sent',
            },
        ];

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        const row = rowFor('aday@example.test');
        expect(within(row).queryByText(/did not go out/i)).not.toBeInTheDocument();
        expect(within(row).queryByText(/cannot tell whether/i)).not.toBeInTheDocument();
    });

    it('bootstraps CSRF then POSTs the resend endpoint with same-origin credentials and the XSRF header', async () => {
        const user = userEvent.setup();

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        await user.click(
            within(rowFor('aday@example.test')).getByRole('button', { name: /send again/i }),
        );

        await waitFor(() => {
            expect(fetchSpy.mock.calls.some(([url]) => String(url) === RESEND_ENDPOINT)).toBe(true);
        });

        const csrfIndex = fetchSpy.mock.calls.findIndex(([url]) => String(url) === CSRF_COOKIE_URL);
        const resendIndex = fetchSpy.mock.calls.findIndex(
            ([url]) => String(url) === RESEND_ENDPOINT,
        );
        expect(csrfIndex).toBeGreaterThanOrEqual(0);
        expect(csrfIndex).toBeLessThan(resendIndex);

        const init = fetchSpy.mock.calls[resendIndex][1] as RequestInit;
        expect(init).toMatchObject({ method: 'POST', credentials: 'same-origin' });
        expect(new Headers(init.headers).get('X-XSRF-TOKEN')).toBe('ff160-resend-test-token');
    });

    it('never claims the inbox: a successful resend reports only that the provider accepted it', async () => {
        const user = userEvent.setup();

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        await user.click(
            within(rowFor('aday@example.test')).getByRole('button', { name: /send again/i }),
        );

        await waitFor(() => {
            expect(
                within(rowFor('aday@example.test')).getByText(/provider accepted/i),
            ).toBeInTheDocument();
        });

        // "Teslim edildi", "gelen kutusuna düştü" ya da tahmini bir süre
        // DEMEZ: buradan gelen kutusunu göremeyiz.
        const row = rowFor('aday@example.test');
        expect(
            within(row).queryByText(/delivered|inbox in|minute|second/i),
        ).not.toBeInTheDocument();
    });

    it('a resend whose email still did not go out is reported as refreshed-but-not-sent, never as success', async () => {
        const user = userEvent.setup();
        resendDelivery = 'failed';

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        await user.click(
            within(rowFor('aday@example.test')).getByRole('button', { name: /send again/i }),
        );

        await waitFor(() => {
            expect(
                within(rowFor('aday@example.test')).getByText(
                    /refreshed, but the email did not go out/i,
                ),
            ).toBeInTheDocument();
        });

        expect(
            within(rowFor('aday@example.test')).queryByText(/provider accepted/i),
        ).not.toBeInTheDocument();
        // Davet ayakta kalır: bağlantı gerçekten yenilendi.
        expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
    });

    it('reports a rejected resend as an error and leaves the invitation row in place', async () => {
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === CSRF_COOKIE_URL) return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (href === INVITATIONS_ENDPOINT && method === 'GET')
                return jsonResponse(200, pendingInvitations);
            if (href === RESEND_ENDPOINT && method === 'POST')
                return jsonResponse(429, { message: 'Too Many Requests' });
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        await user.click(
            within(rowFor('aday@example.test')).getByRole('button', { name: /send again/i }),
        );

        await waitFor(() => {
            expect(
                within(rowFor('aday@example.test')).getByText(/could not send it again/i),
            ).toBeInTheDocument();
        });

        expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
    });

    it('tells the owner that sending again replaces the link', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        expect(within(pendingRegion()).getByText(/replaces the link/i)).toBeInTheDocument();
    });

    it('uses no breakpoint-prefixed classes on the resend controls at a 320 fluid viewport', async () => {
        const user = userEvent.setup();
        const { container } = render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(pendingRegion()).getByText('aday@example.test')).toBeInTheDocument();
        });

        await user.click(
            within(rowFor('aday@example.test')).getByRole('button', { name: /send again/i }),
        );

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });
});
