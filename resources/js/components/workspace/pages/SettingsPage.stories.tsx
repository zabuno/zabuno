import type { Meta, StoryObj } from '@storybook/react-vite';

import { ThemeRoot } from '../../theme/ThemeRoot';
import { SettingsPage } from './SettingsPage';

/**
 * Ayarlar ekranı ürüne girmeden görülemiyordu (FF-131).
 *
 * Sekme grameri —bölümlü kontrol— tam olarak "bakmadan doğrulanamayan"
 * türden bir karar: sınıf listesi doğru olsa bile kutunun genişliği,
 * taşma davranışı ve seçili sekmenin dolgusu ancak ekranda görülür.
 *
 * `ThemeRoot` ile sarılır çünkü Görünüm bölümü sağlayıcı yoksa hiç
 * çizilmez; sarmasız bir hikâye eksik bir sayfa gösterir ve yanıltır.
 */
const meta: Meta<typeof SettingsPage> = {
    title: 'Surface/Workspace/SettingsPage',
    component: SettingsPage,
    parameters: { layout: 'fullscreen' },
    args: {
        workspaceId: 1,
        brand: null,
        onSaved: () => undefined,
        onSelectTab: () => undefined,
        onNavigateToMedia: () => undefined,
    },
    decorators: [
        (Story) => (
            <ThemeRoot>
                <div className="min-h-dvh bg-canvas p-[var(--space-fluid-lg)]">
                    <Story />
                </div>
            </ThemeRoot>
        ),
    ],
};

export default meta;

type Story = StoryObj<typeof SettingsPage>;

export const BrandTab: Story = { args: { activeTab: 'brand' } };

/**
 * Çalışma alanı sekmesi (docs/109): çalışma alanının adı ve panel adresi.
 * Kişisel bilgiler burada DEĞİL — hepsi Profil ekranında.
 */
export const WorkspaceTab: Story = { args: { activeTab: 'workspace' } };
