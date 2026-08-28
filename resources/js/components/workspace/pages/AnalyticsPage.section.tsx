import type { ReactNode } from 'react';
import { AnalyticsPage } from './AnalyticsPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <AnalyticsPage
            workspaceId={ctx.workspaceId}
            locationId={ctx.catalogLocationId ?? undefined}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    );
}

const analyticsSection: WorkspaceSectionDescriptor = {
    key: 'analytics',
    path: 'analytics',
    order: 3,
    labelKey: 'workspace.shell.nav.insights',
    group: 'primary',
    render,
};

export default analyticsSection;
