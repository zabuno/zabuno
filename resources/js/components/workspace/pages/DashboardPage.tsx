import { t } from '../../../i18n/dashboard';
import { t as tWorkspace } from '../../../i18n/workspace';
import { DashboardOverview } from '../../catalog/layout/macro/DashboardOverview';
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
        header: 'Item',
        render: (row) => `${row.productName ?? `#${row.productId}`} (${row.categoryName})`,
    },
    { key: 'isVisible', header: 'Visible', render: (row) => (row.isVisible ? 'Yes' : 'No') },
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
            <WorkspacePageFrame
                measure="standard"
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
                    <DashboardOverview<DashboardMenuItemTableRow>
                        header={{ title: t('dashboard.heading') }}
                        stats={[
                            {
                                key: 'categories',
                                label: 'Categories',
                                value: dashboardMenuTree.categories.length,
                            },
                            {
                                key: 'menu-items',
                                label: 'Menu items',
                                value: dashboardMenuTree.categories.reduce(
                                    (total, category) => total + category.menuItems.length,
                                    0,
                                ),
                            },
                            {
                                key: 'visible-items',
                                label: 'Visible items',
                                value: `${dashboardMenuTree.categories.reduce(
                                    (total, category) =>
                                        total +
                                        category.menuItems.filter((item) => item.isVisible).length,
                                    0,
                                )} / ${dashboardMenuTree.categories.reduce(
                                    (total, category) => total + category.menuItems.length,
                                    0,
                                )}`,
                            },
                        ]}
                        table={{
                            caption: 'Menu item list',
                            columns: DASHBOARD_MENU_ITEM_COLUMNS,
                            rows: dashboardMenuTree.categories.flatMap((category) =>
                                category.menuItems.map((item) => ({
                                    ...item,
                                    categoryName: category.name,
                                })),
                            ),
                            getRowKey: (row) => String(row.id),
                        }}
                    />
                ) : (
                    /*
                        Boş durumun TEK çıkış yolu bir ölü bağlantıydı:
                        `href="#menu"`, adres tabanlı gezintiye geçildiğinden
                        beri hiçbir şey yapmıyordu (`docs/70`). Menüsü olmayan
                        bir kullanıcının Home'da yapabileceği tek şey buydu.
                    */
                    <div className="flex flex-col gap-3">
                        {/*
                            Sayfanın ADI boş durumda da durmalı: `h1` ekran
                            okuyucunun sayfalar arasında gezinme yoludur ve
                            dolu hâlde `PageHeader` onu zaten çiziyor.
                        */}
                        <h1 className="text-title font-semibold text-fg">
                            {t('dashboard.heading')}
                        </h1>
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
