import { CreditCard, Key, Receipt } from '@phosphor-icons/react';

import { OpsShell } from '../ops/OpsShell';
import { OpsPageHeader } from '../ops/OpsPageHeader';
import { PlanManagementPage } from '../admin/pages/PlanManagementPage';
import { SubscriptionManagementPage } from '../admin/pages/SubscriptionManagementPage';
import { ProviderCredentialsPage } from '../admin/pages/ProviderCredentialsPage';
import { t } from '../../i18n/platform';

type PlatformSection = 'plans' | 'subscriptions' | 'credentials';

/**
 * Platform yönetimi kabuğu — plan, abonelik, sağlayıcı anahtarları.
 *
 * Release readiness ve denetim izi buradan ÇIKTI: `/engineering`
 * (`docs/98` FF-66). Aynı kişi olabilir, aynı iş değil.
 *
 * Gövde `OpsShell`'dir (`docs/99`): mühendislik kabuğuyla aynı ray, aynı üst
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
                commercial: t('platform.shell.group.commercial'),
                integrations: t('platform.shell.group.integrations'),
            }}
            sections={[
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
