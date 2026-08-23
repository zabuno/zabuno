import { Breadcrumbs, type BreadcrumbItem } from '../../catalog/navigation/compound/Breadcrumbs';

export type WorkspaceBreadcrumbsProps = {
    workspaceName: string;
    locationDisplayName: string | null;
    sectionLabel: string;
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
            href: '#locations',
            onSelect: () => {
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
