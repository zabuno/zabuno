import type { ReactNode } from 'react';
import { LocationsPage } from './LocationsPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <LocationsPage
            workspaceId={ctx.workspaceId}
            locations={ctx.locationProfiles}
            selectedLocationId={ctx.catalogLocationId}
            onSelectLocation={ctx.onSelectLocation}
            onLocationSaved={ctx.onLocationSaved}
            onLocationCreated={ctx.onLocationCreated}
        />
    );
}

const locationsSection: WorkspaceSectionDescriptor = {
    key: 'locations',
    hash: '#locations',
    order: 2,
    labelKey: 'workspace.shell.nav.locations',
    aiQuickAction: true,
    render,
};

export default locationsSection;
