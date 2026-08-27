import type { ReactNode } from 'react';
import { AnalyticsPage } from './AnalyticsPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <AnalyticsPage
            workspaceId={ctx.workspaceId}
            locationId={ctx.catalogLocationId ?? undefined}
        />
    );
}

const analyticsSection: WorkspaceSectionDescriptor = {
    key: 'analytics',
    path: 'analytics',
    order: 6,
    labelKey: 'workspace.shell.nav.analytics',
    render,
};

export default analyticsSection;
