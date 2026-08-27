import type { ReactNode } from 'react';
import { LaunchReadinessPage } from './LaunchReadinessPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <LaunchReadinessPage workspaceId={ctx.workspaceId} />;
}

const launchReadinessSection: WorkspaceSectionDescriptor = {
    key: 'security',
    path: 'security',
    order: 9,
    labelKey: 'workspace.shell.nav.launchReadiness',
    render,
};

export default launchReadinessSection;
