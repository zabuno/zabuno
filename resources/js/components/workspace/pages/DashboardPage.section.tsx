import { House } from '@phosphor-icons/react';
import type { ReactNode } from 'react';
import { DashboardPage } from './DashboardPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

export type { DashboardMenuTree } from './DashboardPage';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <DashboardPage
            noviceHome={ctx.features['novice-home'] !== false}
            dashboardMenuTree={ctx.dashboardMenuTree}
            brand={ctx.brand}
            location={ctx.location}
            workspaceId={ctx.workspaceId}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    );
}

const dashboardSection: WorkspaceSectionDescriptor = {
    key: 'dashboard',
    path: 'dashboard',
    order: 0,
    labelKey: 'workspace.shell.nav.home',
    icon: <House size={18} weight="regular" />,
    group: 'primary',
    aiQuickAction: true,
    render,
};

export default dashboardSection;
