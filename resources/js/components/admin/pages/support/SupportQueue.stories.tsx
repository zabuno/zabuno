import type { Meta, StoryObj } from '@storybook/react-vite';

import { SupportQueue } from './SupportQueue';
import { ThemeRoot } from '../../../theme/ThemeRoot';

/**
 * Destek kuyruğu, GERÇEK bir düzen motorunda (`docs/117`, `docs/125` §6).
 *
 * En uzun dize E-POSTA ADRESİDİR ve hikâye onu gerçek uzunluğunda taşır:
 * kırılmayan bir adres, 320 pikselde sayfayı yana kaydırır.
 */
const meta: Meta<typeof SupportQueue> = {
    title: 'Surface/Platform/SupportQueue',
    component: SupportQueue,
    parameters: { layout: 'fullscreen' },
    args: {
        status: '',
        busy: false,
        onStatusFilter: () => undefined,
        onChangeStatus: () => undefined,
        onOpenWorkspace: () => undefined,
        rows: [
            {
                id: 1,
                reference: 'ZB-3F7K2',
                workspace_id: 42,
                name: 'Hüseyin Kaya',
                email: 'huseyin.kaya@kadikoykebapsalonu-moda.example.com',
                subject: 'Menüm görünmüyor',
                message: 'Masadaki karekodu okuttum, boş sayfa açılıyor. Dün akşam çalışıyordu.',
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
                message: 'Üç şubem var, hangi plana bakmalıyım?',
                channel: 'public_contact',
                status: 'received',
                received_at: '2026-09-06 11:02:40',
                first_response_at: null,
                acknowledgement: 'not_attempted',
            },
        ],
    },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-3)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
};

export default meta;

type Story = StoryObj<typeof SupportQueue>;

export const Waiting: Story = {};

/** Boş kuyruk BOŞ bir kutu değil, bir cümledir. */
export const NothingWaiting: Story = { args: { rows: [] } };
