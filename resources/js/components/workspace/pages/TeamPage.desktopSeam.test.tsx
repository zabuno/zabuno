import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen, waitFor } from '@testing-library/react';

import { TeamPage } from './TeamPage';
import type { TeamMember, TeamMemberRoleOutcome } from './team/TeamMemberList';

/**
 * TAKIM ÜYELERİ DİKİŞİ — masaüstü paketinin kendi çizimi (`docs/153` §4).
 *
 * Burada donan şey ÜYE LİSTESİNİN VERİSİ DEĞİL: onu `TeamPage.members`
 * testi zaten donduruyor. Bu dosyanın tek sorusu şu: sayfa, üyeler
 * bölgesini ÇİZİCİ olarak dışarı verebiliyor mu ve o çiziciye verilen şey
 * SUNUCUNUN cevabı mı?
 *
 * `MediaPage`'in `renderLibrary` dikişiyle aynı biçim ve aynı sebep: bayrak
 * değil çizici geçilir — `deviceClass === 'desktop'` diye bir dal, masaüstü
 * tablosunun kodunu telefon paketine yine indirirdi (`docs/153` §3).
 *
 * Ölçülen İKİ şey var:
 *
 * 1. Çizici sunucudan gelen ÜYELERİ ve yüklenmiş durumu alır. Kendi
 *    listesini kurabilseydi, masaüstü tablosu ile telefon listesi aynı
 *    çalışma alanı için farklı kişiler gösterebilirdi ve fark ancak sahibin
 *    "bir ekran o kişiyi listeliyor, öteki listelemiyor" dediği gün
 *    görünürdü.
 * 2. Çizicinin elindeki rol geri çağrısı, sayfanın GERÇEK mutasyon yoluna
 *    iner (`PUT .../members/{id}/role`). Yeni bir yol açılsaydı CSRF
 *    başlangıcı ve yetkili istek kurulumu masaüstünde sessizce eksik
 *    kalırdı.
 *
 * VARSAYILAN ÇİZİM burada sınanmaz: çizici geçilmediğinde bugünkü dokunmatik
 * liste çizilir ve onu `TeamPage.members` / `TeamPage.roles` testleri
 * tutuyor.
 */

const WORKSPACE_ID = 5;
const MEMBERS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/members`;
const INVITATIONS_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/team/invitations`;
const ROLE_ENDPOINT = `${MEMBERS_ENDPOINT}/2/role`;

const SERVER_MEMBERS: TeamMember[] = [
    { id: 1, name: 'Mehmet Usta', email: 'mehmet@example.test', role: 'owner' },
    { id: 2, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'editor' },
];

/**
 * Çizicinin ELİNE GEÇEN şeyin bu testin ihtiyaç duyduğu kadarı. Tam
 * sözleşme üretim tarafında yaşar; buradaki dar okuma, testi sözleşmenin
 * geri kalanı büyüdükçe kırılgan yapmamak içindir.
 */
type TeamMemberSurface = {
    status: 'loading' | 'error' | 'success';
    members: TeamMember[];
    onChangeRole: (memberId: number, role: string) => Promise<TeamMemberRoleOutcome>;
};

describe('TeamPage — masaüstü üye yüzeyi dikişi', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === '/sanctum/csrf-cookie') {
                return {
                    ok: true,
                    status: 204,
                    headers: new Headers(),
                    json: async () => ({}),
                } as Response;
            }

            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => SERVER_MEMBERS,
                } as Response;
            }

            if (href === INVITATIONS_ENDPOINT && method === 'GET') {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => [],
                } as Response;
            }

            if (href === ROLE_ENDPOINT && method === 'PUT') {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => ({}),
                } as Response;
            }

            throw new Error(`Unhandled fetch: ${method} ${href}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('enjekte edilen üye çizicisine sunucunun listesini verir ve rol geri çağrısı var olan mutasyon yoluna iner', async () => {
        const seen: TeamMemberSurface[] = [];
        const renderMembers = vi.fn((surface: TeamMemberSurface) => {
            seen.push(surface);

            return <div data-testid="team-members-desktop-seam" />;
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} renderMembers={renderMembers} />);

        // Yüklenmiş durum: çizici "loading" ile de çağrılabilir, sınanan şey
        // sunucunun cevabı geldiğinde onun ELİNDE olmasıdır.
        await waitFor(() => {
            expect(seen[seen.length - 1]?.status).toBe('success');
        });

        expect(screen.getByTestId('team-members-desktop-seam')).toBeInTheDocument();

        const surface = seen[seen.length - 1];

        expect(surface.members).toEqual(SERVER_MEMBERS);

        let outcome: TeamMemberRoleOutcome | undefined;

        await act(async () => {
            outcome = await surface.onChangeRole(2, 'manager');
        });

        expect(outcome).toBe('success');

        const roleCall = fetchSpy.mock.calls.find(
            ([calledUrl, init]) =>
                String(calledUrl) === ROLE_ENDPOINT &&
                ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'PUT',
        );

        expect(roleCall).toBeDefined();
        expect(JSON.parse(String((roleCall?.[1] as RequestInit).body))).toEqual({
            role: 'manager',
        });
    });
});
