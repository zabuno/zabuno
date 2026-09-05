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
                        {
                            id: 2,
                            name: 'Mehmet Demir',
                            email: 'mehmet@example.test',
                            role: 'member',
                        },
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
        /*
            Sorgu GEVŞEKTEN KESİNE çevrildi: `/member/i` bölgenin artık
            GÖRÜNÜR olan "Team members" başlığıyla da eşleşiyor (`docs/109`
            §6.4 — kaynağın "Üyeler" kart başlığı). Sınanan sözleşme başlık
            değil, SATIRIN sunucudan gelen rol değerini yazmasıydı; sorgu
            şimdi tam olarak onu arıyor.
        */
        expect(within(membersRegion).getByText('member')).toBeInTheDocument();

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
            expect(within(membersRegion).getByRole('status')).toHaveTextContent(
                /error|failed|unable/i,
            );
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
                    json: async () => [
                        { id: 1, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
                    ],
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
                currentMembers = currentMembers.filter(
                    (member) => (member as { id: number }).id !== EDITOR_ID,
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
        expect(
            within(editorRow).getByRole('button', { name: /^(keep|cancel)/i }),
        ).toBeInTheDocument();
        expect(
            fetchSpy.mock.calls.find(
                ([, init]) => (init as RequestInit | undefined)?.method === 'DELETE',
            ),
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

        const csrfCall = fetchSpy.mock.calls.find(
            ([url]) => String(url) === '/sanctum/csrf-cookie',
        );
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
                String(url) === MEMBERS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
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
                String(url) === MEMBERS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET') === 'GET',
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
            if (href === MEMBERS_ENDPOINT && method === 'GET')
                return jsonResponse(200, currentMembers);
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
                currentMembers = currentMembers.filter(
                    (member) => (member as { id: number }).id !== EDITOR_ID,
                );
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

/**
 * FF-138d — ÇIKARMA ARTIK YALNIZ EDİTÖRE AİT DEĞİL.
 *
 * Ekran "Çıkar" düğmesini yalnız `editor` satırlarına çiziyordu. Sunucu ise
 * çıkarılabilir kümeyi `MembershipRole::removable()` üzerinden okuyor:
 * Editör, Yönetici ve Mutfak. Aradaki fark sahibin ekranında bir ÇIKIŞSIZLIK
 * olarak görünüyordu — işten ayrılan bir yöneticiyi ya da aşçıyı ekipten
 * çıkarmanın hiçbir yolu yoktu, çünkü satırda düğme yoktu.
 *
 * Kümenin dışında kalan TEK rol `owner`'dır ve bu paket onu KORUR: sahiplik
 * silinmez, DEVREDİLİR — silinseydi çalışma alanı sahipsiz kalır ve kimse
 * onaramazdı. (Eski `member` rolü de bir süre dışarıdaydı; FF-142 onu
 * çıkarılabilir kümeye aldı, çünkü dışarıda kalması bir karar değil, kümenin
 * davet listesinden türetilmesinin yan etkisiydi.)
 */
describe('TeamPage — FF-138d çıkarılabilir roller ve dürüst ret', () => {
    const OWNER_ID = 1;
    const EDITOR_ID = 2;
    const MANAGER_ID = 3;
    const KITCHEN_ID = 4;
    const LEGACY_ID = 5;

    let fetchSpy: ReturnType<typeof vi.fn>;
    let currentMembers: Array<{ id: number; name: string; email: string; role: string }>;
    let deleteStatus: number;

    beforeEach(() => {
        currentMembers = [
            { id: OWNER_ID, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
            { id: EDITOR_ID, name: 'Mehmet Demir', email: 'mehmet@example.test', role: 'editor' },
            { id: MANAGER_ID, name: 'Zeynep Ak', email: 'zeynep@example.test', role: 'manager' },
            { id: KITCHEN_ID, name: 'Hasan Usta', email: 'hasan@example.test', role: 'kitchen' },
            { id: LEGACY_ID, name: 'Elif Kaya', email: 'elif@example.test', role: 'member' },
        ];
        deleteStatus = 204;
        document.cookie = 'XSRF-TOKEN=ff-138d-remove-test-token';

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
            if (href.startsWith(`${MEMBERS_ENDPOINT}/`) && method === 'DELETE') {
                const membershipId = Number(href.slice(`${MEMBERS_ENDPOINT}/`.length));

                if (deleteStatus === 204) {
                    currentMembers = currentMembers.filter((member) => member.id !== membershipId);

                    return jsonResponse(204, null);
                }

                /*
                    Sunucunun KENDİ gövdesi. Ekranda olduğu gibi görünmemeli:
                    "Forbidden." bir geliştirici cümlesidir ve sahibe ne
                    yapacağını söylemez.
                */
                return jsonResponse(deleteStatus, {
                    message: deleteStatus === 403 ? 'Forbidden.' : 'Not Found.',
                });
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

    async function waitForRows() {
        await waitFor(() => {
            expect(within(membersRegion()).getByText('Zeynep Ak')).toBeInTheDocument();
        });
    }

    it('Yönetici satırında da çıkarma düğmesi çizilir', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        expect(
            within(rowFor('Zeynep Ak')).getByRole('button', { name: /remove/i }),
        ).toBeInTheDocument();
    });

    it('Mutfak satırında da çıkarma düğmesi çizilir', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        expect(
            within(rowFor('Hasan Usta')).getByRole('button', { name: /remove/i }),
        ).toBeInTheDocument();
    });

    /**
     * Sahiplik DEVREDİLİR, silinmez: sahibi silinen bir çalışma alanını kimse
     * onaramaz. Kaldırılabilir küme büyürken bu satırın korunması, kümenin
     * "davet edilebilir roller"den türediğinin de kanıtıdır.
     */
    it('Sahip satırı çıkarılabilir gösterilmez', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        expect(
            within(rowFor('Ayşe Yılmaz')).queryByRole('button', { name: /remove/i }),
        ).not.toBeInTheDocument();
    });

    /**
     * FF-142 — ESKİ SALT OKUNUR ÜYELİK DE ÇIKARILABİLİR.
     *
     * Bu satır bir zamanlar bunun TERSİNİ söylüyordu ve doğruyu anlatıyordu:
     * sunucu `member` satırını silmiyordu, ekran da vaat etmiyordu. Ama
     * sunucunun o davranışı bir karar değil, kaldırma kümesinin "davet
     * edilebilir roller"den türetilmesinin yan etkisiydi. Sonuç, sahibin
     * ekranında çıkışsızlıktı: eski bir kayıttan gelen kişiyi ekipten
     * çıkarmanın hiçbir yolu yoktu. Sunucu artık çıkarıyor; ekran da
     * çizmeli — yapılabilen iş gizlenmez.
     */
    it('eski salt okunur üyelik de çıkarılabilir gösterilir', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        expect(
            within(rowFor('Elif Kaya')).getByRole('button', { name: /remove/i }),
        ).toBeInTheDocument();
    });

    it('Yönetici çıkarıldığında o üyeliğin adresine DELETE gider ve satır listeden düşer', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        const row = rowFor('Zeynep Ak');
        await user.click(within(row).getByRole('button', { name: /remove/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(membersRegion()).queryByText('Zeynep Ak')).not.toBeInTheDocument();
        });

        expect(
            fetchSpy.mock.calls.some(
                ([url, init]) =>
                    String(url) === `${MEMBERS_ENDPOINT}/${MANAGER_ID}` &&
                    (init as RequestInit | undefined)?.method === 'DELETE',
            ),
        ).toBe(true);
        expect(within(membersRegion()).getByText('Hasan Usta')).toBeInTheDocument();
    });

    /**
     * SUNUCU HAYIR DERSE KULLANICI GÖRÜR.
     *
     * 403 "bu işi yapan sen değilsin" der ve çıkış yolu bellidir: sahibinden
     * istemek. Genel "tekrar deneyin" cümlesi burada YANLIŞTIR — kaç kez
     * denerse denesin aynı cevabı alır ve nedenini hiç öğrenemez.
     */
    it('sunucu 403 derse satır kalır ve neden çıkarılamadığı yazar', async () => {
        const user = userEvent.setup();
        deleteStatus = 403;

        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        const row = rowFor('Zeynep Ak');
        await user.click(within(row).getByRole('button', { name: /remove/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(row).getByRole('status')).toHaveTextContent(
                /only the workspace owner can remove/i,
            );
        });

        expect(within(membersRegion()).getByText('Zeynep Ak')).toBeInTheDocument();
        // HAM GÖVDE SIZMAZ: ne sunucunun cümlesi ne de JSON'un kendisi.
        expect(membersRegion().textContent ?? '').not.toMatch(/Forbidden\.|"message"|\{/);
        // Boşuna tekrar sunulmaz: aynı istek aynı cevabı alır.
        expect(within(row).queryByRole('button', { name: /^retry$/i })).not.toBeInTheDocument();
    });

    /**
     * 404 farklı bir gerçeği anlatır: o üyelik artık orada değil. "Tekrar
     * deneyin" demek, olmayan bir satırı silmeye çağırmak olurdu.
     */
    it('sunucu 404 derse üyeliğin artık listede olmadığı yazar', async () => {
        const user = userEvent.setup();
        deleteStatus = 404;

        render(<TeamPage workspaceId={WORKSPACE_ID} />);
        await waitForRows();

        const row = rowFor('Hasan Usta');
        await user.click(within(row).getByRole('button', { name: /remove/i }));
        await user.click(within(row).getByRole('button', { name: /^confirm/i }));

        await waitFor(() => {
            expect(within(row).getByRole('status')).toHaveTextContent(
                /no longer in the team list/i,
            );
        });

        expect(membersRegion().textContent ?? '').not.toMatch(/Not Found\.|"message"|\{/);
    });

    /**
     * YAPILAMAYAN İŞ ÇİZİLMEZ (`docs/98` FF-74).
     *
     * `workspace.manage` iznini Yönetici de taşır ve Takım ekranı ona da
     * açıktır — ama ekipten çıkarma sahibin kararıdır ve uç nokta yöneticiye
     * 403 döner. Düğmeyi çizip tıklandığında reddetmek, yöneticiye olmayan
     * bir yetkiyi vaat etmektir.
     */
    it('sahibi olmayan bir kullanıcıya çıkarma düğmesi hiç çizilmez', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} viewerRole="manager" />);
        await waitForRows();

        expect(within(membersRegion()).queryAllByRole('button', { name: /remove/i })).toHaveLength(
            0,
        );
        expect(
            within(membersRegion()).queryAllByRole('button', { name: /transfer ownership/i }),
        ).toHaveLength(0);
    });

    it('sahibe çıkarma düğmesi çizilir', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} viewerRole="owner" />);
        await waitForRows();

        expect(
            within(rowFor('Zeynep Ak')).getByRole('button', { name: /remove/i }),
        ).toBeInTheDocument();
    });
});
