import type { Meta, StoryObj } from '@storybook/react-vite';
import { ProfilePage } from './ProfilePage';
import { ThemeRoot } from '../../theme/ThemeRoot';
import type { BrandProfile } from '../BrandEditForm';

/**
 * Profil ekranı (FF-88). Hikâyeler, ekranın gerçekten ne kadar yer kapladığını
 * ve bölümlerin birbirinden ayrılıp ayrılmadığını GÖRMEK içindir — ölçüsüz bir
 * estetik iddiası, ekrana bakmadan doğrulanamaz.
 */
const brand: BrandProfile = {
    id: 1,
    workspace_id: 7,
    name: 'Zeytin Kebap',
    slug: 'zeytin-kebap',
    locale: 'tr',
    timezone: 'Europe/Istanbul',
    currency: 'TRY',
    description: null,
    contact_email: null,
    contact_phone: null,
    primary_color: '#C8102E',
    secondary_color: '#1B4332',
};

const meta: Meta<typeof ProfilePage> = {
    title: 'Surface/Workspace/ProfilePage',
    component: ProfilePage,
    /*
        Gerçek uygulamada ekran `ThemeRoot` altındadır; hikâye de öyle olmalı.
        Aksi hâlde "Görünüm" bölümü sağlayıcı bulamaz, hiç çizilmez ve hikâye
        ürünün göstermediği bir ekranı gösterirdi.
    */
    decorators: [
        (Story) => (
            <ThemeRoot>
                <Story />
            </ThemeRoot>
        ),
    ],
    args: {
        workspaceId: 7,
        email: 'mehmet@zeytinkebap.com',
        userName: 'Mehmet Usta',
        avatarMediaAssetId: null,
        avatarUrl: null,
        brand,
        onBrandSaved: () => {},
        canManageBrand: true,
    },
};

export default meta;
type Story = StoryObj<typeof ProfilePage>;

/** Yönetici: marka rengi bölümü de görünür. */
export const Manager: Story = {};

/** Personel: renk bölümü HİÇ çizilmez — dokunamayacağı kontrolü görmez. */
export const WithoutBrandPermission: Story = { args: { canManageBrand: false } };

/** Fotoğrafı olan kullanıcı. */
export const WithPhoto: Story = {
    args: {
        avatarMediaAssetId: 42,
        avatarUrl:
            'data:image/svg+xml;utf8,' +
            encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="128" height="128" fill="#C8102E"/><text x="64" y="80" font-size="56" text-anchor="middle" fill="white" font-family="sans-serif">M</text></svg>',
            ),
    },
};
