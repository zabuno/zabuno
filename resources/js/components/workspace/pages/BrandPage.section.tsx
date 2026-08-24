import type { ReactNode } from 'react';
import { BrandPage } from './BrandPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <BrandPage workspaceId={ctx.workspaceId} brand={ctx.brand} onSaved={ctx.onBrandSaved} />;
}

const brandSection: WorkspaceSectionDescriptor = {
    key: 'brand',
    hash: '#brand',
    order: 1,
    labelKey: 'workspace.shell.nav.brand',
    render,
};

export default brandSection;
