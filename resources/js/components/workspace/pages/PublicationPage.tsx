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
import { PanelCard } from './shared/PanelCard';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';
import { trackEvent } from '../../../lib/analytics';
import { minutesSinceSignup } from '../../../lib/analyticsEvents';

/**
 * TIME TO FIRST QR (`docs/112` §4.1) — taksonominin en değerli satırı.
 *
 * Kullanıcı yolculuğu: Mehmet Usta kaydolur ve menüsünü yayınlar. Bu iki an
 * arasındaki süre bugüne kadar HİÇBİR YERDE ölçülmüyordu; `docs/110` §7'deki
 * "kurulum 5 dakika mı 15 dakika mı" tartışması bu sayı olmadan kapanamaz.
 *
 * "İlk" olduğunu SUNUCU söyler: yayının sürüm numarası 1 ise bu menünün ilk
 * yayınıdır. İstemcinin elindeki "önce yayın var mıydı?" bilgisi bunu güvenilir
 * biçimde söyleyemez — sahip paneli iki sekmede açmış olabilir, ya da yayın
 * başka bir ekip üyesi tarafından yapılmış olabilir. Sürüm numarası ise yayının
 * kendi kaydından gelir ve tek bir doğruyu taşır.
 *
 * `minutes_since_signup` bilinmiyorsa alan HİÇ gönderilmez (`docs/112` §3.4):
 * "0 dakikada yayınladı" ile "ne zaman yayınladığını bilmiyoruz" aynı
 * ortalamada toplanamaz.
 */
function trackFirstPublish(published: CurrentPublication): void {
    if (published.version !== 1) {
        return;
    }

    /*
        Ürün sayısı YAYINLANAN kopyadan sayılır, ekrandaki taslaktan değil.
        Sahip "Yayınla"ya bastığı anda taslağını değiştirmiş olabilir; ölçüm
        misafirin gerçekten göreceği menüyü anlatmalıdır.
    */
    const itemCount = published.snapshot.categories.reduce(
        (total, category) => total + category.menuItems.length,
        0,
    );

    trackEvent('first_publish_completed', {
        minutes_since_signup: minutesSinceSignup(),
        item_count: itemCount,
    });
}

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
        // Hangi hata tekrar denettiriyor (`docs/112` §4.3).
        trackEvent('retry_clicked', { surface: 'publication' });
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
                const published = (await response.json()) as CurrentPublication;

                trackFirstPublish(published);

                setCurrent(published);
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
                /*
                    EKRAN İKİ SÜTUNLU, YANİ GENİŞ (kanonik kaynak
                    `docs/reference/panel-v3/panel-v3.1.dc.html`, Yayınlama:
                    `publishCols = '1fr 360px'`).

                    `standard` ölçüsünde iki sütun sığmıyordu; sürümler
                    listesi akışın altına düşüyor ve sahip "hangi sürüme
                    döneyim?" sorusunun cevabına ulaşmak için ekranı sonuna
                    kadar kaydırmak zorunda kalıyordu — oysa geri alma tam
                    olarak panik anında aranan şeydir.
                */
                measure="wide"
                /*
                    `cardChildren` KALDIRILDI: KARTIN İÇİNE KART ÇİZİLMEZ
                    (`docs/36` §5.2; aynı düzeltme Insights'ta yapılmıştı).

                    Çerçeve her doğrudan çocuğu bir `PanelCard`'a sarıyordu.
                    Ama adım çizgisi, değişiklik listesi, planlama, telefon
                    önizlemesi ve sürümler kendi kartlarını ZATEN çiziyor —
                    yani ekranda aynı zemin ve aynı kenarlıkla çizilmiş iç
                    içe iki çerçeve vardı. Sahip iki çizgi görüyor, hiçbiri
                    ona yeni bir şey söylemiyor, 320 piksellik bir telefonda
                    içerik iki kat yatay dolgu kaybediyordu.

                    Kart artık YALNIZ kendi kartı olmayan bölgeye açıkça
                    veriliyor.
                */
                title={t('workspace.shell.nav.publication')}
                description={t('workspace.publication.operational.description')}
                badges={badges}
            >
                {/*
                    ADIM ÇİZGİSİ EN ÜSTTE ve TAM GENİŞLİKTE — kaynağın kendi
                    sırası: Taslak → Önizleme → Yayında. Sahip paneli günde
                    beş kez açar ve her açışında tek bir soru sorar: "menüm
                    güncel mi?". Cevap üç ayrı bölgeye dağılmış değil, ilk
                    satırda; ve bir sütunun içine sıkışmadığı için üç adım
                    yan yana okunur.
                */}
                <PublishStepper
                    pendingChangeCount={pendingChanges.length}
                    previewOpen={previewChecked}
                    liveVersion={current?.version ?? null}
                    publishedAt={current?.publishedAt ?? null}
                />

                {/*
                    SOLDA AKIŞ, SAĞDA SÜRÜMLER (kaynağın `1fr 360px`
                    düzeni).

                    Kırılma noktası sınıfı YOK ve sabit piksel yok: sarma
                    ölçüyü `flex-wrap` + `rem` tabanlı taban genişlikleri
                    belirler, yani düzen kapsayıcının kendi genişliğine göre
                    kurulur, ekranın değil. 320 pikselde iki sütun tek
                    sütuna iner ve sıra bozulmaz: önce ne yayınlanacağı,
                    sonra sürümler.
                */}
                <div className="flex flex-wrap items-start gap-[var(--space-fluid-md)]">
                    <div className="flex min-w-0 flex-[3_1_28rem] flex-col gap-[var(--space-fluid-md)]">
                        {/*
                            NE YAYINLANACAK — "Yayınla" düğmesinden ÖNCE.
                            Sahip bugüne kadar düğmeye, ne yayınlayacağını
                            görmeden basıyordu.
                        */}
                        <PublicationDiffRegion
                            dashboardMenuTree={dashboardMenuTree}
                            current={current}
                        />
                        <PanelCard>
                            <PublishReadinessChecklistRegion
                                dashboardMenuTree={dashboardMenuTree}
                                onFix={onNavigateToSection}
                            />
                        </PanelCard>
                        <PanelCard>
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
                        </PanelCard>
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
                        {current !== null ? (
                            <PanelCard>
                                <PublishedSnapshotRegion current={current} />
                            </PanelCard>
                        ) : null}
                        <PanelCard>
                            <PublishActionConfigRegion />
                        </PanelCard>
                        {workspaceId !== undefined && locationId !== null && menuId !== null ? (
                            <PanelCard>
                                <QrDestinationRegion
                                    workspaceId={workspaceId}
                                    locationId={locationId}
                                    menuId={menuId}
                                    hasCurrentPublication={current !== null}
                                />
                            </PanelCard>
                        ) : null}
                    </div>

                    {/*
                        SÜRÜMLER KENDİ ŞERİDİNDE.

                        `empty:hidden`: hiç yayın yapılmamışken liste kendini
                        çizmez ve şerit de yok olur — boş bir sütun, geniş
                        ekranda akışın yanında açıklanamayan bir boşluk
                        bırakırdı.
                    */}
                    <div className="flex min-w-0 flex-[1_1_18rem] flex-col gap-[var(--space-fluid-md)] empty:hidden">
                        {workspaceId !== undefined && menuId !== null ? (
                            <PublicationHistoryRegion
                                workspaceId={workspaceId}
                                menuId={menuId}
                                refreshToken={retryToken + (current?.version ?? 0)}
                                onRestored={handleRetry}
                            />
                        ) : null}
                    </div>
                </div>
            </WorkspacePageFrame>
        </div>
    );
}

export default PublicationPage;
