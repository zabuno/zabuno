import { t } from '../../../i18n/dashboard';
import { t as tWorkspace } from '../../../i18n/workspace';
import { DashboardOverview } from '../../catalog/layout/macro/DashboardOverview';
import type { DataTableColumn } from '../../catalog/data-display/compound/ResponsiveDataTable';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import { DashboardSetupJourney } from './dashboard/DashboardSetupJourney';
import type { BrandProfile } from '../BrandEditForm';
import type { LocationProfile } from '../LocationEditForm';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

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
};

export function DashboardPage({
    dashboardMenuTree,
    brand = null,
    location = null,
    workspaceId,
}: DashboardPageProps) {
    const badges: WorkspacePageStatusBadge[] = dashboardMenuTree
        ? [
              {
                  key: 'dashboard-menu-state',
                  status: dashboardMenuTree.state === 'draft' ? 'warning' : 'success',
                  label: dashboardMenuTree.state,
              },
          ]
        : [];

    return (
        <div id="dashboard">
            <WorkspacePageFrame
                description={tWorkspace('workspace.dashboard.operational.description')}
                badges={badges}
            >
                <DashboardSetupJourney
                    brand={brand}
                    location={location}
                    dashboardMenuTree={dashboardMenuTree}
                    workspaceId={workspaceId}
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
                    <div className="flex flex-col gap-3">
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-white">
                            {t('dashboard.heading')}
                        </h1>
                        <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                            {t('dashboard.empty')}
                        </p>
                        <a
                            href="#menu"
                            className="text-sm font-medium text-blue-600 dark:text-blue-400"
                        >
                            {t('dashboard.empty.openMenu')}
                        </a>
                    </div>
                )}

                <AiAssistPanel context={t('dashboard.heading')} />
            </WorkspacePageFrame>
        </div>
    );
}

export default DashboardPage;
