import { t } from '../../../../i18n/workspace';
import { HeatGrid, type HeatRow } from '../../../catalog/data-display/compound/HeatGrid';
import { RankBarList } from '../../../catalog/data-display/compound/RankBarList';
import { ShareRing } from '../../../catalog/data-display/compound/ShareRing';
import { TrendChart, type TrendPoint } from '../../../catalog/data-display/compound/TrendChart';
import { PanelCard } from '../shared/PanelCard';
import type { AnalyticsBreakdownRow } from './types';
import { useAnalyticsTimeSeries, type AnalyticsHourCell } from './useAnalyticsTimeSeries';

export type AnalyticsTimeSeriesRegionProps = {
    workspaceId: number;
    locationId?: number;
    range: string;
    /**
     * "Masaya göre ilk 5" — özet ucunun karekod kırılımı.
     *
     * Ayrı bir uçtan istenmez: aynı sayı zaten özetten geliyor ve ikinci bir
     * kaynak, iki listenin ayrışmasına yol açardı.
     */
    qrCodes?: AnalyticsBreakdownRow[];
};

const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7] as const;
const HOURS_IN_DAY = 24;
const TOP_TABLES = 5;

/** Kova tarihini kısa bir gün etiketine çevirir; sıralama tabloda tam durur. */
function dayLabel(date: string): string {
    const parsed = new Date(`${date}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

/**
 * Yayımlanan hücreleri 7 × 24'lük bir ızgaraya yerleştirir.
 *
 * Yayımlanmayan hücre SIFIR çizilir ve bunun bir bedeli var: gizlenmiş bir
 * hücre ile gerçekten boş bir hücre görsel olarak aynı görünür. Bedel
 * ızgaranın ALTINDA açıkça yazılarak ödenir ("{n} saat dilimi
 * gösterilmiyor") — çünkü sunucu, gizlenen hücrenin KOORDİNATINI de
 * vermiyor. Vermesi, tam olarak gizlemek istediğimiz şeyi söylerdi: bir
 * kişinin hangi gün, hangi saatte geldiğini.
 */
function heatRows(hourly: AnalyticsHourCell[]): HeatRow[] {
    const grid = new Map<string, number>();

    for (const cell of hourly) {
        grid.set(`${String(cell.weekday)}-${String(cell.hour)}`, cell.qrResolveCount);
    }

    return WEEKDAYS.map((weekday) => ({
        label: t(
            `workspace.analytics.weekday.${String(weekday)}` as 'workspace.analytics.weekday.1',
        ),
        values: Array.from(
            { length: HOURS_IN_DAY },
            (_unused, hour) => grid.get(`${String(weekday)}-${String(hour)}`) ?? 0,
        ),
    }));
}

/**
 * Insights'ın grafik bölgesi — `docs/109` §1, §6.5.
 *
 * Aralık TOPLAMI bir haftanın şeklini gizliyordu. Sahibin cumartesi akşamı
 * sorduğu dört soru —hangi gün çöktü, hangi saatte yoğunlaştı, geçen
 * haftaya göre nasıl, hangi şube çekiyor— ancak bu bölgede cevaplanır.
 *
 * Grafikler ECharts ile DEĞİL, elle yazılmış SVG ile çizilir: paket ~300 KB
 * gzip, bütçe ise giriş başına 200 KB (`DS-BUNDLE-BUDGET-07`). Tek bir ekran
 * için bütçeyi ikiye katlamak, telefonla bakan bir restoran sahibinin her
 * sayfa açılışını yavaşlatırdı.
 */
export function AnalyticsTimeSeriesRegion({
    workspaceId,
    locationId,
    range,
    qrCodes = [],
}: AnalyticsTimeSeriesRegionProps) {
    const { status, series } = useAnalyticsTimeSeries(workspaceId, locationId, range);

    if (status === 'error') {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.analytics.timeSeries.error')}
            </p>
        );
    }

    if (status === 'loading' || series === null) {
        return (
            <p role="status" className="text-body text-fg-muted">
                {t('workspace.analytics.timeSeries.loading')}
            </p>
        );
    }

    if (series.state === 'not_enough_data') {
        return (
            <PanelCard title={t('workspace.analytics.timeSeries.trend.title')}>
                {/*
                    Boş bir grafik sahibe "ürünüm bozuk" dedirtir. Sebep ve
                    EŞİK açıkça yazılır (`docs/66`): kaç ziyaretçi
                    gerektiğini bilmeyen biri, ne kadar bekleyeceğini de
                    bilemez.
                */}
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.analytics.timeSeries.thin', {
                        observed: String(series.observedVisitors),
                        threshold: String(series.threshold),
                    })}
                </p>
            </PanelCard>
        );
    }

    const points: TrendPoint[] = series.buckets.map((bucket) => ({
        label: dayLabel(bucket.date),
        primary: bucket.qrResolveCount,
        secondary: bucket.menuOpenCount,
    }));

    return (
        <div className="flex flex-col gap-[var(--space-fluid-md)]">
            <PanelCard title={t('workspace.analytics.timeSeries.trend.title')}>
                <TrendChart
                    points={points}
                    primaryLabel={t('workspace.analytics.timeSeries.scans')}
                    secondaryLabel={t('workspace.analytics.timeSeries.menuOpens')}
                    columnLabel={t('workspace.analytics.timeSeries.trend.column')}
                    description={t('workspace.analytics.timeSeries.trend.description')}
                />
            </PanelCard>

            {/*
                Isı haritası ve halka YAN YANA durur ama kırılma noktası
                sınıfı yoktur: ızgara kendi kendine sarar, yani ölçüyü
                tarayıcı değil içerik belirler. 320 pikselde tek sütun.
            */}
            <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,17rem),1fr))] gap-[var(--space-fluid-md)]">
                <PanelCard title={t('workspace.analytics.timeSeries.heat.title')}>
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <HeatGrid
                            rows={heatRows(series.hourly)}
                            description={t('workspace.analytics.timeSeries.heat.description', {
                                timezone: series.timezone,
                            })}
                            columnLabel={t('workspace.analytics.timeSeries.heat.column')}
                            hourLabel={(hour) => `${String(hour).padStart(2, '0')}`}
                            withheldLabel={t('workspace.analytics.timeSeries.heat.withheld')}
                        />
                        {/*
                            Gizleme SESSİZ olmaz. Ekran "o saatte kimse
                            yoktu" derse bu yanlıştır: geldi, yalnız tek
                            kişiydi ve sayısı yayımlanamaz.
                        */}
                        {series.suppressedHourCells > 0 ? (
                            <p className="text-meta text-fg-muted">
                                {t('workspace.analytics.timeSeries.heat.withheldNote', {
                                    count: String(series.suppressedHourCells),
                                })}
                            </p>
                        ) : null}
                    </div>
                </PanelCard>

                {/*
                    TEK DİLİMLİ HALKA ÇİZİLMEZ. Tek şubeli bir işletmede pay
                    %100'dür; halka üstündeki toplamın kelimesi kelimesine
                    tekrarıdır ve ekranda yalnız yer kaplar. Kırılımın değeri
                    KARŞILAŞTIRMADIR (`docs/68`).
                */}
                {series.locationShare.length > 1 ? (
                    <PanelCard title={t('workspace.analytics.timeSeries.share.title')}>
                        <div className="flex flex-col gap-[var(--space-2)]">
                            <ShareRing
                                slices={series.locationShare.map((row) => ({
                                    id: row.id,
                                    label: row.label,
                                    value: row.qrResolveCount,
                                    percent: row.sharePercent,
                                }))}
                                description={t('workspace.analytics.timeSeries.share.description')}
                                formatValue={(value) =>
                                    `${String(value)} ${t('workspace.analytics.timeSeries.tables.value')}`
                                }
                                formatPercent={(percent) => `${String(percent)}%`}
                            />
                            {/*
                                Süzülmüş bir ekranda pay hâlâ markanın
                                tamamını gösterir; söylenmezse sahip halkayı
                                seçili şubenin kırılımı sanar.
                            */}
                            <p className="text-meta text-fg-muted">
                                {t('workspace.analytics.timeSeries.share.scope')}
                            </p>
                        </div>
                    </PanelCard>
                ) : null}
            </div>

            {qrCodes.length > 1 ? (
                <PanelCard
                    title={t('workspace.analytics.timeSeries.tables.title')}
                    padded={false}
                    className="overflow-hidden"
                >
                    <RankBarList
                        rows={qrCodes.map((row) => ({
                            id: row.id,
                            label: row.label,
                            value: row.qrResolveCount,
                        }))}
                        limit={TOP_TABLES}
                        valueLabel={t('workspace.analytics.timeSeries.tables.value')}
                    />
                </PanelCard>
            ) : null}
        </div>
    );
}

export default AnalyticsTimeSeriesRegion;
