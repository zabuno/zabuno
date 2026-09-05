import { useEffect, type ReactNode } from 'react';
import type { WorkspaceSectionRuntimeContext } from '../WorkspaceApp';

export type WorkspaceCatalogOnboardingPhase = 'brand-onboarding' | 'location-onboarding';

/**
 * Kenar çubuğunun grupları — `docs/50` §5'teki hedef bilgi mimarisi.
 *
 * Gruplar keyfi değil, kullanıcının işine göre: `primary` her gün gidilen
 * yerler, `management` ara sıra düzenlenen kayıtlar, `utility` nadiren açılan
 * ayarlar.
 *
 * Grubu OLMAYAN bölüm kenar çubuğunda listelenmez ama adresi çalışır. Bu
 * kasıtlı bir üçüncü hâldir: Brand, Billing ve Publication günlük operasyon
 * değildir ve ana menüde kalıcı yer işgal etmemeleri gerekir — ama
 * ulaşılamaz da olmamalılar. Marka ayarları Settings'in içinden, yayınlama
 * ise menünün yanından açılır.
 */
export type WorkspaceNavGroupKey = 'primary' | 'management' | 'utility';

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
    /**
     * Kenar çubuğu grubu. `undefined` ise bölüm LİSTELENMEZ — adresi yine
     * çalışır ve başka bir sayfadan açılır.
     */
    group?: WorkspaceNavGroupKey;
    /**
     * Bölümü GÖRMEK için gereken izin (`docs/98` FF-74). Sunucunun
     * `workspace-context` ile verdiği listede yoksa bölüm kenar çubuğunda,
     * omnibox'ta ve oluştur menüsünde ÇİZİLMEZ — Editor 403 görmez.
     * Tanımsızsa bölüm herkese açıktır (workspace.view yeter).
     */
    permission?: string;
    /** Gezinti ikonu (Phosphor), `aria-hidden`; kenar çubuğu, çekmece ve omnibox aynı kayıttan okur (`docs/102`). */
    icon?: ReactNode;
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

/**
 * Bir bölümün tam adresi. Bağlantılar bunu kullanır.
 *
 * `subPath` sayfa İÇİ gezinti içindir (`settings/brand` gibi). Bölüm
 * çözümlemesi yalnız ilk parçaya bakar; alt parçayı sayfa kendisi okur.
 * Böylece "Settings içinde Brand" gerçek bir adres olur — paylaşılabilir,
 * yer imine eklenebilir ve tarayıcı geçmişinde anlamlıdır.
 */
export function sectionHref(workspaceSlug: string, sectionKey: string, subPath?: string): string {
    const descriptor =
        SECTION_DESCRIPTORS.find((candidate) => candidate.key === sectionKey) ??
        DASHBOARD_SECTION_DESCRIPTOR;

    const suffix = subPath !== undefined && subPath !== '' ? `/${subPath}` : '';

    return `/app/${workspaceSlug}/${descriptor.path}${suffix}`;
}

/** Adresteki bölüm-içi parça (`/app/x/settings/brand` → `brand`). */
export function resolveSubPath(pathname: string): string {
    const segments = pathname.split('/').filter((segment) => segment !== '');

    return segments.length >= 4 ? segments[3] : '';
}

export function resolveSectionDescriptorForOnboardingPhase(
    phase: WorkspaceCatalogOnboardingPhase,
): WorkspaceSectionDescriptor | null {
    return (
        SECTION_DESCRIPTORS.find((descriptor) => descriptor.catalogOnboardingPhase === phase) ??
        null
    );
}

/**
 * Kullanıcının GERÇEKTEN görebildiği ilk listelenen bölüm.
 *
 * "Listelenen" şartı önemlidir: grubu olmayan bölümler (Ayarlar, Profil,
 * Yayınlama) başka ekranlardan açılan hedeflerdir ve bir varış noktası
 * olarak seçilmeleri kullanıcıyı hiç istemediği bir yere bırakırdı.
 */
function firstVisibleSection(
    ctx: WorkspaceSectionRuntimeContext,
): WorkspaceSectionDescriptor | null {
    return (
        SECTION_DESCRIPTORS.find(
            (descriptor) =>
                descriptor.group !== undefined &&
                (descriptor.permission === undefined || ctx.can(descriptor.permission)),
        ) ?? null
    );
}

/**
 * İzni olmayan bölüm istendiğinde kullanıcıyı görebildiği bölüme TAŞIR.
 *
 * Çizim sırasında yönlendirme yapılamaz (yan etki), o yüzden iş bir etkiye
 * bırakılır ve o kare boyunca hiçbir şey çizilmez. `onNavigateToSection`
 * hem adresi hem aktif bölümü günceller; yani kırıntı yolu, kenar çubuğu
 * vurgusu ve tarayıcı geçmişi de doğru yeri gösterir. Yalnız içeriği
 * değiştirmek, "Panom" yazan bir başlığın altında Menüler ekranı bırakırdı.
 */
function SectionAccessRedirect({
    ctx,
    target,
}: {
    ctx: WorkspaceSectionRuntimeContext;
    target: WorkspaceSectionDescriptor;
}): ReactNode {
    useEffect(() => {
        ctx.onNavigateToSection(target.key);
    }, [ctx, target]);

    return null;
}

export function renderActiveSection(
    activeKey: string,
    ctx: WorkspaceSectionRuntimeContext,
): ReactNode {
    const descriptor =
        SECTION_DESCRIPTORS.find((candidate) => candidate.key === activeKey) ??
        DASHBOARD_SECTION_DESCRIPTOR;

    /*
        İZİN KAPISI ÇİZİMDE DE DURUR (`docs/98` FF-74, `docs/109` §6.4).

        Bölümü kenar çubuğundan çıkarmak yetmiyordu: bilinmeyen adres
        Panom'a düşüyor ve giriş sonrası varış noktası da orası. Panom artık
        `analytics.view` istiyor; ölçüm göremeyen Mutfak rolündeki bir aşçı,
        listede olmayan bir ekranın üstünde açılırdı — kenar çubuğunda hiçbir
        şey vurgulanmaz, ekranda boş kartlar durur ve oradan çıkmanın yolu
        görünmezdi.

        Sunucunun kararının yerine geçmez ve bir güvenlik sınırı DEĞİLDİR:
        her uç yetkiyi kendisi doğrular. Buradaki iş yalnız kullanıcıyı boş
        bir ekranda bırakmamaktır.
    */
    if (descriptor.permission !== undefined && !ctx.can(descriptor.permission)) {
        const target = firstVisibleSection(ctx);

        if (target !== null && target.key !== descriptor.key) {
            return <SectionAccessRedirect ctx={ctx} target={target} />;
        }
    }

    return descriptor.render(ctx);
}
