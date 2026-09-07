import type React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * KİRACI AYRINTISI EKRANI — `docs/122` §3 boşluk 1, dalga Y2.
 *
 * Ölçülen durum: liste vardı, satıra tıklayınca hiçbir şey yoktu. Bu ekran
 * tek yerde dört soruyu cevaplar: kaç şube, hangi menüler, abonelik ne
 * durumda, son ne oldu.
 *
 * Testlerin bir kısmı ekranın NE ÇİZMEDİĞİNİ dondurur. En önemlisi: kiracı
 * olarak oturum açan bir düğme YOK. `docs/122` §5 impersonation'ı en
 * tehlikeli süperadmin yeteneği sayar, Y7'ye bırakır ve zor olmasını şart
 * koşar; buraya konacak kolay bir düğme, o kararı sessizce iptal ederdi.
 */

const WORKSPACES = [{ id: 7, name: 'Kebapçı Ali', slug: 'kebapci-ali', state: 'active' }];

const DETAIL = {
    workspace: {
        id: 7,
        name: 'Kebapçı Ali',
        slug: 'kebapci-ali',
        state: 'active',
        createdAt: '2026-01-04 09:00:00',
    },
    brand: { name: 'Ali Usta', slug: 'ali-usta', locale: 'tr', currency: 'TRY' },
    subscription: {
        state: 'active',
        plan_id: 3,
        plan_code: 'growth',
        plan_name: 'Growth',
        plan_version: 2,
        ends_at: '2026-12-31 00:00:00',
    },
    usage: { locations: 2, menus: 1, products: 34, mediaAssets: 12, members: 3 },
    locations: [
        {
            id: 11,
            displayName: 'Kadıköy',
            city: 'İstanbul',
            countryCode: 'TR',
            timezone: 'Europe/Istanbul',
        },
    ],
    menus: [{ id: 21, name: 'Ana Menü', state: 'draft', locationId: 11, locationName: 'Kadıköy' }],
    members: [
        {
            userId: 5,
            name: 'Ayşe Yılmaz',
            email: 'ayse@ornek.test',
            role: 'owner',
            since: '2026-01-04 09:10:00',
        },
    ],
    listsTruncated: { locations: false, menus: false, members: false },
    recentEvents: [
        {
            source: 'publication',
            action: 'published',
            subject: 'Kadıköy · v4',
            actor: 'ayse@ornek.test',
            at: '2026-09-01 12:00:00',
        },
    ],
};

function jsonResponse(status: number, body: unknown): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(),
        json: async () => body,
    } as Response;
}

function stubFetch(detailStatus = 200, detailBody: unknown = DETAIL): void {
    vi.stubGlobal(
        'fetch',
        vi.fn(async (url: string) => {
            const target = String(url);

            if (target.startsWith('/api/admin/workspaces?')) {
                return jsonResponse(200, WORKSPACES);
            }

            if (target === '/api/admin/workspaces/7') {
                return jsonResponse(detailStatus, detailBody);
            }

            throw new Error(`Beklenmeyen istek: ${target}`);
        }),
    );
}

async function importPage() {
    return import('./TenantDetailPage') as unknown as Promise<{
        TenantDetailPage: React.ComponentType;
    }>;
}

async function openWorkspace() {
    const { TenantDetailPage } = await importPage();
    render(<TenantDetailPage />);

    const option = await screen.findByRole('option', { name: /Kebapçı Ali/ });
    await userEvent.click(option);
}

describe('TenantDetailPage', () => {
    beforeEach(() => {
        stubFetch();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('asks nothing until a workspace is picked', async () => {
        const { TenantDetailPage } = await importPage();

        render(<TenantDetailPage />);

        await screen.findByRole('option', { name: /Kebapçı Ali/ });

        expect(fetch).not.toHaveBeenCalledWith('/api/admin/workspaces/7', expect.anything());
    });

    it('answers branches, menus, usage, subscription, team and last events in one place', async () => {
        await openWorkspace();

        const usage = await screen.findByRole('region', { name: 'Usage' });
        expect(within(usage).getByText('34')).toBeInTheDocument();
        expect(within(usage).getByText('12')).toBeInTheDocument();

        const branches = screen.getByRole('region', { name: 'Branches' });
        expect(within(branches).getByText('Kadıköy')).toBeInTheDocument();
        expect(within(branches).getByText('İstanbul')).toBeInTheDocument();

        // Menünün ŞUBESİ menünün yanında: üç şubeli bir işletmede "Ana Menü"
        // tek başına hangi menünün değiştiğini söylemez.
        const menus = screen.getByRole('region', { name: 'Menus' });
        expect(within(menus).getByText('Ana Menü')).toBeInTheDocument();
        expect(within(menus).getByText('Kadıköy')).toBeInTheDocument();

        const subscription = screen.getByRole('region', { name: 'Subscription' });
        expect(within(subscription).getByText(/Growth/)).toBeInTheDocument();

        const team = screen.getByRole('region', { name: 'Team' });
        expect(within(team).getByText('ayse@ornek.test')).toBeInTheDocument();
        expect(within(team).getByText('owner')).toBeInTheDocument();

        const events = screen.getByRole('region', { name: 'Last events' });
        expect(within(events).getByText('Kadıköy · v4')).toBeInTheDocument();
        expect(within(events).getByText('published')).toBeInTheDocument();

        expect(fetch).toHaveBeenCalledWith('/api/admin/workspaces/7', expect.anything());
    });

    it('says a workspace has no subscription instead of drawing an empty plan card', async () => {
        stubFetch(200, { ...DETAIL, subscription: { state: 'none' } });

        await openWorkspace();

        const subscription = await screen.findByRole('region', { name: 'Subscription' });
        expect(
            within(subscription).getByText(
                'No subscription has ever been recorded for this workspace.',
            ),
        ).toBeInTheDocument();
    });

    it('warns that a long list is trimmed so the count above is not read as the list length', async () => {
        stubFetch(200, {
            ...DETAIL,
            usage: { ...DETAIL.usage, locations: 61 },
            listsTruncated: { locations: true, menus: false, members: false },
        });

        await openWorkspace();

        const branches = await screen.findByRole('region', { name: 'Branches' });
        expect(
            within(branches).getByText(
                'Only the first rows are drawn; the count above is the real one.',
            ),
        ).toBeInTheDocument();
    });

    it('says it could not read rather than drawing an empty workspace', async () => {
        stubFetch(500, {});

        await openWorkspace();

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'We could not load this workspace.',
        );
        // Boş kartlar çizmek "bu restoranda hiç şube yok" derdi; oysa
        // bilinen tek şey okuyamadığımızdır.
        expect(screen.queryByRole('region', { name: 'Branches' })).not.toBeInTheDocument();
    });

    it('offers no door to act as the tenant and no way to touch their data', async () => {
        await openWorkspace();

        await screen.findByRole('region', { name: 'Usage' });

        const forbidden =
            /impersonate|view as|sign in as|log in as|reset password|change password|suspend|delete/i;

        for (const control of [
            ...screen.queryAllByRole('button'),
            ...screen.queryAllByRole('link'),
        ]) {
            expect(control).not.toHaveAccessibleName(forbidden);
        }
    });
});
