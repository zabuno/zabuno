import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamPage } from './TeamPage';

/**
 * TEAM_OWNERSHIP_TRANSFER_FRONTEND_RED
 *
 * RED suite for S1-WP02E-OWNERSHIP-TRANSFER. TeamMemberList
 * (resources/js/components/workspace/pages/team/TeamMemberList.tsx) today
 * renders member rows with, at most, a Remove control gated to
 * role==='editor'; there is no "Transfer ownership" control on any row, no
 * ConfirmDialog wiring, and TeamPage performs no POST to
 * /api/workspaces/{id}/team/members/{member}/transfer-ownership. Every
 * assertion below must fail against current production. No production,
 * i18n, Storybook, backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 5;
const MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;

const OWNER_ID = 1;
const EDITOR_ID = 2;
const MEMBER_ID = 3;
const MANAGER_ID = 4;
const KITCHEN_ID = 5;

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

describe('TeamPage — S1-WP02E ownership transfer (TEAM_OWNERSHIP_TRANSFER_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;
    let currentMembers: Array<{ id: number; name: string; email: string; role: string }>;

    beforeEach(() => {
        currentMembers = [
            { id: OWNER_ID, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
            { id: EDITOR_ID, name: 'Mehmet Demir', email: 'mehmet@example.test', role: 'editor' },
            { id: MEMBER_ID, name: 'Elif Kaya', email: 'elif@example.test', role: 'member' },
            { id: MANAGER_ID, name: 'Can Öztürk', email: 'can@example.test', role: 'manager' },
            {
                id: KITCHEN_ID,
                name: 'Zeynep Arslan',
                email: 'zeynep@example.test',
                role: 'kitchen',
            },
        ];
        document.cookie = 'XSRF-TOKEN=s1-wp02e-transfer-test-token';

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
            if (
                href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                method === 'POST'
            ) {
                currentMembers = currentMembers.map((member) => {
                    if (member.id === EDITOR_ID) {
                        return { ...member, role: 'owner' };
                    }
                    if (member.id === OWNER_ID) {
                        return { ...member, role: 'editor' };
                    }

                    return member;
                });

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

    /*
        DÜĞME, SUNUCUNUN KABUL ETTİĞİ HER SATIRDA ÇİZİLİR — DAHA AZINDA DA
        DEĞİL, DAHA FAZLASINDA DA (FF-144).

        İki yön de ayrı birer hata: sunucunun reddettiği bir satıra düğme
        çizmek, sahibi tıklayıp reddedilmeye gönderir; sunucunun kabul ettiği
        bir satırda düğmeyi gizlemek ise yapılabilen bir işi yok gibi
        gösterir. Yönetici satırı uzun süre ikinci hatanın örneğiydi.
    */
    it('exposes a Transfer ownership control on every server-eligible row (Editor, Manager) and on no other row', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        expect(
            within(editorRow).getByRole('button', { name: /transfer ownership/i }),
        ).toBeInTheDocument();

        const managerRow = rowFor('Can Öztürk');
        expect(
            within(managerRow).getByRole('button', { name: /transfer ownership/i }),
        ).toBeInTheDocument();

        const ownerRow = rowFor('Ayşe Yılmaz');
        expect(
            within(ownerRow).queryByRole('button', { name: /transfer ownership/i }),
        ).not.toBeInTheDocument();

        const memberRow = rowFor('Elif Kaya');
        expect(
            within(memberRow).queryByRole('button', { name: /transfer ownership/i }),
        ).not.toBeInTheDocument();

        // Mutfak sunucuda da reddedilir; satırda düğme olmaması bir görsel
        // tercih değil, sunucu sınırının ekrandaki karşılığıdır.
        const kitchenRow = rowFor('Zeynep Arslan');
        expect(
            within(kitchenRow).queryByRole('button', { name: /transfer ownership/i }),
        ).not.toBeInTheDocument();
    });

    it('clicking Transfer ownership opens the accessible ConfirmDialog with explicit cancel/confirm, and issues no POST before confirm', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByRole('button', { name: /^confirm/i })).toBeInTheDocument();
        expect(within(dialog).getByRole('button', { name: /^cancel/i })).toBeInTheDocument();

        expect(
            fetchSpy.mock.calls.find(
                ([, init]) => (init as RequestInit | undefined)?.method === 'POST',
            ),
        ).toBeUndefined();
    });

    it('cancelling the dialog closes it and issues no POST', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: /^cancel/i }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        expect(
            fetchSpy.mock.calls.find(
                ([, init]) => (init as RequestInit | undefined)?.method === 'POST',
            ),
        ).toBeUndefined();
    });

    it('confirming the dialog bootstraps CSRF, POSTs to the exact transfer-ownership endpoint with same-origin credentials, JSON Accept and X-XSRF-TOKEN, and disables/aria-busies the confirm control while the request is in flight', async () => {
        const user = userEvent.setup();
        let releasePost: ((response: Response) => void) | undefined;
        const deferredPost = new Promise<Response>((resolve) => {
            releasePost = resolve;
        });

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === '/sanctum/csrf-cookie') return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET')
                return jsonResponse(200, currentMembers);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (
                href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                method === 'POST'
            ) {
                return deferredPost;
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        const confirmButton = within(dialog).getByRole('button', { name: /^confirm/i });
        await user.click(confirmButton);

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    String(url) === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                    (init as RequestInit | undefined)?.method === 'POST',
            );
            expect(postCall).toBeDefined();
        });

        // The POST promise is still pending here — the confirm control must
        // observably reflect the in-flight request before it resolves.
        await waitFor(() => {
            const isDisabled = confirmButton.hasAttribute('disabled');
            const isAriaBusy = confirmButton.getAttribute('aria-busy') === 'true';
            expect(isDisabled || isAriaBusy).toBe(true);
        });

        const postCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                String(url) === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                (init as RequestInit | undefined)?.method === 'POST',
        );
        const postInit = postCall?.[1] as RequestInit;
        const postHeaders = new Headers(postInit.headers);

        expect(postInit.credentials).toBe('same-origin');
        expect(postHeaders.get('Accept')).toBe('application/json');
        expect(postHeaders.get('X-XSRF-TOKEN')).toBe(readCookie('XSRF-TOKEN'));

        const csrfCall = fetchSpy.mock.calls.find(
            ([url]) => String(url) === '/sanctum/csrf-cookie',
        );
        expect(csrfCall).toBeDefined();

        releasePost?.(jsonResponse(204, null));

        await waitFor(() => {
            const isDisabled = confirmButton.hasAttribute('disabled');
            const isAriaBusy = confirmButton.getAttribute('aria-busy') === 'true';
            expect(isDisabled || isAriaBusy).toBe(false);
        });
    });

    it('after a successful transfer, authoritatively refetches members and shows the new owner/editor role assignment', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const getCallsBefore = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === MEMBERS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        await waitFor(() => {
            const refreshedEditorRow = rowFor('Mehmet Demir');
            expect(within(refreshedEditorRow).getByText(/owner/i)).toBeInTheDocument();
        });

        const refreshedOwnerRow = rowFor('Ayşe Yılmaz');
        expect(within(refreshedOwnerRow).getByText(/editor/i)).toBeInTheDocument();

        const getCallsAfter = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                String(url) === MEMBERS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
        ).length;
        expect(getCallsAfter).toBeGreaterThan(getCallsBefore);
    });

    it('on a plain transfer POST failure (500), shows a row/dialog error and re-enables confirm without a fabricated success claim', async () => {
        const user = userEvent.setup();
        let postCalls = 0;

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === '/sanctum/csrf-cookie') return jsonResponse(204, null);
            if (href === MEMBERS_ENDPOINT && method === 'GET')
                return jsonResponse(200, currentMembers);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (
                href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                method === 'POST'
            ) {
                postCalls += 1;

                return jsonResponse(500, { message: 'Failed' });
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(dialog).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });

        expect(postCalls).toBe(1);
        expect(within(dialog).getByRole('button', { name: /^confirm/i })).toBeEnabled();

        await user.click(within(dialog).getByRole('button', { name: /^cancel/i }));

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        const editorRowAfterFailure = rowFor('Mehmet Demir');
        expect(within(editorRowAfterFailure).getByText(/^editor$/i)).toBeInTheDocument();
        expect(within(editorRowAfterFailure).queryByText(/^owner$/i)).not.toBeInTheDocument();
    });

    it('TEAM_OWNERSHIP_TRANSFER_REFETCH_LIFECYCLE_RED: when the transfer POST succeeds (204) but the first authoritative members refetch fails or returns stale roles, keeps a safe explicit Retry that re-issues only the GET (never a second POST); after the retry GET returns the transferred roles, success is shown', async () => {
        const user = userEvent.setup();
        let postCalls = 0;
        let getCallsAfterPost = 0;
        let nextGetOutcome: 'fail' | 'stale' | 'authoritative' = 'fail';

        const staleMembers = currentMembers;
        const authoritativeMembers = currentMembers.map((member) => {
            if (member.id === EDITOR_ID) {
                return { ...member, role: 'owner' };
            }
            if (member.id === OWNER_ID) {
                return { ...member, role: 'editor' };
            }

            return member;
        });

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();
            if (href === '/sanctum/csrf-cookie') return jsonResponse(204, null);
            if (href === INVITATIONS_ENDPOINT && method === 'GET') return jsonResponse(200, []);
            if (
                href === `${MEMBERS_ENDPOINT}/${EDITOR_ID}/transfer-ownership` &&
                method === 'POST'
            ) {
                postCalls += 1;
                nextGetOutcome = 'fail';

                return jsonResponse(204, null);
            }
            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                if (postCalls === 0) {
                    return jsonResponse(200, staleMembers);
                }

                getCallsAfterPost += 1;

                if (nextGetOutcome === 'fail') {
                    nextGetOutcome = 'stale';

                    return jsonResponse(500, { message: 'Refetch failed' });
                }
                if (nextGetOutcome === 'stale') {
                    nextGetOutcome = 'authoritative';

                    return jsonResponse(200, staleMembers);
                }

                return jsonResponse(200, authoritativeMembers);
            }
            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        await user.click(within(editorRow).getByRole('button', { name: /transfer ownership/i }));

        const dialog = await screen.findByRole('dialog');
        await user.click(within(dialog).getByRole('button', { name: /^confirm/i }));

        // First refetch fails outright: the mutation is already committed
        // server-side, so this must surface a recoverable Retry, not a
        // fabricated success and not a replayed POST.
        await waitFor(() => {
            expect(within(dialog).getByRole('status')).toHaveTextContent(/error|failed|unable/i);
        });
        expect(postCalls).toBe(1);

        const retryButton = within(dialog).getByRole('button', { name: /^retry$/i });
        await user.click(retryButton);

        // Second refetch (via retry) returns stale roles — still not a
        // success, and still must not have replayed the POST.
        await waitFor(() => {
            expect(getCallsAfterPost).toBe(2);
        });
        expect(postCalls).toBe(1);
        expect(within(dialog).queryByText(/success/i)).not.toBeInTheDocument();

        const retryButtonAgain = within(dialog).getByRole('button', { name: /^retry$/i });
        await user.click(retryButtonAgain);

        // Third refetch (via retry) finally returns the authoritative
        // transferred roles: success is shown, and the POST was sent exactly
        // once across the whole retry lifecycle.
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        });

        await waitFor(() => {
            const refreshedEditorRow = rowFor('Mehmet Demir');
            expect(within(refreshedEditorRow).getByText(/owner/i)).toBeInTheDocument();
        });

        const refreshedOwnerRow = rowFor('Ayşe Yılmaz');
        expect(within(refreshedOwnerRow).getByText(/editor/i)).toBeInTheDocument();

        expect(postCalls).toBe(1);
        expect(getCallsAfterPost).toBe(3);
    });

    it('supports keyboard-only interaction: Tab to the Transfer ownership control, Enter opens the dialog, and focus lands inside it', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await waitFor(() => {
            expect(within(membersRegion()).getByText('Mehmet Demir')).toBeInTheDocument();
        });

        const editorRow = rowFor('Mehmet Demir');
        const transferButton = within(editorRow).getByRole('button', {
            name: /transfer ownership/i,
        });
        transferButton.focus();
        expect(transferButton).toHaveFocus();

        await user.keyboard('{Enter}');

        const dialog = await screen.findByRole('dialog');
        await waitFor(() => {
            expect(dialog.contains(document.activeElement)).toBe(true);
        });
    });
});
