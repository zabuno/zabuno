import type { ReactNode } from 'react';
import { MediaPage } from './MediaPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <MediaPage workspaceId={ctx.workspaceId} />;
}

const mediaSection: WorkspaceSectionDescriptor = {
    key: 'media',
    path: 'media',
    order: 5,
    labelKey: 'workspace.shell.nav.media',
    group: 'management',
    render,
};

export default mediaSection;
