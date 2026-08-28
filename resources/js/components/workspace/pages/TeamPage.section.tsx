import type { ReactNode } from 'react';
import { TeamPage } from './TeamPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <TeamPage workspaceId={ctx.workspaceId} />;
}

const teamSection: WorkspaceSectionDescriptor = {
    key: 'team',
    path: 'team',
    order: 6,
    labelKey: 'workspace.shell.nav.team',
    group: 'management',
    render,
};

export default teamSection;
