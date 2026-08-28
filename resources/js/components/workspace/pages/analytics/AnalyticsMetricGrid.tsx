import { StatCard } from '../../../catalog/data-display/compound/StatCard';
import { t } from '../../../../i18n/workspace';

export type AnalyticsMetricGridProps = {
    qrResolveCount: number;
    menuOpenCount: number;
    /** Yaklaşık benzersiz ziyaretçi — `docs/68`. */
    uniqueVisitorCount: number;
    /** Tarama yoksa oran YOKTUR; sıfır değil, `null`. */
    openRate: number | null;
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
}: AnalyticsMetricGridProps) {
    return (
        <div className="flex flex-col gap-3">
            <StatCard label={t('workspace.analytics.metric.qrResolve')} value={qrResolveCount} />
            <StatCard label={t('workspace.analytics.metric.menuOpen')} value={menuOpenCount} />
            {/*
                "Yaklaşık" kelimesi etikettedir ve orada kalmalı: proxy
                arkasındaki iki müşteri tek görünebilir, tarayıcısını
                değiştiren bir kişi iki görünebilir. Kesinmiş gibi sunulan bir
                tahmin, yanlış kararlara temel olur.
            */}
            <StatCard
                label={t('workspace.analytics.metric.uniqueVisitors')}
                value={uniqueVisitorCount}
            />
            {/*
                Oran YALNIZ hesaplanabildiğinde çizilir. Tarama yokken "%0"
                göstermek "kimse açmadı" der; oysa doğrusu "kimse taramadı"dır
                ve ikisi farklı sorunlardır.
            */}
            {openRate !== null ? (
                <StatCard
                    label={t('workspace.analytics.metric.openRate')}
                    value={`${String(Math.round(openRate * 100))}%`}
                />
            ) : null}
        </div>
    );
}

export default AnalyticsMetricGrid;
