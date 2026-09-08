import { describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { SupportQueue, type SupportQueueRow } from './SupportQueue';

/**
 * DESTEK KUYRUĞU — `docs/125` §6'nın kapanışı.
 *
 * O belge şunu yazıyordu: *"Uçlar var, ekran yok… Ekran `docs/122` Y7 ile
 * birlikte gelir."* Bu dosya o cümleyi kapatır ve ürünün BUGÜNKÜ gerçeğini
 * de dondurur: cevap yazma yüzeyi hâlâ yok ve ekran öyle bir yüzey varmış
 * gibi davranmıyor.
 */
const rows: SupportQueueRow[] = [
    {
        id: 1,
        reference: 'ZB-3F7K2',
        workspace_id: 42,
        name: 'Hüseyin',
        email: 'huseyin@example.com',
        subject: 'Menüm görünmüyor',
        message: 'Karekodu okutunca boş sayfa açılıyor.',
        channel: 'panel',
        status: 'received',
        received_at: '2026-09-06 09:14:02',
        first_response_at: null,
        acknowledgement: 'sent',
    },
    {
        id: 2,
        reference: 'ZB-9QW4M',
        workspace_id: null,
        name: 'Ayşe',
        email: 'ayse@example.com',
        subject: 'Fiyatlarınız nedir',
        message: 'Üç şubem var.',
        channel: 'public_contact',
        status: 'received',
        received_at: '2026-09-06 11:02:40',
        first_response_at: null,
        acknowledgement: 'not_attempted',
    },
];

function renderQueue(overrides: Partial<React.ComponentProps<typeof SupportQueue>> = {}) {
    const onStatusFilter = vi.fn();
    const onChangeStatus = vi.fn();

    render(
        <SupportQueue
            rows={rows}
            status=""
            busy={false}
            onStatusFilter={onStatusFilter}
            onChangeStatus={onChangeStatus}
            {...overrides}
        />,
    );

    return { onStatusFilter, onChangeStatus };
}

describe('SupportQueue', () => {
    it('shows who wrote, what they wrote, and how to reach them', () => {
        renderQueue();

        const region = screen.getByRole('region', { name: 'Support queue' });

        expect(within(region).getByText(/ZB-3F7K2 · Menüm görünmüyor/)).toBeInTheDocument();
        expect(within(region).getByText(/huseyin@example.com/)).toBeInTheDocument();
        expect(
            within(region).getByText('Karekodu okutunca boş sayfa açılıyor.'),
        ).toBeInTheDocument();
    });

    it('warns when the sender never got an acknowledgement', () => {
        renderQueue();

        // İki satır var, uyarı YALNIZ alındı e-postası çıkmayanda: sahibin
        // "yazdım ama cevap gelmedi" demesinin sebebi çoğu zaman budur.
        const warnings = screen.getAllByText(/No acknowledgement email reached this sender/);
        expect(warnings).toHaveLength(1);
    });

    it('says plainly that marking a request answered sends nothing', () => {
        renderQueue();

        expect(
            screen.getByText(
                /this product has no reply surface yet, so marking a request answered/,
            ),
        ).toBeInTheDocument();
    });

    it('changes a status through the caller, and hides the button for the status already held', async () => {
        const user = userEvent.setup();
        const { onChangeStatus } = renderQueue({
            rows: [{ ...rows[0], status: 'answered' }],
        });

        expect(screen.queryByRole('button', { name: 'Mark answered' })).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Close' }));
        expect(onChangeStatus).toHaveBeenCalledWith(1, 'closed');
    });

    it('filters through the server, not in the browser', async () => {
        const user = userEvent.setup();
        const { onStatusFilter } = renderQueue();

        await user.selectOptions(screen.getByLabelText('Status'), 'answered');
        expect(onStatusFilter).toHaveBeenCalledWith('answered');
    });

    it('says nothing is waiting instead of drawing an empty box', () => {
        renderQueue({ rows: [] });

        expect(screen.getByText('Nothing is waiting.')).toBeInTheDocument();
    });
});
