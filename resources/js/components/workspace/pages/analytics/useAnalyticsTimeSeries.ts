import { useEffect, useState } from 'react';

export type AnalyticsBucket = {
    date: string;
    qrResolveCount: number;
    menuOpenCount: number;
};

export type AnalyticsComparison = {
    basis: 'previous_period' | 'same_weekday_last_week';
    currentQrResolveCount: number;
    previousQrResolveCount: number;
    /** Önceki pencere boşsa oran YOKTUR — sıfırdan yüzde artış hesaplanamaz. */
    deltaRatio: number | null;
    previousStart: string;
    previousEnd: string;
};

export type AnalyticsHourCell = {
    /** ISO-8601 hafta günü: pazartesi 1 … pazar 7. */
    weekday: number;
    hour: number;
    qrResolveCount: number;
};

export type AnalyticsLocationShare = {
    id: number;
    label: string;
    qrResolveCount: number;
    sharePercent: number;
};

export type AnalyticsTimeSeries = {
    state: 'ready' | 'not_enough_data';
    threshold: number;
    observedVisitors: number;
    timezone: string;
    buckets: AnalyticsBucket[];
    comparison: AnalyticsComparison | null;
    hourly: AnalyticsHourCell[];
    /** Tek ziyaretçiye dayandığı için yayımlanmayan hücre sayısı. */
    suppressedHourCells: number;
    locationShare: AnalyticsLocationShare[];
    locationShareScope: string;
};

export type AnalyticsTimeSeriesStatus = 'loading' | 'error' | 'ready';

/**
 * Yanıt şekline KÖRÜ KÖRÜNE güvenilmez.
 *
 * Eski bir önbellek sürümü, araya giren bir vekil ya da 200 dönen bir hata
 * sayfası beklenmedik bir gövde verebilir. Bir alanın eksikliği analitik
 * sayfasının TAMAMINI çökertmemeli — sahibin gördüğü şey boş bir ekran
 * olurdu ve sebebini hiçbir yerde okuyamazdı.
 */
function normalize(body: Partial<AnalyticsTimeSeries>): AnalyticsTimeSeries {
    const list = <T>(value: unknown): T[] => (Array.isArray(value) ? (value as T[]) : []);

    return {
        state: body.state === 'ready' ? 'ready' : 'not_enough_data',
        threshold: typeof body.threshold === 'number' ? body.threshold : 0,
        observedVisitors: typeof body.observedVisitors === 'number' ? body.observedVisitors : 0,
        timezone: typeof body.timezone === 'string' ? body.timezone : 'UTC',
        buckets: list<AnalyticsBucket>(body.buckets),
        comparison: body.comparison ?? null,
        hourly: list<AnalyticsHourCell>(body.hourly),
        suppressedHourCells:
            typeof body.suppressedHourCells === 'number' ? body.suppressedHourCells : 0,
        locationShare: list<AnalyticsLocationShare>(body.locationShare),
        locationShareScope:
            typeof body.locationShareScope === 'string' ? body.locationShareScope : 'workspace',
    };
}

/**
 * Insights'ın zaman serisi — `docs/109` §1, §6.5.
 *
 * Şube VERİLMİŞSE şubeye süzülmüş adres kullanılır; verilmemişse markanın
 * tamamı. İkisi de sunucuda var (`docs/68`): üst çubuktaki "tüm şubeler"
 * bağlamının analitikte de karşılığı olmalı.
 */
export function useAnalyticsTimeSeries(
    workspaceId: number | undefined,
    locationId: number | undefined,
    range: string,
): { status: AnalyticsTimeSeriesStatus; series: AnalyticsTimeSeries | null } {
    const [status, setStatus] = useState<AnalyticsTimeSeriesStatus>('loading');
    const [series, setSeries] = useState<AnalyticsTimeSeries | null>(null);

    useEffect(() => {
        if (workspaceId === undefined) {
            return;
        }

        let cancelled = false;

        const scope =
            locationId === undefined
                ? `/api/workspaces/${String(workspaceId)}/analytics/time-series`
                : `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/analytics/time-series`;

        void (async () => {
            setStatus('loading');

            try {
                const response = await fetch(`${scope}?range=${range}`, {
                    credentials: 'include',
                    headers: { Accept: 'application/json' },
                });

                if (cancelled) return;

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                const body = (await response.json()) as Partial<AnalyticsTimeSeries>;

                if (cancelled) return;

                setSeries(normalize(body));
                setStatus('ready');
            } catch {
                if (!cancelled) setStatus('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, locationId, range]);

    return { status, series };
}
