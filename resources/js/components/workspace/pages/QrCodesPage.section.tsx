import type { ReactNode } from 'react';
import { QrCodesPage } from './QrCodesPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return (
        <QrCodesPage
            workspaceId={ctx.workspaceId}
            dashboardMenuTree={ctx.dashboardMenuTree}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    );
}

const qrCodesSection: WorkspaceSectionDescriptor = {
    key: 'qr-codes',
    path: 'qr-codes',
    order: 2,
    labelKey: 'workspace.shell.nav.qrCodes',
    permission: 'qr.view',
    group: 'primary',
    render,
};

export default qrCodesSection;
