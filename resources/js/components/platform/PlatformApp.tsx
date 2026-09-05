import { Buildings, ClipboardText, CreditCard, Key, Receipt, Users } from '@phosphor-icons/react';

import { OpsShell } from '../ops/OpsShell';
import { OpsPageHeader } from '../ops/OpsPageHeader';
import { PlanManagementPage } from '../admin/pages/PlanManagementPage';
import { SubscriptionManagementPage } from '../admin/pages/SubscriptionManagementPage';
import { ProviderCredentialsPage } from '../admin/pages/ProviderCredentialsPage';
import { TenantDetailPage } from '../admin/pages/TenantDetailPage';
import { PlatformUsersPage } from '../admin/pages/PlatformUsersPage';
import { AuditLogPage } from '../admin/pages/AuditLogPage';
import { t } from '../../i18n/platform';

type PlatformSection =
    'plans' | 'subscriptions' | 'credentials' | 'workspaces' | 'users' | 'audit-log';

/**
 * Platform yönetimi kabuğu — plan, abonelik, sağlayıcı anahtarları.
 *
 * Release readiness ve denetim izi buradan ÇIKTI: `/engineering`
 * (`docs/98` FF-66). Aynı kişi olabilir, aynı iş değil.
 *
 * Gövde `OpsShell`'dir (`docs/50`): mühendislik kabuğuyla aynı ray, aynı üst
 * çubuk, aynı zemin. Bölüm adresten gelir, fragment'ten değil (`docs/38` §4);
 * bilinmeyen bölüm varsayılana düşer.
 */
export function PlatformApp() {
    return (
        <OpsShell<PlatformSection>
            brandName={t('platform.shell.brand')}
            navLabel={t('platform.shell.navLabel')}
            basePath="/platform"
            defaultSection="plans"
            groupLabels={{
                /*
                    GÖZETİM grubu (`docs/122` Y2), ticaretten AYRI durur:
                    plan/abonelik/anahtar bir satış işidir, kiracıya bakmak
                    bir destek işidir. Aynı rayda karışmaları, ikisini de
                    yanlış yerde aratırdı.
                */
                oversight: t('platform.shell.group.oversight'),
                commercial: t('platform.shell.group.commercial'),
                integrations: t('platform.shell.group.integrations'),
            }}
            sections={[
                {
                    key: 'workspaces',
                    label: t('platform.tenants.nav.label'),
                    icon: <Buildings aria-hidden="true" size={18} />,
                    group: 'oversight',
                },
                {
                    key: 'users',
                    label: t('platform.users.nav.label'),
                    icon: <Users aria-hidden="true" size={18} />,
                    group: 'oversight',
                },
                {
                    key: 'audit-log',
                    label: t('platform.auditLog.nav.label'),
                    icon: <ClipboardText aria-hidden="true" size={18} />,
                    group: 'oversight',
                },
                {
                    key: 'plans',
                    label: t('platform.plans.region.label'),
                    icon: <Receipt aria-hidden="true" size={18} />,
                    group: 'commercial',
                },
                {
                    key: 'subscriptions',
                    label: t('platform.subscriptions.nav.label'),
                    icon: <CreditCard aria-hidden="true" size={18} />,
                    group: 'commercial',
                },
                {
                    key: 'credentials',
                    label: t('platform.credentials.nav.label'),
                    icon: <Key aria-hidden="true" size={18} />,
                    group: 'integrations',
                },
            ]}
            topBarEnd={
                <>
                    <a href="/engineering" className="text-body font-medium underline">
                        {t('platform.shell.toEngineering')}
                    </a>
                    <a href="/app" className="text-body font-medium underline">
                        {t('platform.shell.backToWorkspace')}
                    </a>
                </>
            }
            render={(section) => {
                const crumb = { label: t('platform.shell.heading') };

                if (section === 'workspaces') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('platform.tenants.region.label')}
                                crumbs={[crumb, { label: t('platform.tenants.nav.label') }]}
                            />
                            <TenantDetailPage />
                        </>
                    );
                }

                if (section === 'users') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('platform.users.region.label')}
                                crumbs={[crumb, { label: t('platform.users.nav.label') }]}
                            />
                            <PlatformUsersPage />
                        </>
                    );
                }

                if (section === 'audit-log') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('platform.auditLog.region.label')}
                                crumbs={[crumb, { label: t('platform.auditLog.nav.label') }]}
                            />
                            <AuditLogPage />
                        </>
                    );
                }

                if (section === 'subscriptions') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('platform.subscriptions.nav.label')}
                                crumbs={[crumb, { label: t('platform.subscriptions.nav.label') }]}
                            />
                            <SubscriptionManagementPage />
                        </>
                    );
                }

                if (section === 'credentials') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('platform.credentials.region.label')}
                                description={t('platform.credentials.intro')}
                                crumbs={[crumb, { label: t('platform.credentials.nav.label') }]}
                            />
                            <ProviderCredentialsPage />
                        </>
                    );
                }

                return (
                    <>
                        <OpsPageHeader
                            title={t('platform.plans.region.label')}
                            crumbs={[crumb, { label: t('platform.plans.region.label') }]}
                        />
                        <PlanManagementPage />
                    </>
                );
            }}
        />
    );
}

export default PlatformApp;
