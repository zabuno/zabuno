import { StatCard } from '../../../catalog/data-display/compound/StatCard';
import { t } from '../../../../i18n/workspace';

export type AnalyticsMetricGridProps = {
    qrResolveCount: number;
    menuOpenCount: number;
    /** Yaklaşık benzersiz ziyaretçi — `docs/68`. */
    uniqueVisitorCount: number;
    /**
     * Tarama yoksa oran YOKTUR; sıfır değil, `null`.
     *
     * Kendi kartı yoktur: menü açılışı kartının ALT SATIRIDIR (kaynağın dört
     * sayaçlı ızgarası). Oran iki sayının bileşimi, üçüncü bir ölçüm değil —
     * ve tam olarak açıkladığı sayının altında durur.
     */
    openRate: number | null;
    /**
     * Tarama sayacının ALTINDAKİ tek satır — kaynağın "%12 · geçen perşembe"
     * yuvası (`docs/109` §1).
     *
     * Bu satır artık GERÇEKTEN ölçülüyor: zaman serisi ucu bir önceki
     * pencerenin taramasını da veriyor. Ölçülemediğinde (önceki pencere boş,
     * ya da seri henüz yüklenmedi) hiç yazılmaz — uydurulmuş bir yüzde
     * göstermek, sahibin bir sonraki kararına yanlış temel olurdu.
     */
    comparisonSupport?: string;
    /**
     * Arama sayacı — kaynağın dördüncü KPI'ı.
     *
     * İki sayı BİRLİKTE anlam taşır: 40 aramanın 14'ü sonuçsuzsa menüde bir
     * boşluk var demektir. Yalnız "40 arama" bunu hiç söylemez.
     */
    searchCount?: number;
    notFoundCount?: number;
};

/**
 * Analitik sayaçları.
 *
 * Dördü de gerçek ölçümdür; hiçbiri uydurulmaz. Çağıran bunu ancak gerçek
 * bir özet yanıtı geldikten sonra çizer.
 */
export function AnalyticsMetricGrid({
    qrResolveCount,
    menuOpenCount,
    uniqueVisitorCount,
    openRate,
    comparisonSupport,
    searchCount,
    notFoundCount,
}: AnalyticsMetricGridProps) {
    return (
        /*
            KPI IZGARASI (FF-131, teslim paketi `DESIGN_SPEC` §5).

            Dört sayı ALT ALTA diziliyordu. Oysa bu dördü ancak BİRLİKTE
            anlam taşır: tarama 200 iken menü açılışı 40 ise sorun QR'da
            değil menüdedir — ve bu, iki sayı yan yana durmadan görülmez.
            Yığılmış hâlde sahibin gözü her sayı için satır başına dönüyor,
            dördüncü sayı katlamanın altında kalıyordu.

            `auto-fit` + `minmax`: 320 pikselde tek sütun, yer açıldıkça
            iki, üç, dört. Kırılma noktası sınıfı YOK — ızgara kendi kendine
            sarar, yani ölçüyü tarayıcı değil içerik belirler.
        */
        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,9rem),1fr))] gap-[var(--space-3)]">
            <StatCard
                label={t('workspace.analytics.metric.qrResolve')}
                value={qrResolveCount}
                {...(comparisonSupport === undefined ? {} : { support: comparisonSupport })}
            />
            {/*
                Oran YALNIZ hesaplanabildiğinde yazılır. Tarama yokken "%0"
                göstermek "kimse açmadı" der; oysa doğrusu "kimse taramadı"dır
                ve ikisi farklı sorunlardır — biri menünün, diğeri karekodun
                derdi.
            */}
            <StatCard
                label={t('workspace.analytics.metric.menuOpen')}
                value={menuOpenCount}
                {...(openRate === null
                    ? {}
                    : {
                          support: t('workspace.analytics.metric.menuOpen.support', {
                              percent: String(Math.round(openRate * 100)),
                          }),
                      })}
            />
            {/*
                "Yaklaşık" kelimesi etikettedir ve orada kalmalı: proxy
                arkasındaki iki müşteri tek görünebilir, tarayıcısını
                değiştiren bir kişi iki görünebilir. Kesinmiş gibi sunulan bir
                tahmin, yanlış kararlara temel olur. Alt satır bunun SEBEBİNİ
                söyler: ölçülen şey kişi değil, cihaz.
            */}
            <StatCard
                label={t('workspace.analytics.metric.uniqueVisitors')}
                value={uniqueVisitorCount}
                support={t('workspace.analytics.metric.uniqueVisitors.support')}
            />
            {/*
                Arama sayacı ANCAK ölçüldüğünde çizilir. Menü mühendisliği
                raporu henüz gelmediyse ya da eşiğin altındaysa buraya "0"
                yazmak, "kimse aramadı" der — oysa doğrusu "henüz
                bilmiyoruz"dur ve ikisi farklı şeylerdir.
            */}
            {searchCount !== undefined ? (
                <StatCard
                    label={t('workspace.analytics.metric.searches')}
                    value={searchCount}
                    support={t('workspace.analytics.metric.searches.support', {
                        count: String(notFoundCount ?? 0),
                    })}
                />
            ) : null}
        </div>
    );
}

export default AnalyticsMetricGrid;
