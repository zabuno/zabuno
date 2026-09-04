import type { Meta, StoryObj } from '@storybook/react-vite';
import { DashboardSetupJourney } from './DashboardSetupJourney';
import type { BrandProfile } from '../../BrandEditForm';
import type { LocationProfile } from '../../LocationEditForm';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * Kurulum şeridi — panonun her gün açılan ilk ekranındaki kart.
 *
 * Hikâyeler ÜÇ HÂLİ gösterir, çünkü kartın işi hâle göre değişir: hiç
 * başlanmamış, ortasında, ve bitmiş. Bitmiş hâl kendiliğinden kapanır —
 * bir kez yapılıp bir daha dönülmeyen bir liste, günlük ekranın ortasında
 * kalıcı gürültüdür.
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

const location: LocationProfile = {
    id: 3,
    workspace_id: 7,
    brand_id: 1,
    display_name: 'Kadıköy Şubesi',
    country_code: 'TR',
    timezone: 'Europe/Istanbul',
    city: 'İstanbul',
    address_line1: 'Bahariye Cd. No:1',
    address_line2: null,
    postal_code: '34710',
};

const menuTree: DashboardMenuTree = {
    id: 11,
    workspaceId: 7,
    locationId: 3,
    name: 'Ana Menü',
    state: 'published',
    categories: [
        {
            id: 21,
            menuId: 11,
            name: 'Kebaplar',
            position: 0,
            menuItems: [
                {
                    id: 31,
                    categoryId: 21,
                    productId: 41,
                    productName: 'Adana',
                    priceMinorAmount: 4250,
                    currencyCode: 'TRY',
                    position: 0,
                    allergens: [],
                    isVisible: true,
                },
                {
                    id: 32,
                    categoryId: 21,
                    productId: 42,
                    productName: 'Urfa',
                    priceMinorAmount: 4250,
                    currencyCode: 'TRY',
                    position: 1,
                    allergens: [],
                    isVisible: true,
                },
            ],
        },
    ],
};

const meta: Meta<typeof DashboardSetupJourney> = {
    title: 'Surface/Workspace/DashboardSetupJourney',
    component: DashboardSetupJourney,
    decorators: [
        (Story) => (
            <div className="max-w-[52rem] bg-canvas p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
    args: {
        onNavigateToSection: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof DashboardSetupJourney>;

/** İlk gün: hiçbir adım bitmemiş, ilk adım sırada. */
export const NothingDoneYet: Story = {
    args: { brand: null, location: null, dashboardMenuTree: null },
};

/** Ortada: marka ve şube tamam, sıradaki adım menü. */
export const InProgress: Story = {
    args: { brand, location, dashboardMenuTree: null },
};

/**
 * Bitmiş: şerit KAPALI açılır ve tek satıra iner. Sahibin her gün gördüğü
 * hâl budur (2026-09-04 ekran görüntüsü).
 */
export const Complete: Story = {
    args: {
        brand,
        location,
        dashboardMenuTree: menuTree,
        workspaceId: 7,
    },
    decorators: [
        /*
            Yayın ve karekod adımları SUNUCUDAN okunur; hikâye onları
            uydurmaz, sunucuyu taklit eder. Bileşene "hazır göster" diye bir
            kapı açmak, hikâyeyi ürünün göstermediği bir hâle sokardı.
        */
        (Story) => {
            window.fetch = (async (input: RequestInfo | URL) =>
                String(input).includes('/publications/current')
                    ? new Response(JSON.stringify({ id: 2 }), { status: 200 })
                    : new Response(JSON.stringify([{ state: 'active' }]), {
                          status: 200,
                      })) as typeof window.fetch;

            return <Story />;
        },
    ],
};
