import { ClipboardText, ClockCounterClockwise, Stack } from '@phosphor-icons/react';
import { lazy, Suspense } from 'react';

import { OpsShell } from '../ops/OpsShell';
import { OpsPageHeader } from '../ops/OpsPageHeader';
import { ReleaseReadinessPage } from '../admin/pages/ReleaseReadinessPage';
import { AiAuditPage } from '../admin/pages/AiAuditPage';
import { t } from '../../i18n/platform';

/*
    ENVANTER İSTENDİĞİNDE İNER.

    Bu ekran superadmin'in her gün değil, dört somut soruda açtığı bir
    yerdir (`docs/111` §1): bir kiracı şikâyet ettiğinde, bir dağıtım
    öncesinde, bir belgeden şüphelenildiğinde, bir şeyi kapatmak
    gerektiğinde. Sürüm hazırlığına bakmaya gelen birine onun kodunu da
    indirtmek, hiç açılmayan bir tablonun ağırlığını herkese ödetmek olurdu.
*/
const ModulesPage = lazy(async () => ({
    default: (await import('../admin/pages/ModulesPage')).ModulesPage,
}));

type EngineeringSection = 'release-readiness' | 'ai-audit' | 'modules';

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
                /*
                    MODÜL ENVANTERİ BURADA, `/platform` altında DEĞİL
                    (`docs/111` §2). `/platform` ticari kabuktur: plan,
                    abonelik, sağlayıcı anahtarı — para ve satış. "Hangi
                    yetenek bu kurulumda gerçekten var" bir para sorusu
                    değil, bir KANIT sorusudur; komşuları sürüm hazırlığı
                    ve denetim izidir, plan fiyatı değil.
                */
                {
                    key: 'modules',
                    label: t('engineering.modules.nav.label'),
                    icon: <Stack aria-hidden="true" size={18} />,
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
            render={(section) => {
                if (section === 'ai-audit') {
                    return (
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
                    );
                }

                if (section === 'modules') {
                    return (
                        <>
                            <OpsPageHeader
                                title={t('engineering.modules.heading')}
                                description={t('engineering.modules.description')}
                                crumbs={[
                                    { label: t('engineering.shell.heading') },
                                    { label: t('engineering.modules.nav.label') },
                                ]}
                            />
                            {/*
                                Bekleme metni YOK: ekran zaten kendi yükleme
                                durumunu anlatır ve parça milisaniyeler içinde
                                iner. İki katmanlı "yükleniyor", kullanıcıya bir
                                şeyin takıldığını düşündürür.
                            */}
                            <Suspense fallback={null}>
                                <ModulesPage />
                            </Suspense>
                        </>
                    );
                }

                return (
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
                );
            }}
        />
    );
}

export default EngineeringApp;
