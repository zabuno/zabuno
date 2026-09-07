import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * KULLANICI GÖRÜNÜRLÜĞÜ EKRANI — `docs/122` §3 boşluk 2, dalga Y2.
 *
 * Destek çağrısı hep aynı cümleyle başlar: "Giremiyorum." Bu ekran o
 * cümlenin bakılacak yeridir: kişi kim, hangi çalışma alanlarında, hangi
 * rolle, adresi doğrulanmış mı, açık oturumu var mı.
 *
 * NE ÇİZİLMEDİĞİ EN AZ O KADAR ÖNEMLİ:
 *  - Parola sıfırlama/değiştirme YOK. İstenen görünürlüktü, müdahale değil;
 *    bir destek aracının ilk sürümündeki yazma fiili, geri alınamayan ilk
 *    kazayı da getirir.
 *  - KİLİT SÜTUNU YOK. Bu üründe bugün bir kullanıcı kilidi kavramı yok;
 *    "kilitli değil" rozeti, olmayan bir denetimi yapılmış gibi gösterirdi
 *    (`docs/109` §8.4).
 *  - Oturum sayısı ölçülemediğinde hücre BOŞ kalır, "0" yazılmaz: ölçülmemiş
 *    olmak yokluk değildir (`docs/109` §8.3).
 */

const PAYLOAD = {
    users: [
        {
            id: 5,
            name: 'Ayşe Yılmaz',
            email: 'ayse@ornek.test',
            emailVerifiedAt: '2026-01-05 10:00:00',
            createdAt: '2026-01-04 09:00:00',
            platformRoles: ['super_admin'],
            memberships: [
                {
                    workspaceId: 7,
                    workspaceName: 'Kebapçı Ali',
                    workspaceSlug: 'kebapci-ali',
                    workspaceState: 'active',
                    role: 'owner',
                },
            ],
            sessions: { known: true, active: 2, lastActivity: 1800000500 },
        },
        {
            id: 9,
            name: 'Kerem Aksoy',
            email: 'kerem@ornek.test',
            emailVerifiedAt: null,
            createdAt: '2026-02-01 09:00:00',
            platformRoles: [],
            memberships: [],
            sessions: { known: false },
        },
    ],
    truncated: false,
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function stubFetch(status = 200, body: unknown = PAYLOAD) {
    const mock = vi.fn(async () => jsonResponse(status, body));
    vi.stubGlobal('fetch', mock);

    return mock;
}

async function importPage() {
    return import('./PlatformUsersPage') as unknown as Promise<{
        PlatformUsersPage: React.ComponentType;
    }>;
}

function rowFor(table: HTMLElement, name: string): HTMLElement {
    const row = within(table)
        .getAllByRole('row')
        .find((candidate) => within(candidate).queryByText(name) !== null);

    if (row === undefined) throw new Error(`"${name}" satırı yok.`);

    return row;
}

describe('PlatformUsersPage', () => {
    beforeEach(() => {
        stubFetch();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('names every workspace a person belongs to, with the role', async () => {
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);

        const table = await screen.findByRole('region', { name: 'Users' });
        const row = rowFor(table, 'Ayşe Yılmaz');

        expect(within(row).getByText('ayse@ornek.test')).toBeInTheDocument();
        expect(within(row).getByText(/Kebapçı Ali/)).toBeInTheDocument();
        expect(within(row).getByText(/owner/)).toBeInTheDocument();
        expect(within(row).getByText('super_admin')).toBeInTheDocument();

        expect(fetch).toHaveBeenCalledWith('/api/admin/users?query=', expect.anything());
    });

    it('says an address is not verified and says a person belongs to nothing', async () => {
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);

        const table = await screen.findByRole('region', { name: 'Users' });
        const row = rowFor(table, 'Kerem Aksoy');

        expect(within(row).getByText('Not verified')).toBeInTheDocument();
        expect(within(row).getByText('Belongs to no workspace')).toBeInTheDocument();
    });

    it('counts open sessions where they are measured and leaves the cell empty where they are not', async () => {
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);

        const table = await screen.findByRole('region', { name: 'Users' });

        expect(within(rowFor(table, 'Ayşe Yılmaz')).getByText('2')).toBeInTheDocument();
        // Ölçülemeyen hücreye "0" yazmak, destek görevlisini "kullanıcı zaten
        // giriş yapmamış" diye yanlış yola sokardı.
        expect(within(rowFor(table, 'Kerem Aksoy')).queryByText('0')).not.toBeInTheDocument();
        expect(
            within(table).getByText(/An empty cell means “not measured here”/),
        ).toBeInTheDocument();
    });

    it('searches by name or address through the endpoint, not in the browser', async () => {
        const mock = stubFetch();
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);
        await screen.findByRole('region', { name: 'Users' });

        await userEvent.type(screen.getByLabelText('Search by name or email address'), 'kerem');
        await userEvent.click(screen.getByRole('button', { name: 'Search' }));

        expect(mock).toHaveBeenCalledWith('/api/admin/users?query=kerem', expect.anything());
    });

    it('says it could not read rather than drawing an empty directory', async () => {
        stubFetch(500, {});
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);

        expect(await screen.findByRole('alert')).toHaveTextContent('We could not load users.');
        expect(screen.queryByRole('region', { name: 'Users' })).not.toBeInTheDocument();
    });

    it('offers no password, lock or role control at all', async () => {
        const { PlatformUsersPage } = await importPage();

        render(<PlatformUsersPage />);
        await screen.findByRole('region', { name: 'Users' });

        const forbidden = /password|lock|unlock|suspend|delete|impersonate|sign in as|role change/i;

        for (const control of [
            ...screen.queryAllByRole('button'),
            ...screen.queryAllByRole('link'),
        ]) {
            expect(control).not.toHaveAccessibleName(forbidden);
        }

        expect(screen.queryByRole('columnheader', { name: /lock/i })).not.toBeInTheDocument();
    });
});
