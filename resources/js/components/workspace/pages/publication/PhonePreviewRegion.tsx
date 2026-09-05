import { useCallback, useState } from 'react';
import { DeviceMobile } from '@phosphor-icons/react';

import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { DraftMenuPreviewRegion } from './DraftMenuPreviewRegion';
import { t } from '../../../../i18n/workspace';
import type { DashboardMenuTree } from '../DashboardPage';

type PhonePreviewRegionProps = {
    dashboardMenuTree: DashboardMenuTree | null;
    workspaceId?: number;
    menuId: number | null;
    /**
     * Sahip taslağı GERÇEKTEN telefonunda açtığında haber verir.
     *
     * Adım çizgisindeki "Önizleme" adımı bununla yanar; bir düğmenin
     * varlığıyla değil. Aksi hâlde çizgi, sahibin yapmadığı bir kontrolü
     * yapılmış gösterirdi — ve o çizginin tek işi doğruyu söylemektir.
     */
    onPreviewOpened?: () => void;
};

/**
 * "Telefonda önizle" — misafirin göreceği menüye YAYINLAMADAN bakmak.
 *
 * Restoran sahibinin yolculuğu: fiyatları düzeltir ve "masadaki misafir
 * bunu nasıl görecek?" diye bakmak ister. Bugüne kadar bunun tek yolu
 * YAYINLAMAKTI — yani kontrol etmek için önce riski almak.
 *
 * İKİ KATMANLI ÖNİZLEME ve ikisi de gerçek:
 *   1. EKRANDA, dar bir sütunda: telefon genişliğinde okununca uzun ürün
 *      adlarının kırıldığı, fiyatların nereye düştüğü hemen görülür.
 *   2. TELEFONDA, imzalı ve on beş dakikalık bir adresle: cihazın kendisi.
 *
 * Adres MİSAFİRİN ADRESİ DEĞİLDİR ve ekran bunu YAZAR. Basılı karttaki QR
 * kodu yayınlanmış menüyü göstermeye devam eder; sahibin en pahalı korkusu
 * kartların ölmesidir ve o korku tam burada karşılanmalıdır.
 */
export function PhonePreviewRegion({
    dashboardMenuTree,
    workspaceId,
    menuId,
    onPreviewOpened,
}: PhonePreviewRegionProps) {
    const [linkPending, setLinkPending] = useState(false);
    const [linkError, setLinkError] = useState(false);

    const openLink = useCallback(async () => {
        if (workspaceId === undefined || menuId === null) {
            return;
        }

        setLinkPending(true);
        setLinkError(false);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/menu/${menuId}/draft-preview-link`,
                buildAuthRequestInit({ method: 'POST' }),
            );

            if (!response.ok) {
                setLinkError(true);

                return;
            }

            const body = (await response.json()) as { url?: string };

            if (typeof body.url !== 'string' || body.url === '') {
                setLinkError(true);

                return;
            }

            window.open(body.url, '_blank', 'noopener,noreferrer');
            onPreviewOpened?.();
        } catch {
            setLinkError(true);
        } finally {
            setLinkPending(false);
        }
    }, [workspaceId, menuId, onPreviewOpened]);

    return (
        <section
            role="region"
            aria-label={t('workspace.publication.preview.region')}
            data-phone-preview="true"
            className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--density-padding-inline)]"
        >
            <h3 className="flex items-center gap-[var(--space-2)] text-body font-bold text-fg">
                <DeviceMobile aria-hidden="true" size={22} weight="bold" className="shrink-0" />
                {t('workspace.publication.preview.heading')}
            </h3>

            {workspaceId !== undefined && menuId !== null ? (
                <div className="flex flex-col items-start gap-[var(--space-2)]">
                    <button
                        type="button"
                        disabled={linkPending}
                        onClick={() => void openLink()}
                        className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] py-[var(--space-1)] text-body font-medium text-fg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    >
                        {t('workspace.publication.preview.linkButton')}
                    </button>

                    {/*
                        ADRESİN SINIRLARI DÜĞMENİN YANINDA YAZAR. On beş
                        dakika, arama motoruna kapalı, ve MİSAFİRİN ADRESİ
                        DEĞİL. Üçüncüsü en önemlisi: sahip bu bağlantıyı
                        yanlışlıkla masaya bastıracak olsa, o kart ertesi gün
                        ölürdü.
                    */}
                    <p className="max-w-[60ch] text-body text-fg-secondary">
                        {t('workspace.publication.preview.linkHelp')}
                    </p>

                    {linkPending ? (
                        <p role="status" className="text-body text-fg-muted">
                            {t('workspace.publication.preview.linkPending')}
                        </p>
                    ) : null}

                    {linkError ? (
                        <p role="alert" className="text-body text-fg-danger">
                            {t('workspace.publication.preview.linkError')}
                        </p>
                    ) : null}
                </div>
            ) : null}

            {/*
                TELEFON GENİŞLİĞİ EKRANDA. Ölçü bir jetondan değil, gerçek
                bir cihazın dar sütunundan gelir; `rem` cinsindendir, yani
                sahibin yazı tipi ölçüsünü büyütmesiyle birlikte büyür.
                Sabit piksel verilseydi, büyük yazı seçen bir kullanıcıda
                aynı çerçeve bambaşka bir menü gösterirdi.
            */}
            <div className="w-full max-w-[22rem] overflow-hidden rounded-[var(--radius-lg)] border border-border-strong bg-surface p-[var(--space-3)]">
                <DraftMenuPreviewRegion dashboardMenuTree={dashboardMenuTree} />
            </div>
        </section>
    );
}

export default PhonePreviewRegion;
