import { t } from '../../../../i18n/dashboard';
import type { BrandProfile } from '../../BrandEditForm';
import type { LocationProfile } from '../../LocationEditForm';
import type { DashboardMenuTree } from '../DashboardPage';

type DashboardSetupJourneyProps = {
    brand: BrandProfile | null;
    location: LocationProfile | null;
    dashboardMenuTree: DashboardMenuTree | null;
};

function menuSummary(dashboardMenuTree: DashboardMenuTree | null): string {
    if (!dashboardMenuTree) {
        return t('dashboard.setup.menu.empty');
    }

    const categories = dashboardMenuTree.categories.length;
    const items = dashboardMenuTree.categories.reduce(
        (total, category) => total + category.menuItems.length,
        0,
    );

    return `${categories} categories · ${items} items`;
}

export function DashboardSetupJourney({
    brand,
    location,
    dashboardMenuTree,
}: DashboardSetupJourneyProps) {
    const notConnected = t('dashboard.setup.notConnected');

    const rows: { key: string; label: string; value: string; href: string }[] = [
        {
            key: 'brand',
            label: t('dashboard.setup.brand'),
            value: brand?.name ?? '',
            href: '#brand',
        },
        {
            key: 'location',
            label: t('dashboard.setup.location'),
            value: location?.display_name ?? '',
            href: '#locations',
        },
        {
            key: 'menu',
            label: t('dashboard.setup.menu'),
            value: menuSummary(dashboardMenuTree),
            href: '#menu',
        },
        {
            key: 'publication',
            label: t('dashboard.setup.publication'),
            value: notConnected,
            href: '#publication',
        },
        { key: 'qr', label: t('dashboard.setup.qr'), value: notConnected, href: '#publication' },
    ];

    return (
        <section aria-label={t('dashboard.setup.region')} className="flex flex-col gap-3">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                {t('dashboard.setup.heading')}
            </h2>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-3">
                {rows.map((row) => (
                    <div key={row.key} className="flex flex-col gap-1">
                        <dt className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            <a
                                href={row.href}
                                className="text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {row.label}
                            </a>
                        </dt>
                        <dd className="text-sm text-gray-900 dark:text-white">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export default DashboardSetupJourney;
