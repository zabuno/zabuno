import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamPage } from './TeamPage';

/**
 * Davet rolleri — `docs/70`.
 *
 * Davet önceden HER ZAMAN `editor` gönderiyordu ve o rol hiçbir şeyi
 * düzenleyemiyordu: sahibi, adı "editör" olan ama salt okunur bir kullanıcı
 * yaratıyordu. Sahibin, faturaya dokunamayan ama günlük operasyonu
 * yürütebilen birini davet etmesinin yolu da yoktu.
 */
const WORKSPACE_ID = 5;
const MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;

describe('TeamPage — davet rolleri', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === MEMBERS_ENDPOINT || (href === INVITATIONS_ENDPOINT && method === 'GET')) {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => [],
                } as Response;
            }

            return {
                ok: true,
                status: 201,
                headers: new Headers(),
                json: async () => ({ id: 1 }),
            } as Response;
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    async function invite(role?: string) {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await user.type(await screen.findByLabelText(/invite by email/i), 'yeni@example.com');

        if (role !== undefined) {
            await user.selectOptions(screen.getByLabelText('Role'), role);
        }

        await user.click(screen.getByRole('button', { name: 'Invite' }));

        await waitFor(() => {
            expect(
                fetchSpy.mock.calls.some(
                    ([calledUrl, init]) =>
                        String(calledUrl) === INVITATIONS_ENDPOINT &&
                        ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase() ===
                            'POST',
                ),
            ).toBe(true);
        });

        const post = fetchSpy.mock.calls.find(
            ([calledUrl, init]) =>
                String(calledUrl) === INVITATIONS_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
        );

        return JSON.parse(String((post?.[1] as RequestInit).body)) as {
            email: string;
            role: string;
        };
    }

    it('seçilen rolü gönderir', async () => {
        expect((await invite('manager')).role).toBe('manager');
    });

    it('varsayılan olarak en dar rolü gönderir', async () => {
        /*
            Varsayılan EDITOR'dur, manager değil. Bir sahibi acele ederse en az
            yetkiyi vermiş olur; tersi, en geniş yetkiyi kazara dağıtmak
            olurdu.
        */
        expect((await invite()).role).toBe('editor');
    });

    /**
     * Sahiplik DAVETLE verilmez, devredilir — ayrı bir akışı ve ayrı bir
     * sonucu vardır. Listede görünmesi, olmayan bir yolu varmış gibi
     * gösterirdi.
     */
    it('sahiplik rolünü davet seçeneği olarak sunmaz', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const select = await screen.findByLabelText('Role');
        const options = Array.from(select.querySelectorAll('option')).map((option) => option.value);

        expect(options).toEqual(['editor', 'manager']);
    });

    /**
     * "Editor" kelimesi tek başına yayınlayıp yayınlayamayacağını söylemez ve
     * sahibi yanlış kişiye yanlış yetkiyi verebilir.
     */
    it('rolün ne yapabildiğini alanın altında yazar', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const select = await screen.findByLabelText('Role');
        expect(screen.getByText(/cannot publish/i)).toBeInTheDocument();

        await user.selectOptions(select, 'manager');
        expect(screen.getByText(/cannot manage billing/i)).toBeInTheDocument();
    });
});
