import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { TeamPage } from './TeamPage';

/**
 * Davet rolleri — `docs/70`, düzen `docs/109` §6.4.
 *
 * Davet önceden HER ZAMAN `editor` gönderiyordu ve o rol hiçbir şeyi
 * düzenleyemiyordu: sahibi, adı "editör" olan ama salt okunur bir kullanıcı
 * yaratıyordu. Sahibin, faturaya dokunamayan ama günlük operasyonu
 * yürütebilen birini davet etmesinin yolu da yoktu.
 *
 * KONTROL DEĞİŞTİ, SÖZLEŞME DEĞİŞMEDİ. Bu dosya rolü bir `<select>` üzerinden
 * sürüyordu ve o eleman bir sözleşme SANILMIŞTI. Sınanan gerçek şeyler
 * dördü: seçilen rol gönderilir, varsayılan en dar roldür, sahiplik davet
 * seçeneği değildir, rolün ne yapabildiği yazılır. Dördü de kaynağın rol
 * HAPLARIYLA (`panel.dc.html`, "Takım") aynen geçerli — açılır liste, iki
 * seçeneği aynı anda göstermediği için seçimi zorlaştırıyordu.
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
            await user.click(
                screen.getByRole('radio', { name: role === 'manager' ? 'Manager' : 'Editor' }),
            );
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

        const group = await screen.findByRole('radiogroup', { name: 'Role' });
        const options = Array.from(group.querySelectorAll('[role="radio"]')).map(
            (option) => option.textContent,
        );

        expect(options).toEqual(['Editor', 'Manager', 'Kitchen']);
    });

    /**
     * KAYNAĞIN DÖRDÜNCÜ HAPI. `panel.dc.html` davet kartında üç hap çiziyor:
     * Editör · Yönetici · Mutfak. Mutfak bir önceki pakette bilerek
     * çizilmedi — depoda ne rolü ne izni vardı. Bu pakette ikisi de doğdu,
     * yani hap artık gerçek bir daveti temsil ediyor.
     *
     * Sıra en DAR olanı sona koymaz: haplar yetki genişliğine göre değil,
     * kaynağın sırasına göre durur ve varsayılan yine `editor`'dür.
     */
    it('Mutfak rolünü hap olarak sunar ve seçildiğinde onu gönderir', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await user.type(await screen.findByLabelText(/invite by email/i), 'hasan@example.com');
        await user.click(screen.getByRole('radio', { name: 'Kitchen' }));
        await user.click(screen.getByRole('button', { name: 'Invite' }));

        await waitFor(() => {
            const post = fetchSpy.mock.calls.find(
                ([calledUrl, init]) =>
                    String(calledUrl) === INVITATIONS_ENDPOINT &&
                    ((init as RequestInit | undefined)?.method ?? 'GET').toUpperCase() === 'POST',
            );

            expect(post).toBeDefined();
            expect(JSON.parse(String((post?.[1] as RequestInit).body)).role).toBe('kitchen');
        });
    });

    /**
     * Hapın altındaki cümle kaynağın kendi cümlesidir. "Mutfak" kelimesi tek
     * başına, sahibe fiyatların da açılıp açılmadığını söylemez.
     */
    it('Mutfak seçildiğinde ne yapabildiğini yazar', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await screen.findByRole('radiogroup', { name: 'Role' });
        await user.click(screen.getByRole('radio', { name: 'Kitchen' }));

        expect(
            screen.getByText(
                'Marks allergens and “sold out today”. Cannot change prices, publish or see anything else.',
            ),
        ).toBeInTheDocument();
    });

    /**
     * Sahiplik ayrı bir akıştır ve davet listesinde aranması boşunadır; kart
     * bunu hapların hemen altında söyler (kaynağın kendi cümlesi).
     */
    it('sahipliğin devredildiğini davet kartında yazar', async () => {
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        expect(
            await screen.findByText('Ownership is transferred, not given by invitation.'),
        ).toBeInTheDocument();
    });

    /**
     * "Editor" kelimesi tek başına yayınlayıp yayınlayamayacağını söylemez ve
     * sahibi yanlış kişiye yanlış yetkiyi verebilir.
     */
    it('rolün ne yapabildiğini hapların altında yazar', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        await screen.findByRole('radiogroup', { name: 'Role' });
        /*
            Sorgu TAM METİN: "Roller ne yapabilir?" kartı da yayınlama
            kısıtını anlatıyor (`docs/109` §6.4) ve gevşek bir `/cannot
            publish/i` ikisini birden yakalıyordu. Sınanan şey seçime GÖRE
            DEĞİŞEN yardım metnidir, kartın sabit cümlesi değil.
        */
        expect(
            screen.getByText(
                'Edits menu content. Cannot publish, change locations or see billing.',
            ),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('radio', { name: 'Manager' }));
        expect(screen.getByText(/cannot manage billing/i)).toBeInTheDocument();
    });

    /**
     * MUTFAK ROLÜ EKRANIN HER İKİ YERİNDE DE GERÇEKTİR (FF-138d).
     *
     * Rol sunucuda doğdu ve davet hapı olarak çizildi; ama sahibin onu
     * GÖRECEĞİ iki yer daha var: aşçının kendi satırı ve "Roller ne
     * yapabilir?" kartı. Satırda rolün adı yanlış yazsaydı — ya da kartta hiç
     * geçmeseydi — sahip, davet ettiği kişinin ne yapabildiğini ekranda
     * doğrulayamazdı.
     */
    it('Mutfak üyesi kendi rolüyle listelenir ve rol rehberinde anlatılır', async () => {
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            const method = (init?.method ?? 'GET').toUpperCase();

            if (href === MEMBERS_ENDPOINT && method === 'GET') {
                return {
                    ok: true,
                    status: 200,
                    headers: new Headers(),
                    json: async () => [
                        { id: 1, name: 'Ayşe Yılmaz', email: 'ayse@example.test', role: 'owner' },
                        { id: 4, name: 'Hasan Usta', email: 'hasan@example.test', role: 'kitchen' },
                    ],
                } as Response;
            }

            return {
                ok: true,
                status: 200,
                headers: new Headers(),
                json: async () => [],
            } as Response;
        });

        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const membersRegion = screen.getByRole('region', { name: /team members/i });

        await waitFor(() => {
            expect(within(membersRegion).getByText('Hasan Usta')).toBeInTheDocument();
        });

        // Satırın rol kutusu SUNUCUNUN değerini gösterir, varsayılanı değil.
        expect(within(membersRegion).getByLabelText('Role for Hasan Usta')).toHaveValue('kitchen');

        // Rehber, rolün sözleşmesini kaynağın kendi cümlesiyle yazar.
        expect(
            screen.getByText('Allergens and “sold out today”. Sees nothing else.'),
        ).toBeInTheDocument();
    });

    /** Seçili hap yalnız RENKLE değil, ARIA durumuyla da ayrışır. */
    it('seçili rol yardımcı teknolojiye de bildirilir', async () => {
        const user = userEvent.setup();
        render(<TeamPage workspaceId={WORKSPACE_ID} />);

        const editor = await screen.findByRole('radio', { name: 'Editor' });
        const manager = screen.getByRole('radio', { name: 'Manager' });

        expect(editor).toHaveAttribute('aria-checked', 'true');
        expect(manager).toHaveAttribute('aria-checked', 'false');

        await user.click(manager);

        expect(manager).toHaveAttribute('aria-checked', 'true');
        expect(editor).toHaveAttribute('aria-checked', 'false');
    });
});
