import { t } from '../../../i18n/dashboard';
import { t as tWorkspace } from '../../../i18n/workspace';
import { StatCard } from '../../catalog/data-display/compound/StatCard';
import { ResponsiveDataTable } from '../../catalog/data-display/compound/ResponsiveDataTable';
import { OpsCard } from '../../ops/OpsCard';
import type { DataTableColumn } from '../../catalog/data-display/compound/ResponsiveDataTable';
import { DashboardSetupJourney } from './dashboard/DashboardSetupJourney';
import type { BrandProfile } from '../BrandEditForm';
import type { LocationProfile } from '../LocationEditForm';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
import { Button } from 'flowbite-react';

export type DashboardMenuItemRow = {
    id: number;
    categoryId: number;
    productId: number;
    productName?: string;
    priceMinorAmount: number;
    currencyCode: string;
    position: number;
    allergens: string[];
    isVisible: boolean;
};

export type DashboardCategoryRow = {
    id: number;
    menuId: number;
    name: string;
    position: number;
    menuItems: DashboardMenuItemRow[];
};

export type DashboardMenuTree = {
    id: number;
    workspaceId: number;
    locationId: number;
    name: string;
    state: string;
    categories: DashboardCategoryRow[];
};

type DashboardMenuItemTableRow = DashboardMenuItemRow & { categoryName: string };

const DASHBOARD_MENU_ITEM_COLUMNS: readonly DataTableColumn<DashboardMenuItemTableRow>[] = [
    {
        key: 'productName',
        header: t('dashboard.table.column.item'),
        render: (row) => `${row.productName ?? `#${row.productId}`} (${row.categoryName})`,
    },
    {
        key: 'isVisible',
        header: t('dashboard.table.column.visible'),
        render: (row) => (row.isVisible ? 'Yes' : 'No'),
    },
];

type DashboardPageProps = {
    dashboardMenuTree: DashboardMenuTree | null;
    brand?: BrandProfile | null;
    location?: LocationProfile | null;
    workspaceId?: number;
    onNavigateToSection?: (section: string) => void;
    /** Pennant `novice-home` (FF-74): kiracıda kapatılırsa 'şimdi' kutusu çizilmez. */
    noviceHome?: boolean;
};

export function DashboardPage({
    dashboardMenuTree,
    brand = null,
    location = null,
    workspaceId,
    onNavigateToSection,
    noviceHome = true,
}: DashboardPageProps) {
    return (
        <div id="section-dashboard">
            {/* Tek `h1` sayfanın başında (`docs/102` §4); gövde kart gramerinde. */}
            <WorkspacePageFrame
                measure="standard"
                title={t('dashboard.heading')}
                description={tWorkspace('workspace.dashboard.operational.description')}
            >
                <DashboardSetupJourney
                    brand={brand}
                    location={location}
                    dashboardMenuTree={dashboardMenuTree}
                    workspaceId={workspaceId}
                    onNavigateToSection={onNavigateToSection}
                    noviceHome={noviceHome}
                />

                {dashboardMenuTree ? (
                    <>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-4">
                            <StatCard
                                label={t('dashboard.stats.categories')}
                                value={dashboardMenuTree.categories.length}
                            />
                            <StatCard
                                label={t('dashboard.stats.items')}
                                value={dashboardMenuTree.categories.reduce(
                                    (total, category) => total + category.menuItems.length,
                                    0,
                                )}
                            />
                            <StatCard
                                label={t('dashboard.stats.visible')}
                                value={`${dashboardMenuTree.categories.reduce(
                                    (total, category) =>
                                        total +
                                        category.menuItems.filter((item) => item.isVisible).length,
                                    0,
                                )} / ${dashboardMenuTree.categories.reduce(
                                    (total, category) => total + category.menuItems.length,
                                    0,
                                )}`}
                            />
                        </div>
                        <OpsCard title={t('dashboard.table.heading')} padded={false}>
                            <ResponsiveDataTable<DashboardMenuItemTableRow>
                                caption={t('dashboard.table.caption')}
                                columns={DASHBOARD_MENU_ITEM_COLUMNS}
                                rows={dashboardMenuTree.categories.flatMap((category) =>
                                    category.menuItems.map((item) => ({
                                        ...item,
                                        categoryName: category.name,
                                    })),
                                )}
                                getRowKey={(row) => String(row.id)}
                            />
                        </OpsCard>
                    </>
                ) : (
                    /*
                        Boş durumun TEK çıkış yolu bir ölü bağlantıydı:
                        `href="#menu"`, adres tabanlı gezintiye geçildiğinden
                        beri hiçbir şey yapmıyordu (`docs/70`). Menüsü olmayan
                        bir kullanıcının Home'da yapabileceği tek şey buydu.
                    */
                    <div className="flex flex-col gap-3">
                        <PageState
                            kind="empty"
                            title={t('dashboard.empty')}
                            {...(onNavigateToSection
                                ? {
                                      action: (
                                          <Button
                                              size="sm"
                                              onClick={() => onNavigateToSection('menu')}
                                          >
                                              {t('dashboard.empty.openMenu')}
                                          </Button>
                                      ),
                                  }
                                : { whyNoAction: t('dashboard.empty') })}
                        />
                    </div>
                )}
            </WorkspacePageFrame>
        </div>
    );
}

export default DashboardPage;
