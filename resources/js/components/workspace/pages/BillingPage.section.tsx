import type { ReactNode } from 'react';
import { BillingPage } from './BillingPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <BillingPage workspaceId={ctx.workspaceId} />;
}

const billingSection: WorkspaceSectionDescriptor = {
    key: 'billing',
    path: 'billing',
    order: 9,
    labelKey: 'workspace.shell.nav.billing',
    render,
};

export default billingSection;
