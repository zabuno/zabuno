import { Breadcrumbs, type BreadcrumbItem } from '../../catalog/navigation/compound/Breadcrumbs';
import { shouldInterceptNavigation } from '../../../lib/navigation';

export type WorkspaceBreadcrumbsProps = {
    workspaceName: string;
    locationDisplayName: string | null;
    sectionLabel: string;
    /**
     * Konumlar ekranının GERÇEK adresi.
     *
     * Kırıntı bunu kendisi üretemez: adres workspace slug'ını içerir ve bu
     * bileşen workspace'i bilmez — bilmesi de istenmez (docs/35: kırıntı
     * yalnız props ile çalışır, veri çekmez, rota sahibi değildir).
     */
    locationsHref: string;
    onSwitchWorkspace: () => void;
    onSelectLocations: () => void;
};

/**
 * Workspace-shell compound: composes the catalog Breadcrumbs inside
 * <main> above the page (docs/35 workspace-shell boundary). Orchestrated
 * entirely from props — no fetch or route ownership.
 */
export function WorkspaceBreadcrumbs({
    workspaceName,
    locationDisplayName,
    sectionLabel,
    locationsHref,
    onSwitchWorkspace,
    onSelectLocations,
}: WorkspaceBreadcrumbsProps) {
    const items: BreadcrumbItem[] = [
        {
            key: 'workspace',
            label: workspaceName,
            href: '#',
            onSelect: (event) => {
                event.preventDefault();
                onSwitchWorkspace();
            },
        },
    ];

    if (locationDisplayName !== null) {
        items.push({
            key: 'location',
            label: locationDisplayName,
            href: locationsHref,
            onSelect: (event) => {
                if (!shouldInterceptNavigation(event)) {
                    return;
                }

                event.preventDefault();
                onSelectLocations();
            },
        });
    }

    items.push({
        key: 'section',
        label: sectionLabel,
    });

    return <Breadcrumbs items={items} />;
}
