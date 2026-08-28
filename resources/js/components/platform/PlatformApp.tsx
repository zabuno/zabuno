import { useEffect, useState } from 'react';

import { AdminShell } from '../catalog/layout/macro/AdminShell';
import { AppErrorBoundary } from '../system/AppErrorBoundary';
import { SidebarNav } from '../catalog/layout/compound/SidebarNav';
import { DrawerPanel } from '../catalog/overlays/compound/DrawerPanel';
import type { SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { PlanManagementPage } from '../admin/pages/PlanManagementPage';
import { SubscriptionManagementPage } from '../admin/pages/SubscriptionManagementPage';
import { ReleaseReadinessPage } from '../admin/pages/ReleaseReadinessPage';
import { trackPageView } from '../../lib/analytics';
import { shouldInterceptNavigation } from '../../lib/navigation';
import { t } from '../../i18n/platform';

type PlatformSection = 'plans' | 'subscriptions' | 'release-readiness';

/**
 * Adresten bölüm. Fragment DEĞİL — `docs/38` §4: fragment sunucuya hiç
 * gönderilmez, dolayısıyla bu panelde hangi ekranın kullanıldığı ne sunucu
 * günlüğünde ne de ölçüm aracında görünürdü.
 *
 * Bilinmeyen bir bölüm varsayılana düşer; tam olarak bilinmeyen bir
 * fragment'in eskiden yaptığı gibi.
 */
function sectionFromPath(pathname: string): PlatformSection {
    const trimmed = pathname.replace(/\/+$/, '');

    if (trimmed.endsWith('/subscriptions')) return 'subscriptions';
    if (trimmed.endsWith('/release-readiness')) return 'release-readiness';

    return 'plans';
}

function platformSectionHref(section: PlatformSection): string {
    return `/platform/${section}`;
}

export function PlatformApp() {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeSection, setActiveSection] = useState<PlatformSection>(() =>
        sectionFromPath(window.location.pathname),
    );

    useEffect(() => {
        trackPageView(window.location.pathname, sectionFromPath(window.location.pathname));
    }, []);

    useEffect(() => {
        function handlePopState() {
            const section = sectionFromPath(window.location.pathname);
            setActiveSection(section);
            trackPageView(window.location.pathname, section);
        }

        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    // Tek gezinti girişi: adres ve ekran birlikte değişir.
    function goToSection(section: PlatformSection): void {
        const href = platformSectionHref(section);

        if (window.location.pathname !== href) {
            window.history.pushState({}, '', href);
        }

        setActiveSection(section);
        trackPageView(href, section);
    }

    const navGroups: SidebarNavGroup[] = [
        {
            key: 'main',
            items: [
                {
                    key: 'plans',
                    label: t('platform.plans.region.label'),
                    href: platformSectionHref('plans'),
                    onSelect: (event) => {
                        if (!shouldInterceptNavigation(event)) {
                            return;
                        }

                        event.preventDefault();
                        goToSection('plans');
                    },
                },
                {
                    key: 'subscriptions',
                    label: t('platform.subscriptions.nav.label'),
                    href: platformSectionHref('subscriptions'),
                    onSelect: (event) => {
                        if (!shouldInterceptNavigation(event)) {
                            return;
                        }

                        event.preventDefault();
                        goToSection('subscriptions');
                    },
                },
                {
                    key: 'release-readiness',
                    label: t('platform.releaseReadiness.nav.label'),
                    href: platformSectionHref('release-readiness'),
                    onSelect: (event) => {
                        if (!shouldInterceptNavigation(event)) {
                            return;
                        }

                        event.preventDefault();
                        goToSection('release-readiness');
                    },
                },
            ],
        },
    ];

    return (
        <AdminShell
            brand={{ name: t('platform.shell.brand') }}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            /*
                Platform yönetimi tek pakettir; cihaz ayrımı yoktur ve
                kabuk parçalarını kendisi verir. `AdminShell` artık gezinti
                verisini bilmez — cihaza özgü kabuğun tenant tarafında ayrı
                paketlere bölünebilmesi bunu gerektiriyordu (docs/54).
            */
            persistentSidebar={
                <aside className="admin-shell-sidebar flex flex-[1_1_17rem] flex-col border-e border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]">
                    <SidebarNav
                        groups={navGroups}
                        activeKey={activeSection}
                        label={t('platform.shell.navLabel')}
                    />
                </aside>
            }
            navigationDrawer={
                <DrawerPanel
                    open={mobileMenuOpen}
                    onClose={() => setMobileMenuOpen(false)}
                    title={t('platform.shell.navLabel')}
                >
                    <SidebarNav
                        groups={navGroups}
                        activeKey={activeSection}
                        label={t('platform.shell.navLabel')}
                        asLandmark={false}
                    />
                </DrawerPanel>
            }
            topBarEnd={
                <a href="/app" className="text-body font-medium underline">
                    {t('platform.shell.backToWorkspace')}
                </a>
            }
        >
            <h1 className="text-title font-semibold">{t('platform.shell.heading')}</h1>
            {/*
                Rota düzeyinde sınır: bir platform sayfasının çökmesi kabuğu
                götürmemeli. `resetKey` bölüm anahtarıdır — başka bölüme
                geçildiğinde sınır sıfırlanır, aksi hâlde React bozuk ağacı
                kalıcı sayar ve hata ekranı sonraki bölümde de kalırdı.
            */}
            <AppErrorBoundary scope="route" resetKey={activeSection}>
                {activeSection === 'subscriptions' ? <SubscriptionManagementPage /> : null}
                {activeSection === 'release-readiness' ? <ReleaseReadinessPage /> : null}
                {activeSection === 'plans' ? <PlanManagementPage /> : null}
            </AppErrorBoundary>
        </AdminShell>
    );
}

export default PlatformApp;
