import type { Meta, StoryObj } from '@storybook/react-vite';
import { PanelCard } from '../shared/PanelCard';
import { SupportRequestForm } from './SupportRequestForm';

/**
 * Yeni talep formu (FF-201). 320 pikselde ölçülen şey: konu alanı ve
 * metin alanı 44 piksel mi, düğme tam genişlikte mi, yardım cümlesi
 * kelime ortasından kırılmıyor mu.
 */
const meta: Meta<typeof SupportRequestForm> = {
    title: 'Surface/Workspace/SupportRequestForm',
    component: SupportRequestForm,
    decorators: [
        (Story) => (
            <PanelCard>
                <Story />
            </PanelCard>
        ),
    ],
    args: {
        email: 'mehmet@zeytinkebap.com',
        onSubmit: async () => ({
            kind: 'sent',
            reference: 'ZB-7KM4P',
            acknowledgement: 'sent',
        }),
    },
};

export default meta;
type Story = StoryObj<typeof SupportRequestForm>;

export const Idle: Story = {};

/** Alındı e-postası ÇIKMADI: cümle "kopyasını gönderdik" demez. */
export const ConfirmationNotSent: Story = {
    args: {
        onSubmit: async () => ({ kind: 'sent', reference: 'ZB-7KM4P', acknowledgement: 'failed' }),
    },
};

export const ServerDown: Story = {
    args: { onSubmit: async () => ({ kind: 'error' }) },
};
