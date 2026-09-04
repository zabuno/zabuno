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
    onSelectLocations,
}: WorkspaceBreadcrumbsProps) {
    const items: BreadcrumbItem[] = [
        /*
            Çalışma alanı adı bir HEDEF DEĞİL, bağlamdır (sahibin kararı,
            2026-09-04). Önceden buraya tıklamak "çalışma alanı seç"
            sayfasına götürüyordu; o sayfa kaldırıldı ve seçim kenar
            çubuğunun tepesindeki seçiciye taşındı. Hiçbir yere gitmeyen bir
            bağlantı bırakmak, tıklayanı boşluğa düşürürdü.
        */
        {
            key: 'workspace',
            label: workspaceName,
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
