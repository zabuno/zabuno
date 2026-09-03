import { ClipboardText, ClockCounterClockwise } from '@phosphor-icons/react';

import { OpsShell } from '../ops/OpsShell';
import { OpsPageHeader } from '../ops/OpsPageHeader';
import { ReleaseReadinessPage } from '../admin/pages/ReleaseReadinessPage';
import { AiAuditPage } from '../admin/pages/AiAuditPage';
import { t } from '../../i18n/platform';

type EngineeringSection = 'release-readiness' | 'ai-audit';

/**
 * Mühendislik kabuğu — `docs/50` §3'ün son üyesi, `docs/98` FF-66.
 *
 * "Bu sürüm çıkabilir mi" ve "hangi anahtarı kim yazdı" soruları plan
 * fiyatıyla aynı kenar çubuğunda durunca ikisi de birbirinin gürültüsüdür.
 * Yetki aynı (superadmin), İŞ farklı; kabuk da ayrı (`docs/69` madde 3).
 */
export function EngineeringApp() {
    return (
        <OpsShell<EngineeringSection>
            brandName={t('engineering.shell.brand')}
            navLabel={t('engineering.shell.navLabel')}
            basePath="/engineering"
            defaultSection="release-readiness"
            groupLabels={{ evidence: t('engineering.shell.group.evidence') }}
            sections={[
                {
                    key: 'release-readiness',
                    label: t('platform.releaseReadiness.nav.label'),
                    icon: <ClipboardText aria-hidden="true" size={18} />,
                    group: 'evidence',
                },
                {
                    key: 'ai-audit',
                    label: t('engineering.aiAudit.nav.label'),
                    icon: <ClockCounterClockwise aria-hidden="true" size={18} />,
                    group: 'evidence',
                },
            ]}
            topBarEnd={
                <>
                    <a href="/platform" className="text-body font-medium underline">
                        {t('engineering.shell.toPlatform')}
                    </a>
                    <a href="/app" className="text-body font-medium underline">
                        {t('platform.shell.backToWorkspace')}
                    </a>
                </>
            }
            render={(section) =>
                section === 'ai-audit' ? (
                    <>
                        <OpsPageHeader
                            title={t('engineering.aiAudit.heading')}
                            description={t('engineering.aiAudit.description')}
                            crumbs={[
                                { label: t('engineering.shell.heading') },
                                { label: t('engineering.aiAudit.nav.label') },
                            ]}
                        />
                        <AiAuditPage />
                    </>
                ) : (
                    <>
                        <OpsPageHeader
                            title={t('platform.releaseReadiness.heading')}
                            description={t('platform.releaseReadiness.operational.description')}
                            crumbs={[
                                { label: t('engineering.shell.heading') },
                                { label: t('platform.releaseReadiness.nav.label') },
                            ]}
                        />
                        <ReleaseReadinessPage />
                    </>
                )
            }
        />
    );
}

export default EngineeringApp;
