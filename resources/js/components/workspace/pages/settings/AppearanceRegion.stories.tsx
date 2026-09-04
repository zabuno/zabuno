import type { Meta, StoryObj } from '@storybook/react-vite';

import { ThemeRoot } from '../../../theme/ThemeRoot';
import { AppearanceRegion } from './AppearanceRegion';

/**
 * Görünüm bölümü ürüne girmeden görülemiyordu (FF-128).
 *
 * Tema ve satır aralığı seçicileri yalnız oturum açmış bir Ayarlar sayfasında
 * çiziliyordu; yani "değişikliği ekranda gör" kuralı burada uygulanamıyordu.
 * Hikâye `ThemeRoot` ile sarılır çünkü iki kontrol de sağlayıcı yoksa HİÇ
 * çizilmez — sarmasız bir hikâye boş bir kutu gösterir ve yalan söylerdi.
 */
const meta: Meta<typeof AppearanceRegion> = {
    title: 'Surface/Workspace/AppearanceRegion',
    component: AppearanceRegion,
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="max-w-[42rem] bg-canvas p-[var(--space-5)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
};

export default meta;

export const Default: StoryObj<typeof AppearanceRegion> = {};
