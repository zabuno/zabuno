import type { ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

export type WorkspaceSectionDescriptor = {
    key: string;
    hash: `#${string}`;
    order: number;
    labelKey: string;
    aiQuickAction?: boolean;
    render: (ctx: WorkspaceSectionRuntimeContext) => ReactNode;
};

type SectionModule = { default: WorkspaceSectionDescriptor };

const sectionModules = import.meta.glob('../pages/*.section.tsx', {
    eager: true,
}) as Record<string, SectionModule>;

const rawDescriptors: WorkspaceSectionDescriptor[] = Object.values(sectionModules).map(
    (module) => module.default,
);

function assertNoDuplicateRegistrations(descriptors: WorkspaceSectionDescriptor[]): void {
    const seenKeys = new Set<string>();
    const seenHashes = new Set<string>();
    const seenOrders = new Set<number>();

    for (const descriptor of descriptors) {
        if (seenKeys.has(descriptor.key)) {
            throw new Error(`WorkspaceSectionRegistry: duplicate section key "${descriptor.key}"`);
        }

        if (seenHashes.has(descriptor.hash)) {
            throw new Error(
                `WorkspaceSectionRegistry: duplicate section hash "${descriptor.hash}"`,
            );
        }

        if (seenOrders.has(descriptor.order)) {
            throw new Error(
                `WorkspaceSectionRegistry: duplicate section order "${descriptor.order}"`,
            );
        }

        seenKeys.add(descriptor.key);
        seenHashes.add(descriptor.hash);
        seenOrders.add(descriptor.order);
    }
}

function assertDashboardIsRegistered(descriptors: WorkspaceSectionDescriptor[]): void {
    const hasDashboard = descriptors.some((descriptor) => descriptor.key === 'dashboard');

    if (!hasDashboard) {
        throw new Error('WorkspaceSectionRegistry: missing required "dashboard" section');
    }
}

assertNoDuplicateRegistrations(rawDescriptors);
assertDashboardIsRegistered(rawDescriptors);

export const SECTION_DESCRIPTORS: readonly WorkspaceSectionDescriptor[] = [...rawDescriptors].sort(
    (a, b) => a.order - b.order,
);

const DASHBOARD_SECTION_DESCRIPTOR = SECTION_DESCRIPTORS.find(
    (descriptor) => descriptor.key === 'dashboard',
) as WorkspaceSectionDescriptor;

export function resolveSectionDescriptorByHash(hash: string): WorkspaceSectionDescriptor {
    const match = SECTION_DESCRIPTORS.find((descriptor) => descriptor.hash === hash);

    if (match) {
        return match;
    }

    // Unknown hash: fallback to the 'dashboard' section descriptor.
    return DASHBOARD_SECTION_DESCRIPTOR;
}

export function resolveSectionKeyFromHash(hash: string): string {
    return resolveSectionDescriptorByHash(hash).key;
}

export function renderActiveSection(
    activeKey: string,
    ctx: WorkspaceSectionRuntimeContext,
): ReactNode {
    const descriptor =
        SECTION_DESCRIPTORS.find((candidate) => candidate.key === activeKey) ??
        DASHBOARD_SECTION_DESCRIPTOR;

    return descriptor.render(ctx);
}
