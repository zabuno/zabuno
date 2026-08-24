import type { ReactNode } from 'react';
import { MenuPage } from './MenuPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <MenuPage
            workspaceId={ctx.workspaceId}
            locationId={ctx.catalogLocationId}
            onTreeChange={ctx.onMenuTreeChange}
        />
    );
}

const menuSection: WorkspaceSectionDescriptor = {
    key: 'menu',
    hash: '#menu',
    order: 3,
    labelKey: 'workspace.shell.nav.menu',
    aiQuickAction: true,
    render,
};

export default menuSection;
