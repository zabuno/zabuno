import { useEffect, useState } from 'react';
import { Check, Gavel, LockSimple, Scroll, Trash } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { BULK_ACTION_GROUPS } from './mediaBulk';
import { formatDate } from './mediaFormat';

type GovernancePermission = {
    action: string;
    allowed: boolean;
    requiredPermission: string;
    reversible: boolean;
};

type GovernanceTrailEntry = {
    kind: 'asset' | 'bulk';
    action: string;
    actor: string | null;
    at: string | null;
    scope?: string;
    applied?: number;
    skipped?: number;
    failed?: number;
    operationKey?: string;
    mediaAssetId?: number;
};

type GovernanceBody = {
    role: string | null;
    permissions: GovernancePermission[];
    retention: { trashRetentionDays: number; legalHoldCount: number };
    legalHolds: { id: number; name: string; reason: string; at: string | null }[];
    trail: GovernanceTrailEntry[];
};

type MediaGovernanceRegionProps = {
    workspaceId: number;
};

const ROLE_KEYS: Record<string, Parameters<typeof t>[0]> = {
    owner: 'workspace.media.governance.role.owner',
    manager: 'workspace.media.governance.role.manager',
    editor: 'workspace.media.governance.role.editor',
    member: 'workspace.media.governance.role.member',
};

/** Eylem adı sihirbazla AYNI sözlükten gelir: iki ekranda iki ad olamaz. */
const ACTION_LABELS = new Map<string, Parameters<typeof t>[0]>([
    ...BULK_ACTION_GROUPS.flatMap((group) =>
        group.actions.map((one) => [one.action, one.labelKey] as [string, Parameters<typeof t>[0]]),
    ),
    ['legal-hold', 'workspace.media.governance.action.legal-hold'],
]);

function isGovernance(value: unknown): value is GovernanceBody {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;

    return Array.isArray(body.permissions) && Array.isArray(body.trail);
}

/**
 * YÖNETİŞİM — kanonik kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`,
 * `data-screen-label="Yönetişim"` (plan `docs/109-PANEL-V3.md` §2). Bu
 * bölüm depoda HİÇ YOKTU.
 *
 * Üç soru, üç kart: kim ne yapabilir · dosyalar ne kadar saklanır · kim ne
 * yaptı.
 *
 * ═══ KİLİTLİ SATIR GİZLENMEZ ═══
 *
 * Kaynağın kuralı "Herkes sadece işine yeteni görür" — ama bu, YAPAMADIĞINI
 * da görmemesi demek değil. Menüyü hazırlayan editör "kalıcı sil"i
 * bulamayınca "ürün bunu yapamıyor" sanır ve patronundan hiç istemez;
 * kütüphane büyür, kota dolar ve kimse neden dolduğunu bilmez. O yüzden
 * kilitli satır DURUR ve hangi iznin gerektiğini yazar.
 *
 * ═══ ROL UYDURULMAZ ═══
 *
 * Kaynak dört kademeli bir matris çiziyor (izleyici/editör/yönetici/sahip).
 * Bu deponun gerçek rol modeli farklıdır ve ekranda GERÇEK olan yazar:
 * rolü ve her satırın kilidini sunucu söyler, ekran yalnız çizer.
 */
export function MediaGovernanceRegion({ workspaceId }: MediaGovernanceRegionProps) {
    const [data, setData] = useState<GovernanceBody | null>(null);
    const [loadState, setLoadState] = useState<'loading' | 'error' | 'idle'>('loading');

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/media/governance`, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const body = (await response.json()) as unknown;

                if (cancelled) return;

                if (!isGovernance(body)) {
                    setLoadState('error');

                    return;
                }

                setData(body);
                setLoadState('idle');
            } catch {
                if (!cancelled) setLoadState('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    if (loadState === 'loading') {
        return <p className="text-body text-fg-muted">{t('workspace.media.governance.loading')}</p>;
    }

    if (loadState === 'error' || data === null) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.media.governance.failed')}
            </p>
        );
    }

    return (
        <section
            aria-label={t('workspace.media.governance.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.governance.lead')}</p>

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface">
                <div className="flex flex-col gap-[var(--space-1)] border-b border-border p-[var(--space-3)]">
                    <h3 className="text-body font-bold text-fg">
                        {t('workspace.media.governance.matrix.heading')}
                    </h3>
                    <p className="text-body text-fg-secondary">
                        {t('workspace.media.governance.role', {
                            role: t(
                                ROLE_KEYS[data.role ?? ''] ??
                                    'workspace.media.governance.role.unknown',
                            ),
                        })}
                    </p>
                </div>

                <ul
                    aria-label={t('workspace.media.governance.matrix.heading')}
                    className="flex flex-col"
                >
                    {data.permissions.map((row) => (
                        <li
                            key={row.action}
                            className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border px-[var(--space-3)] py-[var(--space-2)] first:border-t-0"
                        >
                            <span className="min-w-0 flex-1 text-body font-medium text-fg">
                                {t(
                                    ACTION_LABELS.get(row.action) ??
                                        'workspace.media.bulk.action.optimize',
                                )}
                            </span>

                            {row.allowed ? (
                                <span className="inline-flex items-center gap-[var(--space-1)] text-body text-fg-success">
                                    <Check aria-hidden="true" size={16} weight="bold" />
                                    {t('workspace.media.governance.matrix.allowed')}
                                </span>
                            ) : (
                                /*
                                    Kilit SEBEBİYLE durur. "Kapalı" tek
                                    başına, kimden isteyeceğini bilmeyen bir
                                    kullanıcı bırakırdı.
                                */
                                <span className="inline-flex items-center gap-[var(--space-1)] text-body text-fg-warning">
                                    <LockSimple aria-hidden="true" size={16} />
                                    {t('workspace.media.governance.matrix.locked', {
                                        permission: row.requiredPermission,
                                    })}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="pb-[var(--space-2)] text-body font-bold text-fg">
                    {t('workspace.media.governance.retention.heading')}
                </h3>

                <RetentionRow
                    icon={<Trash aria-hidden="true" size={20} />}
                    label={t('workspace.media.governance.retention.trash')}
                    value={t('workspace.media.governance.retention.trash.value', {
                        days: String(data.retention.trashRetentionDays),
                    })}
                    description={t('workspace.media.governance.retention.trash.description')}
                />
                <RetentionRow
                    icon={<Scroll aria-hidden="true" size={20} />}
                    label={t('workspace.media.governance.retention.originals')}
                    value={t('workspace.media.governance.retention.originals.value')}
                    description={t('workspace.media.governance.retention.originals.description')}
                />
                <RetentionRow
                    icon={<Gavel aria-hidden="true" size={20} />}
                    label={t('workspace.media.governance.retention.legalHold')}
                    value={t('workspace.media.governance.retention.legalHold.value', {
                        count: String(data.retention.legalHoldCount),
                    })}
                    description={t('workspace.media.governance.retention.legalHold.description')}
                />
                <RetentionRow
                    icon={<Scroll aria-hidden="true" size={20} />}
                    label={t('workspace.media.governance.retention.audit')}
                    value={t('workspace.media.governance.retention.audit.value')}
                    description={t('workspace.media.governance.retention.audit.description')}
                />
            </section>

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <h3 className="pb-[var(--space-2)] text-body font-bold text-fg">
                    {t('workspace.media.governance.legalHold.heading')}
                </h3>

                {data.legalHolds.length === 0 ? (
                    <p className="text-body text-fg-muted">
                        {t('workspace.media.governance.legalHold.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {data.legalHolds.map((hold) => (
                            <li
                                key={hold.id}
                                className="flex flex-col gap-[var(--space-1)] border-t border-border py-[var(--space-2)] first:border-t-0"
                            >
                                <span className="text-body font-medium text-fg">{hold.name}</span>
                                {/*
                                    SEBEP yazılır. "Kilitli" tek başına, altı
                                    ay sonra kilidi kaldırmaya cesaret
                                    edemeyecek bir sahip bırakırdı.
                                */}
                                <span className="text-body text-fg-secondary">{hold.reason}</span>
                                <span className="text-meta text-fg-muted tabular-nums">
                                    {t('workspace.media.governance.legalHold.since', {
                                        date: formatDate(hold.at),
                                    })}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface">
                <h3 className="border-b border-border p-[var(--space-3)] text-body font-bold text-fg">
                    {t('workspace.media.governance.trail.heading')}
                </h3>

                {data.trail.length === 0 ? (
                    <p className="p-[var(--space-3)] text-body text-fg-muted">
                        {t('workspace.media.governance.trail.empty')}
                    </p>
                ) : (
                    <ul
                        aria-label={t('workspace.media.governance.trail.heading')}
                        className="flex flex-col"
                    >
                        {data.trail.map((entry, index) => (
                            <li
                                key={`${entry.kind}-${entry.operationKey ?? entry.mediaAssetId ?? index}`}
                                className="flex flex-col gap-[var(--space-1)] border-t border-border px-[var(--space-3)] py-[var(--space-2)] first:border-t-0"
                            >
                                <span className="text-body text-fg">
                                    {entry.kind === 'bulk'
                                        ? t('workspace.media.governance.trail.bulk', {
                                              action: entry.action,
                                              applied: String(entry.applied ?? 0),
                                              skipped: String(entry.skipped ?? 0),
                                              failed: String(entry.failed ?? 0),
                                          })
                                        : t('workspace.media.governance.trail.asset', {
                                              action: entry.action,
                                              id: String(entry.mediaAssetId ?? 0),
                                          })}
                                </span>
                                <span className="text-meta text-fg-secondary tabular-nums">
                                    {/*
                                        Aktörü bilinmeyen kayıt SİLİNMEZ:
                                        failin bilinmediğini söylemek, kaydı
                                        gizlemekten dürüsttür.
                                    */}
                                    {entry.actor ??
                                        t('workspace.media.governance.trail.unknownActor')}{' '}
                                    · {formatDate(entry.at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                <p className="border-t border-border bg-surface-subtle p-[var(--space-3)] text-body text-fg-secondary">
                    {t('workspace.media.governance.trail.note')}
                </p>
            </section>
        </section>
    );
}

function RetentionRow({
    icon,
    label,
    value,
    description,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    description: string;
}) {
    return (
        <div className="flex min-h-[var(--density-row-height)] items-start gap-[var(--space-2)] border-t border-border py-[var(--space-2)] first:border-t-0">
            <span className="flex-none pt-[var(--space-1)] text-fg-secondary">{icon}</span>
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-body font-medium text-fg">{label}</span>
                <span className="text-body text-fg-secondary">{description}</span>
            </span>
            <span className="flex-none text-meta font-medium text-fg tabular-nums">{value}</span>
        </div>
    );
}

export default MediaGovernanceRegion;
