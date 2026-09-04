import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrDestinationFieldsRegion } from './QrDestinationFieldsRegion';

/**
 * Kod oluşturma — kapalı olmanın SEBEBİ söylenir.
 *
 * Önceden tek bir cümle vardı ("önce menünüzü yayınlayın") ve yayın bilgisi
 * yoldayken ya da sunucuya ulaşılamadığında da o yazıyordu: yayında bir menüsü
 * olan sahibe yanlış bir iş yaptırıyordu.
 */
const meta = {
    title: 'Surface/Workspace/QrDestinationFieldsRegion',
    component: QrDestinationFieldsRegion,
    args: { disabled: false, onCreate: () => undefined },
    decorators: [
        (Story) => (
            <div className="max-w-[28rem] bg-canvas p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof QrDestinationFieldsRegion>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Yayın var: düğme açık, gerekçe cümlesi yok. */
export const Ready: Story = {};

/** Yayın gerçekten yok. */
export const NotPublished: Story = { args: { disabled: true, reasonKind: 'notPublished' } };

/** Yayın bilgisi henüz gelmedi — "önce yayınlayın" DEMEZ. */
export const Checking: Story = { args: { disabled: true, reasonKind: 'loading' } };

/** Sunucuya ulaşılamadı: basılı kodlar çalışmaya devam ediyor olabilir. */
export const Unknown: Story = { args: { disabled: true, reasonKind: 'unknown' } };
