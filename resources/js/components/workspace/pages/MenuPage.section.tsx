import { ForkKnife } from '@phosphor-icons/react';
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
        />
    );
}

const menuSection: WorkspaceSectionDescriptor = {
    key: 'menu',
    path: 'menu',
    order: 1,
    labelKey: 'workspace.shell.nav.menus',
    icon: <ForkKnife size={18} weight="regular" />,
    permission: 'menu.view',
    group: 'primary',
    aiQuickAction: true,
    render,
};

export default menuSection;
