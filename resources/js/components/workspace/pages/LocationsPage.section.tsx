import { MapPin } from '@phosphor-icons/react';
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
            /*
                Form açık/kapalı durumu ADRESTE durur: `locations/new`.
                Bileşen içinde tutulsaydı, global "Oluştur" menüsü kullanıcıyı
                listeye götürür ve tıkladığı şeyi ekranda ayrıca aratırdı.
            */
            addingLocation={ctx.subPath === 'new'}
            onToggleAddLocation={(adding) =>
                ctx.onNavigateToSection(adding ? 'locations/new' : 'locations')
            }
        />
    );
}

const locationsSection: WorkspaceSectionDescriptor = {
    key: 'locations',
    path: 'locations',
    order: 4,
    labelKey: 'workspace.shell.nav.locations',
    icon: <MapPin size={18} weight="regular" />,
    group: 'management',
    aiQuickAction: true,
    catalogOnboardingPhase: 'location-onboarding',
    render,
};

export default locationsSection;
