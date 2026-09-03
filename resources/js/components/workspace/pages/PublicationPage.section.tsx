import type { ReactNode } from 'react';
import { PublicationPage } from './PublicationPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <PublicationPage workspaceId={ctx.workspaceId} dashboardMenuTree={ctx.dashboardMenuTree} />
    );
}

const publicationSection: WorkspaceSectionDescriptor = {
    key: 'publication',
    path: 'publication',
    order: 10,
    labelKey: 'workspace.shell.nav.publication',
    permission: 'menu.view',
    aiQuickAction: true,
    render,
};

export default publicationSection;
