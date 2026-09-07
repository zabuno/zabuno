import type { Meta, StoryObj } from '@storybook/react-vite';
import { PanelCard } from '../shared/PanelCard';
import { SupportRequestList } from './SupportRequestList';

/**
 * Talep listesi (FF-201) — satır grameri 320 pikselde ölçülür: referans ve
 * rozet ilk satır, konu ikinci, tarih üçüncü. Satırlar etkileşimsizdir;
 * dokunma hedefi yoktur ve bu bilerek böyledir.
 */
const meta: Meta<typeof SupportRequestList> = {
    title: 'Surface/Workspace/SupportRequestList',
    component: SupportRequestList,
    decorators: [
        (Story) => (
            <PanelCard>
                <Story />
            </PanelCard>
        ),
    ],
    args: {
        status: 'success',
        requests: [
            {
                id: 3,
                reference: 'ZB-7KM4P',
                subject: 'Menüm görünmüyor',
                status: 'received',
                received_at: '2026-09-06T09:12:00+03:00',
                first_response_at: null,
            },
            {
                id: 2,
                reference: 'ZB-3F7K2',
                subject:
                    'Karekod kartlarını nasıl bastırırım? Masaya koyacağım kartın ölçüsü nedir?',
                status: 'answered',
                received_at: '2026-09-04T14:30:00+03:00',
                first_response_at: '2026-09-05T10:05:00+03:00',
            },
            {
                id: 1,
                reference: 'ZB-Q2W9X',
                subject: 'Fatura adresi',
                status: 'closed',
                received_at: '2026-08-28T08:00:00+03:00',
                first_response_at: '2026-08-28T15:40:00+03:00',
            },
        ],
    },
};

export default meta;
type Story = StoryObj<typeof SupportRequestList>;

export const Populated: Story = {};

export const Loading: Story = { args: { status: 'loading', requests: [] } };

export const Empty: Story = { args: { status: 'success', requests: [] } };

export const Failed: Story = { args: { status: 'error', requests: [] } };
