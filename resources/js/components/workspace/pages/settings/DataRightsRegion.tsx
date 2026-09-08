import { DownloadSimple, Prohibit, ShieldCheck, Warning } from '@phosphor-icons/react';
import { useEffect, useState } from 'react';

import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { PageState } from '../shared/PageState';

/**
 * Veri hakları — Ayarlar > Çalışma alanı, tehlikeli bölge (FF-226,
 * `docs/107` Faz 3.3, `docs/138`).
 *
 * `WorkspaceIdentityRegion` bu bölümü adıyla bekletiyordu: *"Tehlikeli
 * bölge (dışa aktar / çalışma alanını kapat). Geri döndürülemez etki
 * taşır ve sahibin kararı olmadan çizilmez. Bu üçü doğduğunda buraya
 * gelir."* Karar verildi ve arkasındaki uçlar yazıldı; bölüm buraya geldi.
 *
 * EKRAN LİSTEYİ UYDURMAZ. "Silinmeyenler" listesi sunucudan gelir ve
 * sunucu onu silicinin okuduğu kaynaktan okur (`TenantDataScope`). Elle
 * yazılsaydı, bir gün bir tablo saklananlar arasına girer ve ekran "her
 * şey silinir" demeye devam ederdi — bu, kullanıcıya söylenebilecek en
 * pahalı yalan olurdu.
 *
 * İKİ EYLEM AYNI KARTTA AMA AYNI AĞIRLIKTA DEĞİL: kopya almak sıradan bir
 * düğmedir, silme ise kendi kutusunda, kendi uyarısıyla ve çalışma
 * alanının adını YAZDIRARAK durur.
 */
type DataRequest = {
    id: number;
    kind: 'export' | 'erasure';
    state: string;
    requestedBy: string | null;
    requestedAt: string;
    sectionCount: number;
    bytes: number | null;
    availableUntil: string | null;
    scheduledFor: string | null;
    completedAt: string | null;
    cancelledAt: string | null;
    failed: boolean;
    deletedRowTotal: number;
    notifiedAt: string | null;
    notificationFailed: boolean;
    downloadUrl: string | null;
};

type DataRights = {
    workspaceName: string;
    graceDays: number;
    exportedSections: string[];
    retained: Record<string, string>;
    assetsUnderLegalHold: number;
    hosting: { provider: string; country: string };
    requests: DataRequest[];
};

type Status = 'loading' | 'error' | 'forbidden' | 'ready';

export function DataRightsRegion({ workspaceId }: { workspaceId: number }) {
    const [status, setStatus] = useState<Status>('loading');
    const [data, setData] = useState<DataRights | null>(null);
    const [attempt, setAttempt] = useState(0);
    const [confirmation, setConfirmation] = useState('');
    const [mismatch, setMismatch] = useState(false);
    const [blocked, setBlocked] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);

    /*
        YÜKLEME ETKİNİN İÇİNDE ve yeniden deneme bir SAYAÇLA yapılır —
        `AuditTrailRegion` ile aynı desen ve aynı gerekçe: "yeniden yükle"
        ile "ilk yükleme" aynı yoldan geçmeli, yoksa ikisi bir gün ayrışır.
    */
    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/data-rights`,
                    buildAuthRequestInit(),
                );

                if (cancelled) return;

                if (response.status === 403 || response.status === 404) {
                    /*
                        YETKİ EKSİĞİ BİR ARIZA DEĞİLDİR. Yönetici bu
                        ekranı açabilir ama bu bölümü göremez; ona kırmızı
                        bir hata göstermek, olmayan bir arızayı bildirmeye
                        davet olurdu.
                    */
                    setStatus('forbidden');

                    return;
                }

                if (!response.ok) {
                    setStatus('error');

                    return;
                }

                setData((await response.json()) as DataRights);
                setStatus('ready');
            } catch {
                if (!cancelled) setStatus('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, attempt]);

    const reload = () => {
        setAttempt((previous) => previous + 1);
    };

    const requestExport = async () => {
        setBusy(true);

        try {
            await fetch(
                `/api/workspaces/${String(workspaceId)}/data-rights/exports`,
                buildAuthRequestInit({ method: 'POST' }),
            );
        } finally {
            setBusy(false);
            reload();
        }
    };

    const requestErasure = async () => {
        setMismatch(false);
        setBlocked(null);

        /*
            ADI YANLIŞ YAZILMIŞSA SUNUCUYA HİÇ GİTMEZ. Sunucu aynı kuralı
            ayrıca uyguluyor (422); istemcinin erken durması bir güvenlik
            önlemi değil, kullanıcıya cevabı beklemeden vermek içindir.
        */
        if (data === null || confirmation.trim() !== data.workspaceName.trim()) {
            setMismatch(true);

            return;
        }

        setBusy(true);

        try {
            const response = await fetch(
                `/api/workspaces/${String(workspaceId)}/data-rights/erasure`,
                buildAuthRequestInit({
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ confirmation }),
                }),
            );

            if (response.status === 409) {
                const body = (await response.json()) as { count?: number };
                setBlocked(body.count ?? 0);
            } else {
                setConfirmation('');
            }
        } finally {
            setBusy(false);
            reload();
        }
    };

    const cancelErasure = async (id: number) => {
        setBusy(true);

        try {
            await fetch(
                `/api/workspaces/${String(workspaceId)}/data-rights/erasure/${String(id)}`,
                buildAuthRequestInit({ method: 'DELETE' }),
            );
        } finally {
            setBusy(false);
            reload();
        }
    };

    if (status === 'loading') {
        return (
            <section
                aria-label={t('workspace.settings.dataRights.region')}
                className="flex flex-col gap-[var(--space-2)]"
            >
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.settings.dataRights.loading')}
                </p>
            </section>
        );
    }

    if (status === 'forbidden') {
        return (
            <section
                aria-label={t('workspace.settings.dataRights.region')}
                className="flex flex-col gap-[var(--space-2)]"
            >
                <p className="text-body text-fg-muted">
                    {t('workspace.settings.dataRights.forbidden')}
                </p>
            </section>
        );
    }

    if (status === 'error' || data === null) {
        return (
            <section
                aria-label={t('workspace.settings.dataRights.region')}
                className="flex flex-col gap-[var(--space-2)]"
            >
                <PageState
                    kind="error"
                    screen="settings_data_rights"
                    title={t('workspace.settings.dataRights.error')}
                    action={
                        <button type="button" onClick={reload} className={secondaryButtonClass}>
                            {t('workspace.settings.dataRights.retry')}
                        </button>
                    }
                />
            </section>
        );
    }

    const scheduledErasure = data.requests.find(
        (request) => request.kind === 'erasure' && request.state === 'scheduled',
    );
    const latestExport = data.requests.find((request) => request.kind === 'export');

    return (
        <section
            aria-label={t('workspace.settings.dataRights.region')}
            className="flex flex-col gap-[var(--space-6)]"
        >
            {/*
                BARINDIRMA OLGUSU EN ÜSTTE. Hukuk biriminin ilk sorusu
                "veri nerede tutuluyor?" ve cevabı bir belgeye değil bu
                ekrana ait: sunucu Almanya'da, yedekler aynı sunucuda.
            */}
            <p className="flex items-start gap-[var(--space-2)] text-body text-fg-secondary">
                <ShieldCheck
                    size={20}
                    weight="regular"
                    className="mt-[2px] flex-none"
                    aria-hidden
                />
                <span>
                    {t('workspace.settings.dataRights.hosting', {
                        provider: data.hosting.provider,
                        country: data.hosting.country,
                    })}
                </span>
            </p>

            <div className="flex flex-col gap-[var(--space-3)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.settings.dataRights.export.heading')}
                </h3>
                <p className="text-body text-fg-secondary">
                    {t('workspace.settings.dataRights.export.help')}
                </p>
                <p className="text-meta text-fg-muted">
                    {t('workspace.settings.dataRights.export.sections', {
                        count: String(data.exportedSections.length),
                    })}
                </p>

                {latestExport?.state === 'queued' || latestExport?.state === 'running' ? (
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.settings.dataRights.export.working')}
                    </p>
                ) : null}

                {latestExport?.state === 'ready' && latestExport.downloadUrl !== null ? (
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <p role="status" className="text-body text-fg">
                            {t('workspace.settings.dataRights.export.ready')}
                        </p>
                        <a
                            href={latestExport.downloadUrl}
                            className="inline-flex min-h-[var(--control-height)] items-center justify-center gap-[var(--space-2)] rounded-[var(--radius-md)] bg-action px-[var(--space-4)] py-[var(--space-2)] text-body font-bold text-action-fg"
                        >
                            <DownloadSimple size={20} weight="regular" aria-hidden />
                            {t('workspace.settings.dataRights.export.download')}
                        </a>
                        {latestExport.availableUntil !== null ? (
                            <p className="text-meta text-fg-muted">
                                {t('workspace.settings.dataRights.export.availableUntil', {
                                    date: latestExport.availableUntil,
                                })}
                            </p>
                        ) : null}
                        {/*
                            BİLDİRİMİN HÂLİ SÖYLENİR (`docs/93`): posta
                            yapılandırılmamışsa kullanıcı boşuna gelen
                            kutusuna bakmasın.
                        */}
                        <p className="text-meta text-fg-muted">
                            {latestExport.notifiedAt !== null
                                ? t('workspace.settings.dataRights.export.notified')
                                : t('workspace.settings.dataRights.export.notNotified')}
                        </p>
                    </div>
                ) : null}

                {latestExport?.state === 'expired' ? (
                    <p role="status" className="text-body text-fg-secondary">
                        {t('workspace.settings.dataRights.export.expired')}
                    </p>
                ) : null}

                {latestExport?.failed === true ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {t('workspace.settings.dataRights.export.failed')}
                    </p>
                ) : null}

                <button
                    type="button"
                    disabled={busy}
                    onClick={() => {
                        void requestExport();
                    }}
                    className={secondaryButtonClass}
                >
                    {t('workspace.settings.dataRights.export.request')}
                </button>
            </div>

            {/*
                SİLME KENDİ KUTUSUNDA. Aynı listenin bir maddesi olsaydı,
                "indir" ile "sil" komşu iki düğme olurdu; geri alınamaz
                olanın kendi kenarlığı vardır.
            */}
            <div className="flex flex-col gap-[var(--space-3)] rounded-[var(--radius-lg)] border border-border-danger p-[var(--space-4)]">
                <h3 className="flex items-center gap-[var(--space-2)] text-body font-bold text-fg">
                    <Warning size={20} weight="regular" className="flex-none" aria-hidden />
                    {t('workspace.settings.dataRights.erasure.heading')}
                </h3>
                <p className="text-body text-fg-secondary">
                    {t('workspace.settings.dataRights.erasure.help', {
                        days: String(data.graceDays),
                    })}
                </p>

                <details className="text-body text-fg-secondary">
                    <summary className="min-h-[var(--control-height)] cursor-pointer py-[var(--space-2)] font-medium">
                        {t('workspace.settings.dataRights.erasure.keptHeading')}
                    </summary>
                    {/*
                        LİSTE SUNUCUDAN GELİR. "Her şey silinir" demek
                        yalan olurdu ve o yalanı önleyen tek şey, bu
                        listenin silicinin okuduğu kaynakla aynı olması.
                    */}
                    <ul className="flex flex-col gap-[var(--space-2)] pt-[var(--space-2)]">
                        {Object.entries(data.retained).map(([table, reason]) => (
                            <li key={table} className="flex flex-col gap-[var(--space-1)]">
                                <span className="font-medium text-fg">{table}</span>
                                <span className="text-meta text-fg-muted">{reason}</span>
                            </li>
                        ))}
                    </ul>
                </details>

                {scheduledErasure !== undefined ? (
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <p role="status" className="text-body text-fg">
                            {t('workspace.settings.dataRights.erasure.scheduled', {
                                date: scheduledErasure.scheduledFor ?? '',
                            })}
                        </p>
                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => {
                                void cancelErasure(scheduledErasure.id);
                            }}
                            className={secondaryButtonClass}
                        >
                            {t('workspace.settings.dataRights.erasure.cancel')}
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <label
                            className="block text-body font-medium text-fg-secondary"
                            htmlFor="data-rights-confirmation"
                        >
                            {t('workspace.settings.dataRights.erasure.confirmLabel')}
                        </label>
                        <input
                            id="data-rights-confirmation"
                            name="data-rights-confirmation"
                            type="text"
                            autoComplete="off"
                            value={confirmation}
                            onChange={(event) => {
                                setConfirmation(event.target.value);
                                setMismatch(false);
                            }}
                            className="w-full min-h-[var(--control-height)] rounded-md border border-border bg-surface px-[var(--space-3)] py-[var(--space-2)] text-body text-fg"
                        />
                        <p className="text-meta text-fg-muted">
                            {t('workspace.settings.dataRights.erasure.confirmHelp')}
                        </p>

                        {mismatch ? (
                            <p role="alert" className="text-body text-fg-danger">
                                {t('workspace.settings.dataRights.erasure.mismatch')}
                            </p>
                        ) : null}

                        {blocked !== null ? (
                            <p
                                role="alert"
                                className="flex items-start gap-[var(--space-2)] text-body text-fg-danger"
                            >
                                <Prohibit
                                    size={20}
                                    weight="regular"
                                    className="mt-[2px] flex-none"
                                    aria-hidden
                                />
                                <span>
                                    {t('workspace.settings.dataRights.erasure.legalHold', {
                                        count: String(blocked),
                                    })}
                                </span>
                            </p>
                        ) : null}

                        <button
                            type="button"
                            disabled={busy}
                            onClick={() => {
                                void requestErasure();
                            }}
                            className="min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border-danger px-[var(--space-4)] py-[var(--space-2)] text-body font-bold text-fg-danger"
                        >
                            {t('workspace.settings.dataRights.erasure.request')}
                        </button>
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.settings.dataRights.history')}
                </h3>

                {data.requests.length === 0 ? (
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.settings.dataRights.history.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {data.requests.map((request) => (
                            <li
                                key={request.id}
                                className="flex flex-wrap items-center gap-x-[var(--space-3)] gap-y-[var(--space-1)] border-t border-border py-[var(--space-2)] text-body text-fg-secondary first:border-t-0"
                            >
                                <span className="text-meta tabular-nums text-fg-muted">
                                    {request.requestedAt}
                                </span>
                                <span className="font-medium text-fg">
                                    {request.kind}.{request.state}
                                </span>
                                {request.deletedRowTotal > 0 ? (
                                    <span className="text-meta tabular-nums text-fg-muted">
                                        {request.deletedRowTotal}
                                    </span>
                                ) : null}
                                <span className="text-meta text-fg-muted">
                                    {request.requestedBy ?? ''}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

const secondaryButtonClass =
    'min-h-[var(--control-height)] rounded-[var(--radius-md)] border border-border px-[var(--space-4)] py-[var(--space-2)] text-body font-medium text-fg-secondary hover:bg-surface-hover';

export default DataRightsRegion;
