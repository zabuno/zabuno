import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';

import { AdminShell } from '../catalog/layout/macro/AdminShell';
import { AppErrorBoundary } from '../system/AppErrorBoundary';
import { SidebarNav, type SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { DrawerPanel } from '../catalog/overlays/compound/DrawerPanel';
import { trackPageView } from '../../lib/analytics';
import { shouldInterceptNavigation } from '../../lib/navigation';

export type OpsSection<Key extends string> = {
    key: Key;
    label: string;
    icon: ReactNode;
    group: string;
};

export type OpsShellProps<Key extends string> = {
    brandName: string;
    navLabel: string;
    /** Adres kökü: `/platform` ya da `/engineering`. */
    basePath: string;
    sections: OpsSection<Key>[];
    groupLabels: Record<string, string>;
    defaultSection: Key;
    /** Üst çubuğun sağı: kardeş kabuğa geçiş ve çalışma alanına dönüş. */
    topBarEnd: ReactNode;
    render: (section: Key) => ReactNode;
};

/**
 * Operasyon kabuğu — platform ve mühendislik yüzeylerinin ORTAK gövdesi
 * (`docs/98` FF-66/67, `docs/99`).
 *
 * İki kabuk aynı dili konuşmalı: aynı sol ray, aynı üst çubuk, aynı zemin
 * tonu, aynı adres→bölüm kuralı. Bunu iki dosyaya kopyalamak, bir hafta
 * sonra iki farklı panel üretirdi. Fark yalnız BÖLÜM LİSTESİ ve adres köküdür;
 * ikisi de parametredir.
 *
 * Metronic'ten alınan şey DÜZEN: gruplu ve ikonlu dar ray, soluk uygulama
 * zemini, kartların o zeminde beyaz durması. Renk/boşluk/radius Zabuno
 * semantic token'larıdır — bileşen ham piksel bilmez (`docs/36` §5.4).
 * Bölüm adresten gelir, fragment'ten değil (`docs/38` §4).
 */
export function OpsShell<Key extends string>({
    brandName,
    navLabel,
    basePath,
    sections,
    groupLabels,
    defaultSection,
    topBarEnd,
    render,
}: OpsShellProps<Key>) {
    const keys = sections.map((section) => section.key);

    function sectionFromPath(pathname: string): Key {
        const trimmed = pathname.replace(/\/+$/, '');
        const last = trimmed.slice(trimmed.lastIndexOf('/') + 1);

        return (keys as string[]).includes(last) ? (last as Key) : defaultSection;
    }

    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [active, setActive] = useState<Key>(() => sectionFromPath(window.location.pathname));

    useEffect(() => {
        trackPageView(window.location.pathname, sectionFromPath(window.location.pathname));
        function handlePopState() {
            const section = sectionFromPath(window.location.pathname);
            setActive(section);
            trackPageView(window.location.pathname, section);
        }
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function goTo(section: Key): void {
        const href = `${basePath}/${section}`;
        if (window.location.pathname !== href) window.history.pushState({}, '', href);
        setActive(section);
        setMobileMenuOpen(false);
        trackPageView(href, section);
    }

    const groups: SidebarNavGroup[] = Object.keys(groupLabels).map((group) => ({
        key: group,
        label: groupLabels[group],
        items: sections
            .filter((section) => section.group === group)
            .map((section) => ({
                key: section.key,
                label: section.label,
                icon: section.icon,
                href: `${basePath}/${section.key}`,
                onSelect: (event) => {
                    if (!shouldInterceptNavigation(event)) return;
                    event.preventDefault();
                    goTo(section.key);
                },
            })),
    }));

    const nav = (asLandmark: boolean) => (
        <SidebarNav groups={groups} activeKey={active} label={navLabel} asLandmark={asLandmark} />
    );

    return (
        <AdminShell
            brand={{ name: brandName }}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            // Soluk zemin: kartlar bu tonun üstünde beyaz durur (Metronic "app-bg").
            className="bg-[var(--color-canvas)]"
            persistentSidebar={
                // Genişlik SABİT (FF-86). Öncesi bir BÜYÜME oranıydı (`flex` kısayolu):
                // ray, ana içerikle kalan alanı yarı yarıya paylaşıyor ve geniş
                // ekranda ekranın yarısını kaplıyordu. Kiracı kabuğunda aynı hata
                // FF-83'te düzeltilmişti; bu kabuk atlanmıştı.
                <aside className="admin-shell-sidebar flex shrink-0 grow-0 basis-[16rem] flex-col border-e border-[var(--color-border)] bg-[var(--color-surface)] px-[var(--space-3)] py-[var(--space-4)]">
                    {nav(true)}
                </aside>
            }
            navigationDrawer={
                <DrawerPanel
                    open={mobileMenuOpen}
                    onClose={() => setMobileMenuOpen(false)}
                    title={navLabel}
                >
                    {nav(false)}
                </DrawerPanel>
            }
            topBarEnd={topBarEnd}
        >
            {/* Rota düzeyinde sınır: bir bölümün çökmesi kabuğu götürmez. */}
            <AppErrorBoundary scope="route" resetKey={active}>
                <div className="flex flex-col gap-[var(--space-5)]">{render(active)}</div>
            </AppErrorBoundary>
        </AdminShell>
    );
}

export default OpsShell;
