import type { ReactNode } from 'react';
import { BrandPage } from './BrandPage';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';
import type { WorkspaceSectionDescriptor } from '../shell/WorkspaceSectionRegistry';

function render(ctx: WorkspaceSectionRuntimeContext): ReactNode {
    return <BrandPage workspaceId={ctx.workspaceId} brand={ctx.brand} onSaved={ctx.onBrandSaved} />;
}

const brandSection: WorkspaceSectionDescriptor = {
    key: 'brand',
    path: 'brand',
    order: 8,
    labelKey: 'workspace.shell.nav.brand',
    catalogOnboardingPhase: 'brand-onboarding',
    render,
};

export default brandSection;
