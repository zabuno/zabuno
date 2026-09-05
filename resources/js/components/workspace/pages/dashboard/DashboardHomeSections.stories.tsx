import type { Meta, StoryObj } from '@storybook/react-vite';
import { DashboardSuggestions } from './DashboardSuggestions';
import { DashboardQuickActions } from './DashboardQuickActions';
import { DashboardTopViewed } from './DashboardTopViewed';
import type { MenuInsights } from './useMenuInsights';
import type { DashboardMenuTree } from '../DashboardPage';

/**
 * Home'un v3 bölümleri — kaynak `panel.dc.html`, `data-screen-label="Home"`.
 *
 * Üç bölümün de ASIL hâli "hiç çizilmemiş" hâlidir ve story'ler bunu bir
 * durum olarak gösterir. Ölçüm okunamadığında boş bir kutu bırakmak, sahibe
 * olmayan bir ölçümün varlığını iddia eder; izole olarak bakan biri bu kararı
 * ancak iki hâli yan yana görünce anlar.
 */

const menuTree: DashboardMenuTree = {
    id: 1,
    workspaceId: 7,
    locationId: 3,
    name: 'Ana Menü',
    state: 'published',
    categories: [
        {
            id: 1,
            menuId: 1,
            name: 'Kebaplar',
            position: 0,
            menuItems: [
                {
                    id: 101,
                    categoryId: 1,
                    productId: 901,
                    productName: 'Adana Kebap',
                    priceMinorAmount: 32000,
                    currencyCode: 'TRY',
                    position: 0,
                    allergens: [],
                    isVisible: true,
                },
                {
                    id: 102,
                    categoryId: 1,
                    productId: 902,
                    productName: 'Lahmacun',
                    priceMinorAmount: 9500,
                    currencyCode: 'TRY',
                    position: 1,
                    allergens: [],
                    isVisible: true,
                },
            ],
        },
    ],
};

const measured: MenuInsights = {
    state: 'ready',
    mostViewed: [
        { menuItemId: 101, productName: 'Adana Kebap', categoryName: 'Kebaplar', viewers: 61 },
        { menuItemId: 102, productName: 'Lahmacun', categoryName: 'Kebaplar', viewers: 31 },
    ],
    neverViewed: [
        { menuItemId: 103, productName: 'Tavuk Şiş', categoryName: 'Kebaplar', viewers: 0 },
    ],
    searchesWithNoResults: [{ term: 'Vejetaryen', searches: 14 }],
};

const meta: Meta = {
    title: 'Surface/Workspace/DashboardHomeSections',
    decorators: [
        (Story) => (
            <div className="flex max-w-[64rem] flex-col gap-[var(--space-fluid-md)] bg-canvas p-[var(--space-fluid-lg)]">
                <Story />
            </div>
        ),
    ],
};

export default meta;
type Story = StoryObj;

/** Ölçüm geldi: iki öneri, dört karo ve en çok bakılanlar bir arada. */
export const Measured: Story = {
    render: () => (
        <>
            <DashboardSuggestions insights={measured} onNavigateToSection={() => {}} />
            <DashboardQuickActions onNavigateToSection={() => {}} />
            <DashboardTopViewed
                insights={measured}
                dashboardMenuTree={menuTree}
                onNavigateToSection={() => {}}
            />
        </>
    ),
};

/**
 * Ölçüm YOK: yalnız dört karo kalır. Öneri ve tablo bölümleri boş kutu
 * bırakmaz, hiç çizilmez — ekran kısalır, yalan söylemez.
 */
export const WithoutMeasurement: Story = {
    render: () => (
        <>
            <DashboardSuggestions insights={null} onNavigateToSection={() => {}} />
            <DashboardQuickActions onNavigateToSection={() => {}} />
            <DashboardTopViewed
                insights={null}
                dashboardMenuTree={menuTree}
                onNavigateToSection={() => {}}
            />
        </>
    ),
};
