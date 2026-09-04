import { useEffect, useState } from 'react';
import { t } from '../../../../i18n/workspace';
import { formatBytes } from './mediaFormat';

export type MediaQuota = {
    planCode: string;
    planLabel: string;
    originalBytesUsed: number;
    originalBytesLimit: number;
    assetsUsed: number;
    assetsLimit: number;
    monthlyUploadsUsed: number;
    monthlyUploadsLimit: number | null;
    trashRetentionDays: number;
    blockedReason: string | null;
};

type MediaQuotaRegionProps = {
    workspaceId: number;
    onLoaded?: (quota: MediaQuota) => void;
};

function isQuota(value: unknown): value is MediaQuota {
    if (typeof value !== 'object' || value === null) return false;
    const q = value as Record<string, unknown>;
    return (
        typeof q.originalBytesUsed === 'number' &&
        typeof q.originalBytesLimit === 'number' &&
        typeof q.assetsUsed === 'number' &&
        typeof q.assetsLimit === 'number'
    );
}

function Meter({ label, used, limit }: { label: string; used: string; limit: string }) {
    return (
        <div className="flex flex-col gap-1">
            {/*
                Etiket GÖVDE metnidir: sayacın adı bir zaman damgası değildir
                ve `text-meta` rolü (`app.css`) yalnız zaman damgası/sayaç
                içindir. Ayrım boyutla değil RENKLE yapılır.
            */}
            <span className="text-body text-fg-secondary">{label}</span>
            {/*
                Sayaç `tabular-nums`: üç sayaç yan yana durur ve her yükleme
                bittiğinde değerler değişir. Orantılı rakamda "48" ile "49"
                farklı genişlikte çizilir; şerit yükleme boyunca titrerdi.
            */}
            <span className="text-meta text-fg tabular-nums">
                {t('workspace.media.quota.ratio', { used, limit })}
            </span>
        </div>
    );
}

/**
 * Kota sayaçları (`docs/49` Faz 7 madde 1-2, rakamlar `docs/98` §7).
 *
 * Sahip "ne kadar yerim kaldı?" sorusunu buradan okur. Dolduğunda yalnız
 * yeni yükleme durur — canlı menü hiçbir zaman bu kutuya bakmaz. Uç
 * okunamazsa kutu sessizce çekilir: kota bilgisi yükleme akışının önünde
 * bir kapı değil, yanında bir göstergedir.
 */
export function MediaQuotaRegion({ workspaceId, onLoaded }: MediaQuotaRegionProps) {
    const [quota, setQuota] = useState<MediaQuota | null>(null);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/media/quota`, {
                    credentials: 'same-origin',
                });
                if (!response.ok) return;
                const body = (await response.json()) as { quota?: unknown };
                if (!cancelled && isQuota(body.quota)) {
                    setQuota(body.quota);
                    onLoaded?.(body.quota);
                }
            } catch {
                // Gösterge yok diye yükleme durmaz; sunucu kotayı kendi uygular.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, onLoaded]);

    if (quota === null) {
        return null;
    }

    return (
        <section
            aria-label={t('workspace.media.quota.region')}
            className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-3)]"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.quota.heading')}
                </h3>
                <span className="text-body text-fg-muted">
                    {t('workspace.media.quota.plan', { plan: quota.planLabel })}
                </span>
            </div>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-[var(--space-3)]">
                <Meter
                    label={t('workspace.media.quota.storage')}
                    used={formatBytes(quota.originalBytesUsed)}
                    limit={formatBytes(quota.originalBytesLimit)}
                />
                <Meter
                    label={t('workspace.media.quota.assets')}
                    used={String(quota.assetsUsed)}
                    limit={String(quota.assetsLimit)}
                />
                <Meter
                    label={t('workspace.media.quota.monthly')}
                    used={String(quota.monthlyUploadsUsed)}
                    limit={
                        quota.monthlyUploadsLimit === null
                            ? t('workspace.media.quota.unlimited')
                            : String(quota.monthlyUploadsLimit)
                    }
                />
            </div>
            {/* Bir CÜMLE, sayaç değil: sahibin kararını değiştiren bilgi. */}
            <p className="text-body text-fg-muted">
                {t('workspace.media.quota.note', { days: String(quota.trashRetentionDays) })}
            </p>
            {quota.blockedReason ? (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {quota.blockedReason}
                </p>
            ) : null}
        </section>
    );
}
