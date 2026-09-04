import { Users } from '@phosphor-icons/react';
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
    icon: <Users size={18} weight="regular" />,
    permission: 'workspace.manage',
    group: 'management',
    render,
};

export default teamSection;
