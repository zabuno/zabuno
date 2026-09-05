import type { Meta, StoryObj } from '@storybook/react-vite';
import { DashboardGreeting } from './DashboardGreeting';
import type { BrandProfile } from '../../BrandEditForm';

/**
 * Home'un açılış bloğu — panelin her sabah ilk görülen iki satırı.
 *
 * İKİ HÂL var, çünkü blok ilk gün ile sonraki günlerde farklı konuşur:
 * marka henüz yazılmamışken karşılama adsızdır. Yer tutucu bir ad koymak,
 * kullanıcının adını bildiğimizi ima etmek olurdu.
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
};

const meta: Meta<typeof DashboardGreeting> = {
    title: 'Surface/Workspace/DashboardGreeting',
    component: DashboardGreeting,
    decorators: [
        (Story) => (
            <div className="max-w-[52rem] bg-canvas p-[var(--space-fluid-lg)]">
                <Story />
            </div>
        ),
    ],
};

export default meta;
type Story = StoryObj<typeof DashboardGreeting>;

/** İlk gün: marka henüz yazılmamış, karşılama adsız. */
export const WithoutBrand: Story = {
    args: { brand: null },
};

/** Sonraki günler: karşılama işletmenin kendi adını kullanır. */
export const WithBrand: Story = {
    args: { brand },
};
