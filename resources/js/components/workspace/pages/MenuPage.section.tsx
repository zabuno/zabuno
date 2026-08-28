import type { ReactNode } from 'react';
import { MenuPage } from './MenuPage';
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
            onRetry={ctx.onRetryCatalog}
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
};

export default menuSection;
