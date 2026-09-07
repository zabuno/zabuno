import type { Meta, StoryObj } from '@storybook/react-vite';
import { RegisterForm } from './RegisterForm';
import { ThemeRoot } from '../theme/ThemeRoot';

/**
 * Kayıt ekranı (FF-198). Hikâye, iki onay kutusunun ve belge bağlantılarının
 * 320 pikselde GERÇEKTEN nasıl durduğunu ölçmek için var
 * (`scripts/mobile-ux-audit`): kutu etiketiyle birlikte 44 piksel mi,
 * bağlantılar parmakla vurulabilir mi, hiçbir şey kenardan taşmıyor mu.
 * Estetik iddiası değil, ölçüm zemini.
 */
const meta: Meta<typeof RegisterForm> = {
    title: 'Surface/Auth/RegisterForm',
    component: RegisterForm,
    decorators: [
        (Story) => (
            <ThemeRoot>
                <Story />
            </ThemeRoot>
        ),
    ],
    args: {
        // Hikâyede gezinti yok: gönderim bir sayfaya gitmez.
        navigate: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof RegisterForm>;

/** Boş form: iki kutu da işaretsiz — sessizlik onay değildir. */
export const Default: Story = {};
