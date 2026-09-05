import { useEffect, useState } from 'react';

import { t } from '../../../i18n/workspace';
import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../lib/csrfHeader';
import type { DashboardMenuTree } from './DashboardPage';
import { PhonePreviewRegion } from './publication/PhonePreviewRegion';
import {
    PublicationStatusRegion,
    type CurrentPublication,
} from './publication/PublicationStatusRegion';
import { buildPublicationDiff, PublicationDiffRegion } from './publication/PublicationDiffRegion';
import { PublicationHistoryRegion } from './publication/PublicationHistoryRegion';
import { PublishScheduleRegion } from './publication/PublishScheduleRegion';
import { PublishStepper } from './publication/PublishStepper';
import { PublishActionConfigRegion } from './publication/PublishActionConfigRegion';
import {
    isDraftReady,
    PublishReadinessChecklistRegion,
} from './publication/PublishReadinessChecklistRegion';
import { PublishedSnapshotRegion } from './publication/PublishedSnapshotRegion';
import { QrDestinationRegion } from './publication/QrDestinationRegion';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

type PublicationPageProps = {
    workspaceId?: number;
    dashboardMenuTree?: DashboardMenuTree | null;
    /**
     * Hazırlık listesindeki "Düzelt" düğmesinin gideceği yer. Yoksa düğme
     * çizilmez — gidecek yeri olmayan bir düğme, sahibin ürüne güvenini
     * tek tıkta harcar.
     */
    onNavigateToSection?: (section: string) => void;
};

export function PublicationPage({
    workspaceId,
    dashboardMenuTree = null,
    onNavigateToSection,
}: PublicationPageProps) {
    const menuId = dashboardMenuTree?.id ?? null;
    const locationId = dashboardMenuTree?.locationId ?? null;

    const [current, setCurrent] = useState<CurrentPublication | null>(null);
    const [loading, setLoading] = useState(workspaceId !== undefined && menuId !== null);
    const [loadError, setLoadError] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [publishing, setPublishing] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [retryToken, setRetryToken] = useState(0);
    /*
        Sahip taslağı telefonunda AÇTI MI? Adım çizgisindeki "Önizleme"
        adımı bununla yanar — bir düğmenin ekranda durmasıyla değil.
        Yapılmamış bir kontrolü yapılmış göstermek, çizginin tek işini
        (doğruyu söylemek) elinden alırdı.
    */
    const [previewChecked, setPreviewChecked] = useState(false);

    useEffect(() => {
        if (workspaceId === undefined || menuId === null) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/menu/${menuId}/publications/current`,
                    {
                        credentials: 'include',
                        headers: { Accept: 'application/json' },
                    },
                );

                if (cancelled) return;

                if (response.ok) {
                    setCurrent((await response.json()) as CurrentPublication);
                    setLoadError(false);
                } else if (response.status === 404) {
                    setCurrent(null);
                    setLoadError(false);
                } else {
                    setLoadError(true);
                }
            } catch {
                if (!cancelled) setLoadError(true);
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, menuId, retryToken]);

    function handleRetry() {
        setLoading(true);
        setLoadError(false);
        setRetryToken((token) => token + 1);
    }

    const checklistReady = isDraftReady(dashboardMenuTree);

    /*
        FARK BİR KEZ HESAPLANIR ve iki yer onu paylaşır (adım çizgisi ve
        değişiklik listesi). İki kez hesaplansaydı, aralarına giren tek bir
        yeniden çizim "3 değişiklik" diyen bir adım çizgisiyle iki satırlık
        bir liste üretebilir ve sahip hangisine inanacağını bilemezdi.
    */
    const pendingChanges = buildPublicationDiff(dashboardMenuTree, current?.snapshot ?? null);

    const badges: WorkspacePageStatusBadge[] = [
        checklistReady
            ? {
                  key: 'publication-readiness',
                  status: 'success',
                  label: t('workspace.publication.readiness.ready'),
              }
            : {
                  key: 'publication-readiness',
                  status: 'warning',
                  label: t('workspace.publication.readiness.needsAttention'),
              },
        current !== null
            ? {
                  key: 'publication-status',
                  status: 'success',
                  /*
                      Rozet SÜRÜM NUMARASINI da taşır — kaynağın kendi
                      cümlesi: "Yayında · v14".

                      Önceden yalnız "Yayında" yazıyordu ve bu, yanlış
                      fiyatı gören misafirle tartışan sahibin sorduğu
                      soruyu cevaplamıyordu: "misafir HANGİ sürümü
                      görüyor?". Sürüm, kullanıcı için okunabilir tek
                      sayıdır; her yayında bir artar. (Yayının veritabanı
                      kimliği hâlâ ekrana çıkmaz: onun kullanıcı için bir
                      anlamı yok.)
                  */
                  label: t('workspace.publication.status.liveBadge', {
                      version: String(current.version),
                  }),
              }
            : {
                  key: 'publication-status',
                  status: 'info',
                  label: t('workspace.publication.status.notPublished'),
              },
    ];

    const [prevChecklistReady, setPrevChecklistReady] = useState(checklistReady);
    if (checklistReady !== prevChecklistReady) {
        setPrevChecklistReady(checklistReady);
        if (!checklistReady) {
            setConfirmed(false);
        }
    }

    async function handlePublish() {
        if (workspaceId === undefined || menuId === null) return;

        setPublishing(true);
        setErrorMessage(null);

        try {
            await bootstrapCsrfCookie();

            const response = await fetch(
                `/api/workspaces/${workspaceId}/menu/${menuId}/publications`,
                buildAuthRequestInit({ method: 'POST' }),
            );

            if (response.ok) {
                setCurrent((await response.json()) as CurrentPublication);
                setLoadError(false);
                setConfirmed(false);
            } else {
                setErrorMessage(t('workspace.publication.status.publishError'));
            }
        } catch {
            setErrorMessage(t('workspace.publication.status.publishError'));
        } finally {
            setPublishing(false);
        }
    }

    return (
        <div id="section-publication">
            <WorkspacePageFrame
                measure="standard"
                cardChildren
                title={t('workspace.shell.nav.publication')}
                description={t('workspace.publication.operational.description')}
                badges={badges}
            >
                {/*
                    ADIM ÇİZGİSİ EN ÜSTTE — kaynağın kendi sırası: Taslak →
                    Önizleme → Yayında. Sahip paneli günde beş kez açar ve
                    her açışında tek bir soru sorar: "menüm güncel mi?".
                    Cevap artık üç ayrı bölgeye dağılmış değil, ilk satırda.
                */}
                <PublishStepper
                    pendingChangeCount={pendingChanges.length}
                    previewOpen={previewChecked}
                    liveVersion={current?.version ?? null}
                    publishedAt={current?.publishedAt ?? null}
                />
                {/*
                    NE YAYINLANACAK — "Yayınla" düğmesinden ÖNCE. Sahip
                    bugüne kadar düğmeye, ne yayınlayacağını görmeden
                    basıyordu.
                */}
                <PublicationDiffRegion dashboardMenuTree={dashboardMenuTree} current={current} />
                <PublishReadinessChecklistRegion
                    dashboardMenuTree={dashboardMenuTree}
                    onFix={onNavigateToSection}
                />
                <PublicationStatusRegion
                    current={current}
                    loading={loading}
                    loadError={loadError}
                    onRetry={handleRetry}
                    checklistReady={checklistReady}
                    confirmed={confirmed}
                    onConfirmedChange={setConfirmed}
                    onPublish={handlePublish}
                    publishing={publishing}
                    errorMessage={errorMessage}
                />
                {workspaceId !== undefined && menuId !== null ? (
                    <PublishScheduleRegion
                        workspaceId={workspaceId}
                        menuId={menuId}
                        draftReady={checklistReady}
                    />
                ) : null}
                <PhonePreviewRegion
                    dashboardMenuTree={dashboardMenuTree}
                    workspaceId={workspaceId}
                    menuId={menuId}
                    onPreviewOpened={() => setPreviewChecked(true)}
                />
                {current !== null ? <PublishedSnapshotRegion current={current} /> : null}
                {workspaceId !== undefined && menuId !== null ? (
                    <PublicationHistoryRegion
                        workspaceId={workspaceId}
                        menuId={menuId}
                        refreshToken={retryToken + (current?.version ?? 0)}
                        onRestored={handleRetry}
                    />
                ) : null}
                <PublishActionConfigRegion />
                {workspaceId !== undefined && locationId !== null && menuId !== null ? (
                    <QrDestinationRegion
                        workspaceId={workspaceId}
                        locationId={locationId}
                        menuId={menuId}
                        hasCurrentPublication={current !== null}
                    />
                ) : null}
            </WorkspacePageFrame>
        </div>
    );
}

export default PublicationPage;
