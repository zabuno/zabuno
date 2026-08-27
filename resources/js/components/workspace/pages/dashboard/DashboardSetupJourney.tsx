import { useEffect, useState } from 'react';
import { t } from '../../../../i18n/dashboard';
import type { BrandProfile } from '../../BrandEditForm';
import type { LocationProfile } from '../../LocationEditForm';
import type { DashboardMenuTree } from '../DashboardPage';

type DashboardSetupJourneyProps = {
    brand: BrandProfile | null;
    location: LocationProfile | null;
    dashboardMenuTree: DashboardMenuTree | null;
    workspaceId?: number;
};

function qrLabel(count: number): string {
    return count === 1
        ? t('dashboard.setup.qr.activeCount', { count: String(count) })
        : t('dashboard.setup.qr.activeCount.plural', { count: String(count) });
}

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
    workspaceId,
}: DashboardSetupJourneyProps) {
    const notConnected = t('dashboard.setup.notConnected');
    const checking = t('dashboard.setup.checking');
    const unavailable = t('dashboard.setup.statusUnavailable');

    const [publicationValue, setPublicationValue] = useState<string>(notConnected);
    const [qrValue, setQrValue] = useState<string>(notConnected);

    const menuId = dashboardMenuTree?.id;
    const locationId = dashboardMenuTree?.locationId;

    useEffect(() => {
        let cancelled = false;

        (async () => {
            if (!workspaceId || !menuId || !locationId) {
                if (cancelled) return;
                setPublicationValue(notConnected);
                setQrValue(notConnected);
                return;
            }

            setPublicationValue(checking);
            setQrValue(checking);

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/menu/${menuId}/publications/current`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (response.status === 404) {
                    if (cancelled) return;
                    setPublicationValue(notConnected);
                    setQrValue(notConnected);
                    return;
                }

                if (!response.ok) {
                    if (cancelled) return;
                    setPublicationValue(unavailable);
                    setQrValue(unavailable);
                    return;
                }

                const body = (await response.json()) as { id: number };
                if (cancelled) return;
                setPublicationValue(t('dashboard.setup.published', { id: String(body.id) }));
            } catch {
                if (cancelled) return;
                setPublicationValue(unavailable);
                setQrValue(unavailable);
                return;
            }

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/brand/locations/${locationId}/qr-codes`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (!response.ok) {
                    if (cancelled) return;
                    setQrValue(unavailable);
                    return;
                }

                const body = (await response.json()) as { state: string }[];
                const activeCount = body.filter((qr) => qr.state === 'active').length;
                if (cancelled) return;
                setQrValue(activeCount > 0 ? qrLabel(activeCount) : notConnected);
            } catch {
                if (cancelled) return;
                setQrValue(unavailable);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, menuId, locationId, notConnected, checking, unavailable]);

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
            value: publicationValue,
            href: '#publication',
        },
        { key: 'qr', label: t('dashboard.setup.qr'), value: qrValue, href: '#publication' },
    ];

    return (
        <section aria-label={t('dashboard.setup.region')} className="flex flex-col gap-3">
            <h2 className="text-lg font-semibold text-fg">{t('dashboard.setup.heading')}</h2>
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-3">
                {rows.map((row) => (
                    <div key={row.key} className="flex flex-col gap-1">
                        <dt className="text-body font-medium text-fg-muted">
                            <a href={row.href} className="text-fg-link hover:underline ">
                                {row.label}
                            </a>
                        </dt>
                        <dd className="text-body text-fg">{row.value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

export default DashboardSetupJourney;
