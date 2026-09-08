import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { SupportDesk } from './SupportDesk';
import type { SupportAccessSession, TenantSupportView } from './types';

/**
 * DESTEK MASASI — `docs/122` §3 boşluk 3 ve §5, `docs/133`.
 *
 * Bu dosya iki şeyi birden dondurur: destek çağrısının impersonation'SIZ
 * cevaplanabildiğini, ve bakma kartının KOLAY OLMADIĞINI.
 */

const view: TenantSupportView = {
    workspace: { id: 42, name: 'Kadıköy Kebap', slug: 'kadikoy-kebap', state: 'active' },
    subscription: { state: 'active', plan_name: 'Restaurant', plan_code: 'restaurant' },
    locations: [
        {
            id: 7,
            displayName: 'Moda Caddesi',
            acceptsOrders: true,
            menuCount: 2,
            qrCodes: [
                {
                    id: 11,
                    guestUrl: 'https://zabuno.test/q/token-bir',
                    state: 'active',
                    destinationType: 'menu',
                    menuId: 21,
                    menuName: 'Kahvaltı',
                    publishedVersion: null,
                    publishedAt: null,
                },
            ],
        },
    ],
    supportRequests: [
        {
            reference: 'ZB-3F7K2',
            subject: 'Menüm görünmüyor',
            status: 'received',
            receivedAt: '2026-09-06 09:14:02',
            firstResponseAt: null,
        },
    ],
    accessHistory: [],
    findings: [{ code: 'qr_menu_never_published', locationId: 7 }],
};

const openSession: SupportAccessSession = {
    id: 9,
    workspaceId: 42,
    actor: 'destek@zabuno.com',
    reason: 'Sahip aradı: karekod boş sayfa açıyor.',
    startedAt: '2026-09-07 10:00:00',
    expiresAt: '2026-09-07 10:15:00',
    endedAt: null,
    active: true,
};

function renderDesk(overrides: Partial<React.ComponentProps<typeof SupportDesk>> = {}) {
    const onOpen = vi.fn();
    const onEnd = vi.fn();

    render(
        <SupportDesk
            view={view}
            session={null}
            windowMinutes={15}
            reasonMinLength={12}
            busy={false}
            notice={null}
            onOpen={onOpen}
            onEnd={onEnd}
            {...overrides}
        />,
    );

    return { onOpen, onEnd };
}

describe('SupportDesk', () => {
    it('answers "menüm görünmüyor" without opening any session', () => {
        renderDesk();

        const findings = screen.getByRole('region', { name: 'What is wrong right now' });
        expect(
            within(findings).getByText(/never published, so the printed code/),
        ).toBeInTheDocument();
        // Bulgu hangi ŞUBE olduğunu söyler.
        expect(within(findings).getByText(/Moda Caddesi/)).toBeInTheDocument();

        // Ve misafirin gördüğü adres, tıklanabilir olarak.
        const guest = screen.getByRole('region', { name: 'What the guest sees' });
        expect(
            within(guest).getByRole('link', { name: 'https://zabuno.test/q/token-bir' }),
        ).toHaveAttribute('href', 'https://zabuno.test/q/token-bir');
    });

    it('the open-session button stays disabled until a real reason is typed', async () => {
        const user = userEvent.setup();
        const { onOpen } = renderDesk();

        const submit = screen.getByRole('button', {
            name: 'Open a support session on Kadıköy Kebap',
        });
        expect(submit).toBeDisabled();

        // Tek kelime bir sebep değildir.
        await user.type(screen.getByLabelText(/Why do you need to look/), 'test');
        expect(submit).toBeDisabled();

        await user.clear(screen.getByLabelText(/Why do you need to look/));
        await user.type(
            screen.getByLabelText(/Why do you need to look/),
            'Sahip aradı: karekod boş sayfa açıyor.',
        );
        expect(submit).toBeEnabled();

        await user.click(submit);
        expect(onOpen).toHaveBeenCalledWith('Sahip aradı: karekod boş sayfa açıyor.');
    });

    it('tells the admin, before they type, that the owner will read the reason', () => {
        renderDesk();

        expect(
            screen.getByText(/the restaurant owner will read exactly what you write/),
        ).toBeInTheDocument();
        // Ve süre ekranın kendi sayısı değil, sunucudan gelen sayı.
        expect(screen.getByText(/lasts 15 minutes/)).toBeInTheDocument();
    });

    it('an open session is the first thing on the page, with its end time and reason', async () => {
        const user = userEvent.setup();
        const { onEnd } = renderDesk({ session: openSession });

        const banner = screen.getByRole('region', { name: 'You are inside a tenant account' });
        expect(
            within(banner).getByText(/ends by itself at 2026-09-07 10:15:00/),
        ).toBeInTheDocument();
        expect(within(banner).getByText(/Sahip aradı/)).toBeInTheDocument();
        expect(within(banner).getByText(/no payment, no plan change/)).toBeInTheDocument();

        await user.click(within(banner).getByRole('button', { name: 'End the session now' }));
        expect(onEnd).toHaveBeenCalledTimes(1);
    });

    it('never lets a second session stack on an open one', () => {
        renderDesk({ session: openSession });

        expect(
            screen.getByRole('button', { name: 'Open a support session on Kadıköy Kebap' }),
        ).toBeDisabled();
        expect(screen.getByText(/sessions never stack and are never renewed/)).toBeInTheDocument();
    });

    it('shows the server sentence when a write was refused, not a guess of its own', () => {
        renderDesk({ session: openSession, notice: 'This support access session is read-only.' });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'This support access session is read-only.',
        );
    });

    it('says nothing is broken instead of drawing an empty box', () => {
        renderDesk({ view: { ...view, findings: [] } });

        expect(screen.getByText(/Nothing measurable is broken/)).toBeInTheDocument();
    });

    it('does not hide a finding code it has no translation for', () => {
        renderDesk({
            view: { ...view, findings: [{ code: 'a_ninth_finding', locationId: null }] },
        });

        // Bilinmeyen bir bulguyu gizleyen bir destek ekranı, destek
        // görevlisini gördüğünden farklı bir gerçeğe inandırır.
        expect(screen.getByText('a_ninth_finding')).toBeInTheDocument();
    });

    it('tells the admin where the tenant reads the same list', () => {
        renderDesk();

        expect(
            screen.getByText(/The owner reads this same list in their own panel/),
        ).toBeInTheDocument();
    });
});
