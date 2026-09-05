import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrPrintPreview } from './QrPrintPreview';

/**
 * Yazıcıdan ne çıkacak. Kâğıt ve yön seçicileri, kontrol ettikleri sonucu
 * hiçbir yerde göstermiyordu: sahip "A6 yatay" seçiyor ve ne olacağını ancak
 * yazıcıdan kâğıt çıkınca öğreniyordu.
 */
const meta = {
    title: 'Surface/Workspace/QrPrintPreview',
    component: QrPrintPreview,
    args: { paperSize: 'A4', orientation: 'Portrait' },
    decorators: [
        (Story) => (
            <div className="max-w-[40rem] bg-canvas p-[var(--space-fluid-lg)]">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof QrPrintPreview>;

export default meta;
type Story = StoryObj<typeof meta>;

/** A4 dikey: duvara ya da vitrine asılacak afiş. */
export const A4Portrait: Story = {};

/** Yön gerçekten değişir — şema oranı korur. */
export const A4Landscape: Story = { args: { orientation: 'Landscape' } };

/** Küçük kâğıt, küçük kod: mesafe cümlesi de küçülür. */
export const A7Portrait: Story = { args: { paperSize: 'A7' } };
