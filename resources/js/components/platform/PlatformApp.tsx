import { useEffect, useState } from 'react';

import { AdminShell } from '../catalog/layout/macro/AdminShell';
import type { SidebarNavGroup } from '../catalog/layout/compound/SidebarNav';
import { PlanManagementPage } from '../admin/pages/PlanManagementPage';
import { SubscriptionManagementPage } from '../admin/pages/SubscriptionManagementPage';
import { t } from '../../i18n/platform';

type PlatformSection = 'plans' | 'subscriptions';

function sectionFromHash(hash: string): PlatformSection {
    return hash === '#subscriptions' ? 'subscriptions' : 'plans';
}

export function PlatformApp() {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [activeSection, setActiveSection] = useState<PlatformSection>(() => sectionFromHash(window.location.hash));

    useEffect(() => {
        function handleHashChange() {
            setActiveSection(sectionFromHash(window.location.hash));
        }

        window.addEventListener('hashchange', handleHashChange);
        return () => window.removeEventListener('hashchange', handleHashChange);
    }, []);

    const navGroups: SidebarNavGroup[] = [
        {
            key: 'main',
            items: [
                {
                    key: 'plans',
                    label: t('platform.plans.region.label'),
                    href: '#plans',
                },
                {
                    key: 'subscriptions',
                    label: t('platform.subscriptions.nav.label'),
                    href: '#subscriptions',
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
                <a href="/app" className="text-sm font-medium underline">
                    {t('platform.shell.backToWorkspace')}
                </a>
            }
        >
            <h1 className="text-xl font-semibold">{t('platform.shell.heading')}</h1>
            {activeSection === 'subscriptions' ? <SubscriptionManagementPage /> : <PlanManagementPage />}
        </AdminShell>
    );
}

export default PlatformApp;
