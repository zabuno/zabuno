import type { ReactNode } from 'react';
import { MenuPage } from './MenuPage';
import { MenuInspector } from './menu/MenuInspector';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <MenuPage
            workspaceId={ctx.workspaceId}
            catalogPhase={ctx.catalogPhase}
            locationId={ctx.catalogLocationId}
            onTreeChange={ctx.onMenuTreeChange}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    );
}

function renderInspector(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <MenuInspector
            workspaceId={ctx.workspaceId}
            menuTree={ctx.dashboardMenuTree}
            locationName={ctx.location?.display_name ?? null}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    );
}

const menuSection: WorkspaceSectionDescriptor = {
    key: 'menu',
    path: 'menu',
    order: 1,
    labelKey: 'workspace.shell.nav.menus',
    group: 'primary',
    aiQuickAction: true,
    render,
    renderInspector,
};

export default menuSection;
