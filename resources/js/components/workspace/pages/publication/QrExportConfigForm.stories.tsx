import type { Meta, StoryObj } from '@storybook/react-vite';
import { QrExportConfigForm } from './QrExportConfigForm';

/**
 * Dışa aktarım ayarları.
 *
 * Bu dosyanın story'si YOKTU (`docs/104`): `surface` katmanı her görsel kapının
 * dışında kalıyordu. Kâğıt ve yön yalnız PDF'te ÇİZİLİR — devre dışı çizilmez;
 * devre dışı bir kontrol ekranda yer kaplar, okunur, tıklanır ve hiçbir şey
 * yapmaz, kullanıcı da onu "bozuk" diye öğrenir.
 */
const meta = {
    title: 'Surface/Workspace/QrExportConfigForm',
    component: QrExportConfigForm,
    args: { outputFormat: 'png', paperSize: 'A4', orientation: 'Portrait' },
    decorators: [
        (Story) => (
            <div className="max-w-[28rem] bg-canvas p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof QrExportConfigForm>;

export default meta;
type Story = StoryObj<typeof meta>;

/** PNG: kâğıt ve yön HİÇ çizilmez. */
export const Image: Story = {};

/** PDF: kâğıt ve yön belirir. */
export const Pdf: Story = { args: { outputFormat: 'pdf' } };
