import { useEffect, useState } from 'react';

import { AdminShell } from '../catalog/layout/macro/AdminShell';
import type { SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { PlanManagementPage } from '../admin/pages/PlanManagementPage';
import { SubscriptionManagementPage } from '../admin/pages/SubscriptionManagementPage';
import { trackPageView } from '../../lib/analytics';
import { shouldInterceptNavigation } from '../../lib/navigation';
import { t } from '../../i18n/platform';

type PlatformSection = 'plans' | 'subscriptions';

/**
 * Adresten bölüm. Fragment DEĞİL — `docs/38` §4: fragment sunucuya hiç
 * gönderilmez, dolayısıyla bu panelde hangi ekranın kullanıldığı ne sunucu
 * günlüğünde ne de ölçüm aracında görünürdü.
 *
 * Bilinmeyen bir bölüm varsayılana düşer; tam olarak bilinmeyen bir
 * fragment'in eskiden yaptığı gibi.
 */
function sectionFromPath(pathname: string): PlatformSection {
    return pathname.replace(/\/+$/, '').endsWith('/subscriptions') ? 'subscriptions' : 'plans';
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
            ],
        },
    ];

    return (
        <AdminShell
            brand={{ name: t('platform.shell.brand') }}
            navGroups={navGroups}
            navLabel={t('platform.shell.navLabel')}
            activeNavKey={activeSection}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            onCloseMobileMenu={() => setMobileMenuOpen(false)}
            topBarEnd={
                <a href="/app" className="text-body font-medium underline">
                    {t('platform.shell.backToWorkspace')}
                </a>
            }
        >
            <h1 className="text-xl font-semibold">{t('platform.shell.heading')}</h1>
            {activeSection === 'subscriptions' ? (
                <SubscriptionManagementPage />
            ) : (
                <PlanManagementPage />
            )}
        </AdminShell>
    );
}

export default PlatformApp;
