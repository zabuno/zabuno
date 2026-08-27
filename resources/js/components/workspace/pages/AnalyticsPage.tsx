import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { Button, Label, Select } from 'flowbite-react';
import { t } from '../../../i18n/workspace';
import { AnalyticsMetricGrid } from './analytics/AnalyticsMetricGrid';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

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
};

type Summary = { qrResolveCount: number; menuOpenCount: number };

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
}: AnalyticsPageProps) {
    const rangeId = useId();
    const [range, setRange] = useState<AnalyticsRange>('today');
    const [status, setStatus] = useState<Status>('idle');
    const [summary, setSummary] = useState<Summary | null>(null);

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

    const rangeLabel: Record<AnalyticsRange, string> = {
        today: t('workspace.analytics.range.today'),
        '7d': t('workspace.analytics.range.7d'),
        '30d': t('workspace.analytics.range.30d'),
    };

    const statusBadge: WorkspacePageStatusBadge = (() => {
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
                return { key: 'analytics-status', status: 'success', label: rangeLabel[range] };
            case 'idle':
            default:
                return {
                    key: 'analytics-status',
                    status: 'info',
                    label: t('workspace.analytics.status.notConnected'),
                };
        }
    })();

    return (
        <div id="section-analytics">
            <WorkspacePageFrame
                title={t('workspace.analytics.heading')}
                description={t('workspace.analytics.operational.description')}
                badges={[statusBadge]}
            >
                <div className="flex flex-col gap-2">
                    <div className="mb-2 block">
                        <Label htmlFor={rangeId}>{t('workspace.analytics.range.label')}</Label>
                    </div>
                    <Select
                        id={rangeId}
                        value={range}
                        onChange={(event) => setRange(event.target.value as AnalyticsRange)}
                    >
                        <option value="today">{t('workspace.analytics.range.today')}</option>
                        <option value="7d">{t('workspace.analytics.range.7d')}</option>
                        <option value="30d">{t('workspace.analytics.range.30d')}</option>
                    </Select>
                </div>

                <div
                    role="region"
                    aria-label={t('workspace.analytics.report.region')}
                    className="flex flex-col gap-2"
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
                        <div role="status" className="flex flex-col items-start gap-[var(--space-2)]">
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

                    {status === 'success' && summary && (
                        <AnalyticsMetricGrid
                            qrResolveCount={summary.qrResolveCount}
                            menuOpenCount={summary.menuOpenCount}
                        />
                    )}
                </div>
            </WorkspacePageFrame>
        </div>
    );
}

export default AnalyticsPage;
