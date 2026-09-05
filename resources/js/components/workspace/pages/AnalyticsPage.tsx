import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { SegmentedControl } from '../../catalog/forms/compound/SegmentedControl';
import { AnalyticsMetricGrid } from './analytics/AnalyticsMetricGrid';
import { AnalyticsTimeSeriesRegion } from './analytics/AnalyticsTimeSeriesRegion';
import { InsightsHighlight } from './analytics/InsightsHighlight';
import { MenuEngineeringRegion } from './analytics/MenuEngineeringRegion';
import { useAnalyticsTimeSeries } from './analytics/useAnalyticsTimeSeries';
import { useMenuEngineering } from './analytics/useMenuEngineering';
import type { AnalyticsBreakdownRow } from './analytics/types';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';
import { PageState } from './shared/PageState';
import { useCurrentPublication } from './qr/useCurrentPublication';
import type { DashboardMenuTree } from './DashboardPage';

export type AnalyticsRange = 'today' | '7d' | '30d';

export type AnalyticsPageProps = {
    workspaceId?: number;
    locationId?: number;
    /**
     * Engellenmiş durumdan ÇIKIŞ YOLU. Bir blocked state, nedenini
     * söylemekle yetinmez; kullanıcının bugün yapabileceği şeyi de gösterir
     * (`docs/44` engellenmiş durum standardı).
     */
    onNavigateToSection?: (section: string) => void;
    /**
     * "Veri yok" TEK BİR DURUM DEĞİLDİR — `docs/66`.
     *
     * Menü hiç yokken, menü yayınlanmamışken, yayınlanmış ama hiç
     * taranmamışken ve seçili aralıkta etkinlik yokken kullanıcının yapması
     * gereken şey farklıdır. Aynı cümleyi dördüne birden söylemek, üçüne
     * yanlış çıkış yolu göstermek demektir.
     *
     * Bu yüzden sayfa menü ağacını da alır: ayrımı yapabilmek için.
     */
    menuTree?: DashboardMenuTree | null;
};

type Summary = {
    qrResolveCount: number;
    menuOpenCount: number;
    uniqueVisitorCount: number;
    openRate: number | null;
    locations: AnalyticsBreakdownRow[];
    qrCodes: AnalyticsBreakdownRow[];
};

/** Boş sonucun HANGİ boşluk olduğu. */
type EmptyReason = 'no-menu' | 'not-published' | 'no-scans' | 'range';

/**
 * `plan-restricted`, `error`den AYRIDIR ve bu ayrım şart.
 *
 * Sunucu 402 döndürüyordu ve arayüz onu "Analytics failed to load. Please
 * try again." diye gösterip bir Retry düğmesi koyuyordu. Yeniden denemek
 * hiçbir zaman işe yaramaz: kullanıcı yetkisiz değil, planı bu yeteneği
 * içermiyor. Çıkış yolu farklıdır.
 */
type Status = 'idle' | 'loading' | 'error' | 'plan-restricted' | 'success';

/**
 * Real ledger summary surface: fetches the location-scoped analytics
 * summary once both workspaceId and locationId are known, and never
 * fabricates a zero for loading/error states.
 */
export function AnalyticsPage({
    workspaceId,
    locationId,
    onNavigateToSection,
    menuTree = null,
}: AnalyticsPageProps) {
    const [range, setRange] = useState<AnalyticsRange>('today');
    const [status, setStatus] = useState<Status>('idle');
    const [summary, setSummary] = useState<Summary | null>(null);

    const { current: publication } = useCurrentPublication(workspaceId, menuTree?.id ?? null);

    /*
        ZAMAN SERİSİ VE MENÜ RAPORU SAYFA DÜZEYİNDE OKUNUR (`docs/109` §1).

        İkisi de iki yerde kullanılıyor: üstteki "bu aralıkta ne oldu?" kartı
        ile alttaki grafik ve liste bölgeleri. Her bölgenin kendi isteğini
        atması, aynı sayıyı iki kez indirmek ve iki yerin AYRIŞABİLMESİ
        demekti — özet kartı "14 kez arandı" derken liste 9 gösterirdi.

        Plan raporlamayı içermiyorsa hiç istenmez: aynı 402 iki kez daha
        dönerdi ve ekranda hiçbir karşılığı olmazdı.
    */
    const planAllowsReporting = status !== 'plan-restricted';
    const timeSeries = useAnalyticsTimeSeries(
        planAllowsReporting ? workspaceId : undefined,
        locationId,
        range,
    );
    const menuEngineering = useMenuEngineering(
        planAllowsReporting ? workspaceId : undefined,
        range,
    );

    const requestIdRef = useRef(0);

    const fetchSummary = useCallback(() => {
        if (workspaceId === undefined || locationId === undefined) {
            return;
        }

        const requestId = ++requestIdRef.current;

        void (async () => {
            setStatus('loading');

            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/brand/locations/${locationId}/analytics/summary?range=${range}`,
                );

                if (requestIdRef.current !== requestId) {
                    return;
                }

                if (response.status === 402) {
                    setStatus('plan-restricted');

                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as Summary;

                if (requestIdRef.current !== requestId) {
                    return;
                }

                setSummary({
                    qrResolveCount: body.qrResolveCount,
                    menuOpenCount: body.menuOpenCount,
                    /*
                        Sunucu bu alanları vermezse SIFIR/boş kabul edilir,
                        uydurulmaz. Eski bir sunucuya karşı çalışan bir
                        istemcide "yaklaşık benzersiz" alanı hiç ölçülmemiş
                        olabilir; onu tahmin etmek, bilinmeyeni bilinen gibi
                        göstermek olurdu.
                    */
                    uniqueVisitorCount: body.uniqueVisitorCount ?? 0,
                    openRate: body.openRate ?? null,
                    locations: body.locations ?? [],
                    qrCodes: body.qrCodes ?? [],
                });
                setStatus('success');
            } catch {
                if (requestIdRef.current !== requestId) {
                    return;
                }

                setStatus('error');
            }
        })();
    }, [workspaceId, locationId, range]);

    useEffect(() => {
        fetchSummary();
    }, [fetchSummary]);

    /**
     * Rozet ANORMAL durumu bildirir; başarı hâlinde hiçbir şey göstermez.
     *
     * Önceden başarı hâlinde seçili zaman aralığı ("Today") basılıyordu —
     * oysa o bilgi hemen altındaki `Range` seçicisinde zaten duruyor ve
     * kullanıcının kendi seçtiği şeydir. Bir durum rozetinin işi, kullanıcının
     * BİLMEDİĞİ bir şeyi söylemektir; bildiği şeyi tekrarladığında rozetlerin
     * tamamı okunmayan süse dönüşür ve gerçek uyarı da fark edilmez.
     */
    /*
        Boşluğun SEBEBİ. Sıra önemlidir: en erken engel önce gelir, çünkü
        kullanıcıya gösterilecek çıkış yolu odur. Yayınlanmamış bir menüsü
        olan birine "QR kodunu yazdır" demek, atlayamayacağı bir adımı
        atlamasını istemek olurdu.
    */
    const emptyReason: EmptyReason =
        menuTree === null
            ? 'no-menu'
            : publication === null
              ? 'not-published'
              : range === '30d'
                ? 'no-scans'
                : 'range';

    const statusBadge: WorkspacePageStatusBadge | null = (() => {
        switch (status) {
            case 'loading':
                return {
                    key: 'analytics-status',
                    status: 'info',
                    label: t('workspace.analytics.status.loading'),
                };
            case 'error':
                return {
                    key: 'analytics-status',
                    status: 'error',
                    label: t('workspace.analytics.status.error'),
                };
            case 'plan-restricted':
                // Uyarı, hata değil: ortada bozulmuş bir şey yok.
                return {
                    key: 'analytics-status',
                    status: 'warning',
                    label: t('workspace.analytics.status.planRestricted'),
                };
            case 'success':
                return null;
            case 'idle':
            default:
                return {
                    key: 'analytics-status',
                    status: 'info',
                    label: t('workspace.analytics.status.notConnected'),
                };
        }
    })();

    /*
        ARALIK SEÇİCİSİ SAYFA BAŞLIĞININ YANINDA (FF-131, teslim paketi §5:
        "Başlık + aralık segmenti").

        Gövdenin ilk satırında duran bir "Range" alanı, hemen altındaki tek
        bölgeye aitmiş gibi okunuyordu. Oysa aralık, sayfadaki HER sayının
        kapsamıdır: sahip "7 gün" dediğinde sayaçlar da, kırılımlar da, menü
        mühendisliği de o aralığa göre yeniden okunur. Kapsamı sayfa
        başlığının yanında göstermek, bunu tek bakışta söyler.
    */
    /*
        ARALIK SEÇİCİSİ AÇILIR LİSTE DEĞİL, SEGMENT (`docs/109` §1, kaynağın
        Insights başlığı).

        Üç seçenekli bir açılır liste, üç seçeneği de GİZLER: sahip "30 gün"e
        bakmak için önce listeyi açmak, sonra seçmek zorundaydı — iki dokunuş
        ve arada kapanan bir katman. Kaynağın segmenti üçünü birden gösterir
        ve seçili olanı ekranda tutar; sahip tek dokunuşla aralıklar arasında
        gidip gelir. Bu ekranın en sık yapılan işi tam olarak budur.
    */
    const rangeControl = (
        <SegmentedControl<AnalyticsRange>
            label={t('workspace.analytics.range.label')}
            value={range}
            onChange={setRange}
            options={[
                { value: 'today', label: t('workspace.analytics.range.today') },
                { value: '7d', label: t('workspace.analytics.range.7d') },
                { value: '30d', label: t('workspace.analytics.range.30d') },
            ]}
        />
    );

    /*
        Sayaç kartının altındaki karşılaştırma satırı, kaynağın "%12 · geçen
        perşembe" yuvasıdır ve artık GERÇEKTEN ölçülüyor. Ölçülemediğinde
        (seri henüz yüklenmedi, eşiğin altında ya da önceki pencere boş)
        satır hiç yazılmaz — uydurulmuş bir yüzde, sahibin bir sonraki
        kararına yanlış temel olurdu.
    */
    const comparison = timeSeries.series?.comparison ?? null;
    const comparisonSupport =
        comparison === null || comparison.deltaRatio === null
            ? undefined
            : t(
                  comparison.deltaRatio > 0
                      ? 'workspace.analytics.compare.up'
                      : comparison.deltaRatio < 0
                        ? 'workspace.analytics.compare.down'
                        : 'workspace.analytics.compare.flat',
                  {
                      percent: String(Math.round(Math.abs(comparison.deltaRatio) * 100)),
                      basis: t(
                          comparison.basis === 'same_weekday_last_week'
                              ? 'workspace.analytics.compare.basis.sameWeekdayLastWeek'
                              : 'workspace.analytics.compare.basis.previousPeriod',
                      ),
                  },
              );

    const searches = menuEngineering.report?.searchesWithNoResults ?? null;
    const notFoundCount =
        searches === null ? undefined : searches.reduce((sum, row) => sum + row.searches, 0);

    return (
        <div id="section-analytics">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.analytics.heading')}
                /*
                    Ekranın cümlesi KAYNAĞINKİDİR (`docs/109` §1): "Misafirler
                    neye bakıyor, neyi arayıp bulamıyor." Öncekisi ("gerçek
                    QR çözümleme ve doğrulanmış menü açılışı sayılarını
                    inceleyin") ölçümün ADINI söylüyordu, sahibin sorusunu
                    değil — ve bu ekranın tamamı o soru için var.
                */
                description={t('workspace.analytics.description')}
                badges={statusBadge ? [statusBadge] : []}
                actions={rangeControl}
            >
                {/*
                    KARTIN İÇİNE KART ÇİZİLMEZ (FF-131, teslim paketinin kart
                    grameri).

                    Rapor bölgesinin tamamı tek bir `PanelCard`'ın içindeydi;
                    içindeki kırılımlar, menü mühendisliği kartları ve boş
                    durumlar da kendi kartlarını çiziyordu. Ortaya aynı zemin
                    ve aynı kenarlıkla çizilmiş iç içe iki çerçeve çıkıyor,
                    sahip iki çizgi görüyor ama hiçbiri ona yeni bir şey
                    söylemiyordu — yalnız 320 piksellik bir telefonda içerik
                    iki kat yatay dolgu kaybediyordu.

                    Kart sınırı ANLAM taşıdığı yerde kullanılır (`docs/36`
                    §5.2): bir liste, bir bölge, bir kayıt. Sayfanın tamamı
                    bunların hiçbiri değil.
                */}
                <div
                    role="region"
                    aria-label={t('workspace.analytics.report.region')}
                    className="flex flex-col gap-[var(--space-fluid-md)]"
                >
                    {/*
                        Plan kısıtlıyken yenileme düğmesi GÖSTERİLMEZ.
                        Basıldığında aynı 402 dönecekti; ekranda duran ama
                        hiçbir zaman işe yaramayacak bir düğme, kullanıcıya
                        olmayan bir yol gösterir.
                    */}
                    {status === 'plan-restricted' ? null : (
                        <div>
                            <Button
                                size="xs"
                                color="light"
                                disabled={status === 'loading'}
                                onClick={fetchSummary}
                            >
                                {status === 'error'
                                    ? t('workspace.analytics.action.retry')
                                    : t('workspace.analytics.action.refresh')}
                            </Button>
                        </div>
                    )}

                    {status === 'idle' && (
                        <p role="status" className="text-body text-fg-muted">
                            {t('workspace.analytics.report.unavailable')}
                        </p>
                    )}

                    {status === 'loading' && (
                        <p role="status" className="text-body text-fg-muted">
                            {t('workspace.analytics.report.loading')}
                        </p>
                    )}

                    {status === 'error' && (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('workspace.analytics.report.error')}
                        </p>
                    )}

                    {status === 'plan-restricted' && (
                        <div
                            role="status"
                            className="flex flex-col items-start gap-[var(--space-2)]"
                        >
                            {/*
                                Boş durum dört soruyu cevaplar (`docs/44`):
                                ne yok, neden yok, kullanıcı için anlamı ne,
                                şimdi ne yapabilir. "Veriniz kaybolmuyor"
                                cümlesi bilerek var — asıl korku o.
                            */}
                            <p className="max-w-content text-body text-fg-secondary">
                                {t('workspace.analytics.report.planRestricted')}
                            </p>
                            {onNavigateToSection ? (
                                <Button
                                    size="xs"
                                    color="light"
                                    onClick={() => onNavigateToSection('billing')}
                                >
                                    {t('workspace.analytics.action.viewPlan')}
                                </Button>
                            ) : null}
                        </div>
                    )}

                    {status === 'success' &&
                        summary &&
                        workspaceId !== undefined &&
                        (summary.qrResolveCount === 0 && summary.menuOpenCount === 0 ? (
                            <AnalyticsEmptyState
                                reason={emptyReason}
                                onNavigateToSection={onNavigateToSection}
                                onWidenRange={() => setRange('30d')}
                            />
                        ) : (
                            <div className="flex flex-col gap-[var(--space-fluid-md)]">
                                {/*
                                    "BU ARALIKTA NE OLDU?" EN ÜSTTE
                                    (`docs/109` §1, kaynağın Insights düzeni).

                                    Sahip ekranı grafik okumak için açmıyor;
                                    ne olduğunu öğrenmek için açıyor. Üç
                                    cümlelik özet, grafiklerin sorduğu
                                    "şimdi ne yapmalıyım" sorusuna bir adım
                                    atar — ve üçü de gerçek ölçümden doğar,
                                    uydurulmuş tek bir sayı yoktur.
                                */}
                                {timeSeries.series ? (
                                    <InsightsHighlight
                                        series={timeSeries.series}
                                        report={menuEngineering.report}
                                        {...(onNavigateToSection ? { onNavigateToSection } : {})}
                                    />
                                ) : null}

                                <AnalyticsMetricGrid
                                    qrResolveCount={summary.qrResolveCount}
                                    menuOpenCount={summary.menuOpenCount}
                                    uniqueVisitorCount={summary.uniqueVisitorCount}
                                    openRate={summary.openRate}
                                    {...(comparisonSupport === undefined
                                        ? {}
                                        : { comparisonSupport })}
                                    {...(searches === null
                                        ? {}
                                        : {
                                              searchCount: searches.length,
                                              notFoundCount,
                                          })}
                                />

                                {/*
                                    İKİ SÜTUN, KIRILMA NOKTASI SINIFI YOK.

                                    Solda "ne oldu" (grafikler, masalar),
                                    sağda "menümde ne işe yarıyor" (ürünler,
                                    aranıp bulunamayanlar). Kaynağın düzeni
                                    bu; ızgara kendi kendine sarar, yani
                                    ölçüyü tarayıcı değil içerik belirler ve
                                    320 pikselde tek sütuna iner.
                                */}
                                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))] items-start gap-[var(--space-fluid-md)]">
                                    <AnalyticsTimeSeriesRegion
                                        workspaceId={workspaceId}
                                        {...(locationId === undefined ? {} : { locationId })}
                                        range={range}
                                        qrCodes={summary.qrCodes}
                                    />

                                    {/*
                                        MENÜ MÜHENDİSLİĞİ (`docs/84`).

                                        Huninin ALTINDA duruyor: önce "kaç
                                        kişi geldi", sonra "geldiklerinde neye
                                        baktılar". Ters sırada, sahip ilgi
                                        sayılarını ziyaret sayısı sanardı.

                                        Rapor sayfadan geliyor: ikinci bir
                                        istek, aynı sayıyı iki kez indirmek ve
                                        üstteki özet kartıyla ayrışabilmek
                                        demekti.
                                    */}
                                    <MenuEngineeringRegion
                                        workspaceId={workspaceId}
                                        range={range}
                                        source={menuEngineering}
                                        {...(onNavigateToSection
                                            ? {
                                                  /*
                                                      "Ekle" GERÇEKTEN bir
                                                      yere götürür: kategori
                                                      ve ürün oluşturma akışı
                                                      menü ekranındadır.
                                                      Hedefi olmayan bir düğme
                                                      hiç çizilmez.
                                                  */
                                                  onAddTerm: () => {
                                                      onNavigateToSection('menu');
                                                  },
                                              }
                                            : {})}
                                    />
                                </div>
                            </div>
                        ))}

                    {/*
                        MENÜ MÜHENDİSLİĞİ ARTIK İKİ SÜTUNUN SAĞINDA.

                        Eskiden raporun tamamının ALTINDA, tek sütun olarak
                        duruyordu ve sahip "aranıp bulunamayanlar"a ulaşmak
                        için ekranı sonuna kadar kaydırmak zorundaydı —
                        oysa kaynağın Insights düzeninde o liste ilk ekranda,
                        grafiklerin yanında durur (`docs/109` §1).

                        Plan kısıtı ARIZA DEĞİLDİR (`docs/84`): planın
                        raporlamayı içermediği durumda bölüm hiç çizilmez,
                        aksi hâlde "yüklenemedi" derdi ve sahip ürünün
                        bozulduğunu sanardı — oysa yapması gereken şey planını
                        yükseltmek. Bu koşul yukarıdaki `status === 'success'`
                        dalının içinde zaten sağlanıyor.
                    */}
                </div>
            </WorkspacePageFrame>
        </div>
    );
}

/**
 * Analitiğin boş durumları — `docs/66`.
 *
 * Dördü de "veri yok" der ama dördünün ÇIKIŞ YOLU farklıdır. Tek bir cümle
 * kullanmak, üçüne yanlış yol göstermek demekti.
 */
function AnalyticsEmptyState({
    reason,
    onNavigateToSection,
    onWidenRange,
}: {
    reason: EmptyReason;
    onNavigateToSection?: (section: string) => void;
    onWidenRange: () => void;
}) {
    if (reason === 'no-menu') {
        return (
            <PageState
                kind="prerequisite"
                title={t('workspace.analytics.empty.noMenu.title')}
                description={t('workspace.analytics.empty.noMenu.description')}
                {...(onNavigateToSection
                    ? {
                          action: (
                              <Button size="xs" onClick={() => onNavigateToSection('menu')}>
                                  {t('workspace.analytics.empty.noMenu.action')}
                              </Button>
                          ),
                      }
                    : { whyNoAction: t('workspace.analytics.empty.useSidebar') })}
            />
        );
    }

    if (reason === 'not-published') {
        return (
            <PageState
                kind="prerequisite"
                title={t('workspace.analytics.empty.notPublished.title')}
                description={t('workspace.analytics.empty.notPublished.description')}
                {...(onNavigateToSection
                    ? {
                          action: (
                              <Button size="xs" onClick={() => onNavigateToSection('publication')}>
                                  {t('workspace.analytics.empty.notPublished.action')}
                              </Button>
                          ),
                      }
                    : { whyNoAction: t('workspace.analytics.empty.useSidebar') })}
            />
        );
    }

    if (reason === 'no-scans') {
        return (
            <PageState
                kind="empty"
                title={t('workspace.analytics.empty.noScans.title')}
                description={t('workspace.analytics.empty.noScans.description')}
                {...(onNavigateToSection
                    ? {
                          action: (
                              <Button size="xs" onClick={() => onNavigateToSection('qr-codes')}>
                                  {t('workspace.analytics.empty.noScans.action')}
                              </Button>
                          ),
                      }
                    : { whyNoAction: t('workspace.analytics.empty.useSidebar') })}
            />
        );
    }

    /*
        Seçili aralıkta etkinlik yok. Çıkış yolu ARALIĞI GENİŞLETMEKTİR ve bu
        sayfanın içinde yapılır — kullanıcıyı başka bir ekrana göndermek,
        cevabın burada olduğu bir soruda gereksiz bir yolculuk olurdu.
    */
    return (
        <PageState
            kind="empty"
            title={t('workspace.analytics.empty.range.title')}
            description={t('workspace.analytics.empty.range.description')}
            action={
                <Button size="xs" onClick={onWidenRange}>
                    {t('workspace.analytics.empty.range.action')}
                </Button>
            }
        />
    );
}

export default AnalyticsPage;
