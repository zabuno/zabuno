import type { ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

export type WorkspaceCatalogOnboardingPhase = 'brand-onboarding' | 'location-onboarding';

export type WorkspaceSectionDescriptor = {
    key: string;
    /**
     * Adresteki yol parçası — fragment DEĞİL.
     *
     * Öncesinde `hash: '#menu'` idi ve gezinti fragment ile yapılıyordu.
     * `docs/38` §4 bunu reddediyor: fragment sunucuya hiç gönderilmez, yani
     * hangi ekranın kullanıldığı ölçülemez, bir ekranın bağlantısı
     * paylaşılamaz, tarayıcı geçmişi anlamlı olmaz.
     */
    path: string;
    order: number;
    labelKey: string;
    aiQuickAction?: boolean;
    catalogOnboardingPhase?: WorkspaceCatalogOnboardingPhase;
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
    const seenPaths = new Set<string>();
    const seenOrders = new Set<number>();

    for (const descriptor of descriptors) {
        if (seenKeys.has(descriptor.key)) {
            throw new Error(`WorkspaceSectionRegistry: duplicate section key "${descriptor.key}"`);
        }

        if (seenPaths.has(descriptor.path)) {
            throw new Error(
                `WorkspaceSectionRegistry: duplicate section path "${descriptor.path}"`,
            );
        }

        if (seenOrders.has(descriptor.order)) {
            throw new Error(
                `WorkspaceSectionRegistry: duplicate section order "${descriptor.order}"`,
            );
        }

        seenKeys.add(descriptor.key);
        seenPaths.add(descriptor.path);
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

/**
 * Adres yolundan bölümü çözer.
 *
 * Beklenen biçim `/app/{workspace}/{section}`. Bölüm parçası yoksa ya da
 * tanınmıyorsa dashboard'a düşülür — bilinmeyen bir adres kullanıcıyı boş
 * bir ekranda bırakmamalı.
 *
 * Sunucu bölüm adını DOĞRULAMAZ; aynı kabuğu döndürür ve karar buraya
 * kalır. Sunucuda ikinci bir bölüm listesi tutmak, iki listenin ayrışacağı
 * bir gün yaratırdı.
 */
export function resolveSectionDescriptorByPath(pathname: string): WorkspaceSectionDescriptor {
    const segments = pathname.split('/').filter((segment) => segment !== '');
    const section = segments.length >= 3 ? segments[2] : '';
    const match = SECTION_DESCRIPTORS.find((descriptor) => descriptor.path === section);

    return match ?? DASHBOARD_SECTION_DESCRIPTOR;
}

export function resolveSectionKeyFromPath(pathname: string): string {
    return resolveSectionDescriptorByPath(pathname).key;
}

/** Bir bölümün tam adresi. Bağlantılar bunu kullanır. */
export function sectionHref(workspaceSlug: string, sectionKey: string): string {
    const descriptor =
        SECTION_DESCRIPTORS.find((candidate) => candidate.key === sectionKey) ??
        DASHBOARD_SECTION_DESCRIPTOR;

    return `/app/${workspaceSlug}/${descriptor.path}`;
}

export function resolveSectionDescriptorForOnboardingPhase(
    phase: WorkspaceCatalogOnboardingPhase,
): WorkspaceSectionDescriptor | null {
    return (
        SECTION_DESCRIPTORS.find((descriptor) => descriptor.catalogOnboardingPhase === phase) ??
        null
    );
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
