import { useCallback, useEffect, useState } from 'react';
import { ArrowClockwise } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';

export type MediaProcessingJob = {
    id: number;
    mediaAssetId: number;
    assetName: string | null;
    kind: string;
    state: string;
    attempts: number;
    failureReason: string | null;
    finished: boolean;
    /** Bilinmiyorsa `null` — tabloda yüzde sütunu YOK, uydurulmaz. */
    progress: number | null;
    startedAt: string | null;
    finishedAt: string | null;
};

export type MediaJobCounts = {
    pending: number;
    running: number;
    succeeded: number;
    failed: number;
    held: number;
    total: number;
};

type MediaJobQueueRegionProps = {
    workspaceId: number;
};

type LoadState = 'loading' | 'error' | 'idle';

const KIND_LABEL: Record<string, Parameters<typeof t>[0]> = {
    rendition: 'workspace.media.queue.kind.rendition',
    scan: 'workspace.media.queue.kind.scan',
};

const STATE_LABEL: Record<string, Parameters<typeof t>[0]> = {
    pending: 'workspace.media.queue.state.pending',
    running: 'workspace.media.queue.state.running',
    succeeded: 'workspace.media.queue.state.succeeded',
    failed: 'workspace.media.queue.state.failed',
    held: 'workspace.media.queue.state.held',
};

/**
 * Durumun RENGİ token'dan gelir; ham renk yazılmaz.
 *
 * `held` uyarı rengindedir, tehlike değil: dosyada bir sorun bulunmadı,
 * tarayıcı konuşamadı. Kırmızı göstermek sahibi "dosyam bozuk" sanmaya
 * iterdi.
 */
const STATE_TONE: Record<string, string> = {
    succeeded: 'text-fg-success',
    failed: 'text-fg-danger',
    held: 'text-fg-warning',
};

const BAR_TONE: Record<string, string> = {
    succeeded: 'bg-fg-success',
    failed: 'bg-fg-danger',
    held: 'bg-fg-warning',
};

function isCounts(value: unknown): value is MediaJobCounts {
    if (typeof value !== 'object' || value === null) return false;
    const counts = value as Record<string, unknown>;
    return typeof counts.total === 'number' && typeof counts.running === 'number';
}

/**
 * Sayaç kartı: ad gövde metni, sayı `tabular-nums`.
 *
 * `data-testid` şart: "Running" hem sayaç adı hem satır durumudur ve testin
 * ikisini ayırt edebilmesi, ekranı okuyan birinin de ayırt edebilmesiyle
 * aynı şeydir — sayaç bir ÖZET, satır bir OLAYdır.
 */
function CountCard({
    testId,
    label,
    value,
    tone,
}: {
    testId: string;
    label: string;
    value: number;
    tone?: string;
}) {
    return (
        <div
            data-testid={testId}
            className="flex flex-col gap-[var(--space-1)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-3)]"
        >
            <span className="text-body text-fg-secondary">{label}</span>
            {/*
                Sayaç değeri ekran açıkken DEĞİŞİR. Orantılı rakamda "9" ile
                "10" farklı genişlikte çizilir ve dört kart yan yanayken
                şerit her yenilemede titrer.
            */}
            <span className={`text-subsection font-bold tabular-nums ${tone ?? 'text-fg'}`}>
                {value}
            </span>
        </div>
    );
}

/**
 * KUYRUK — "takıldı mı, yoksa hâlâ çalışıyor mu?" (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Kuyruk"; gerekçe `docs/108` §3 madde 5).
 *
 * Yükleme ve yeniden üretim iş üretir; o iş bugüne kadar veritabanına
 * yazılıp hiçbir ekranda görünmüyordu. Restoran sahibi on fotoğraf yükleyip
 * kütüphanede önizleme çıkmadığını gördüğünde bu sorunun cevabını hiçbir
 * yerden alamıyor, aynı fotoğrafı tekrar tekrar yükleyerek kotasını kendi
 * eliyle dolduruyordu.
 *
 * SALT OKUNUR: burada iş başlatılmaz. "Yeniden dene", var olan tek-varlık
 * yeniden üretim ucuna gider — kuyruğun kendi işleme hattı yoktur.
 *
 * İLERLEME UYDURULMAZ. `media_processing_jobs` tablosunda yüzde sütunu
 * yoktur: çalışan bir işin ne kadarının bittiği BİLİNMİYOR. Şerit yalnız
 * BİTEN iş için dolar; çalışan işin altında "kayıtlı bir ilerleme sayısı
 * yok" cümlesi yazar. Sahte bir "%40" sahibi önce bekletir, sonra yanıltır.
 *
 * İş satırları KART DEĞİL: tek kartın içinde 1 piksellik çizgiyle ayrılmış
 * satırlardır. Altı ayrı kart, altı ayrı duyuru gibi okunurdu.
 */
export function MediaJobQueueRegion({ workspaceId }: MediaJobQueueRegionProps) {
    const [jobs, setJobs] = useState<MediaProcessingJob[]>([]);
    const [counts, setCounts] = useState<MediaJobCounts | null>(null);
    const [loadState, setLoadState] = useState<LoadState>('loading');
    const [retryingId, setRetryingId] = useState<number | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [noticeIsError, setNoticeIsError] = useState(false);

    const load = useCallback(async (): Promise<boolean> => {
        const response = await fetch(
            `/api/workspaces/${workspaceId}/media/jobs`,
            buildAuthRequestInit(),
        );

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const body = (await response.json()) as { data?: MediaProcessingJob[]; counts?: unknown };

        setJobs(Array.isArray(body.data) ? body.data : []);
        setCounts(isCounts(body.counts) ? body.counts : null);

        return true;
    }, [workspaceId]);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                await load();
                if (!cancelled) setLoadState('idle');
            } catch {
                if (!cancelled) setLoadState('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [load]);

    async function handleRetry(job: MediaProcessingJob) {
        if (retryingId !== null) {
            return;
        }

        setRetryingId(job.id);
        setNotice(null);
        setNoticeIsError(false);

        try {
            /*
                VAR OLAN uca gidilir. Kuyruğa kendi "yeniden başlat" hattını
                yazmak, asıl korunuyor mu / sürüm açılıyor mu sorularının
                ikinci bir yerde cevaplanması demek olurdu.
            */
            const response = await fetch(
                `/api/workspaces/${workspaceId}/media/${job.mediaAssetId}/reprocess`,
                {
                    ...buildAuthRequestInit({ method: 'POST' }),
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            setNotice(t('workspace.media.queue.retried'));
            await load();
        } catch {
            setNotice(t('workspace.media.queue.retryFailed'));
            setNoticeIsError(true);
        } finally {
            setRetryingId(null);
        }
    }

    if (loadState === 'loading') {
        return <p className="text-body text-fg-muted">{t('workspace.media.queue.loading')}</p>;
    }

    if (loadState === 'error') {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.media.queue.failed')}
            </p>
        );
    }

    return (
        <section
            aria-label={t('workspace.media.queue.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.queue.lead')}</p>

            {counts === null ? null : (
                <div className="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-[var(--space-2)]">
                    <CountCard
                        testId="media-queue-count-running"
                        label={t('workspace.media.queue.count.running')}
                        value={counts.running}
                    />
                    <CountCard
                        testId="media-queue-count-succeeded"
                        label={t('workspace.media.queue.count.succeeded')}
                        value={counts.succeeded}
                        tone="text-fg-success"
                    />
                    <CountCard
                        testId="media-queue-count-failed"
                        label={t('workspace.media.queue.count.failed')}
                        value={counts.failed}
                        tone={counts.failed > 0 ? 'text-fg-danger' : undefined}
                    />
                    <CountCard
                        testId="media-queue-count-held"
                        label={t('workspace.media.queue.count.held')}
                        value={counts.held}
                        tone={counts.held > 0 ? 'text-fg-warning' : undefined}
                    />
                </div>
            )}

            {notice === null ? null : (
                <p
                    role="status"
                    className={
                        noticeIsError
                            ? 'text-body font-medium text-fg-danger'
                            : 'text-body text-fg-secondary'
                    }
                >
                    {notice}
                </p>
            )}

            {jobs.length === 0 ? (
                <p className="text-body text-fg-muted">{t('workspace.media.queue.empty')}</p>
            ) : (
                <ul className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                    {jobs.map((job) => {
                        const name = job.assetName ?? t('workspace.media.library.asset.untitled');
                        const retryable = job.state === 'failed' || job.state === 'held';

                        return (
                            <li
                                key={job.id}
                                /*
                                    Ayraç ÜSTTEDİR — alttan ayraçta son
                                    satırın çizgisi kartın kendi kenarlığıyla
                                    çakışır.
                                */
                                className="flex flex-col gap-[var(--space-2)] border-t border-border py-[var(--space-3)] first:border-t-0 first:pt-0 last:pb-0"
                            >
                                <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                                    <span className="min-w-0 flex-1 text-body text-fg">
                                        {`${t(
                                            KIND_LABEL[job.kind] ??
                                                'workspace.media.queue.kind.unknown',
                                        )} · ${name}`}
                                    </span>
                                    <span
                                        className={`text-body font-medium ${
                                            STATE_TONE[job.state] ?? 'text-fg-secondary'
                                        }`}
                                    >
                                        {t(
                                            STATE_LABEL[job.state] ??
                                                'workspace.media.queue.state.unknown',
                                        )}
                                    </span>
                                </div>

                                {/*
                                    ŞERİT yalnız BİTEN iş için dolar. Çalışan
                                    işin ilerlemesi bilinmiyor ve şerit boş
                                    kalır — `aria-valuenow` da yazılmaz,
                                    çünkü olmayan bir değeri ekran okuyucuya
                                    söylemek de bir uydurmadır.
                                */}
                                <div
                                    role="progressbar"
                                    aria-label={name}
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={
                                        job.progress === null
                                            ? undefined
                                            : Math.round(job.progress * 100)
                                    }
                                    aria-valuetext={
                                        job.progress === null
                                            ? t('workspace.media.queue.progress.unknown')
                                            : undefined
                                    }
                                    className="h-[var(--space-1)] overflow-hidden rounded-pill bg-surface-subtle"
                                >
                                    <div
                                        className={`h-full rounded-pill ${
                                            BAR_TONE[job.state] ?? 'bg-fg-muted'
                                        }`}
                                        style={{
                                            inlineSize:
                                                job.progress === null
                                                    ? '0%'
                                                    : `${Math.round(job.progress * 100)}%`,
                                        }}
                                    />
                                </div>

                                <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                                    {/*
                                        Zaman damgası ve deneme sayısı
                                        `text-meta`nın MEŞRU kullanımıdır;
                                        `tabular-nums` şarttır.
                                    */}
                                    <span className="flex-1 text-meta text-fg-muted tabular-nums">
                                        {job.finishedAt ?? job.startedAt ?? ''}
                                    </span>
                                    <span className="text-meta text-fg-muted tabular-nums">
                                        {t('workspace.media.queue.attempts', {
                                            count: String(job.attempts),
                                        })}
                                    </span>
                                    {retryable ? (
                                        <button
                                            type="button"
                                            onClick={() => void handleRetry(job)}
                                            disabled={retryingId !== null}
                                            aria-label={t('workspace.media.queue.retry.named', {
                                                name,
                                            })}
                                            className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg hover:bg-surface-hover disabled:opacity-60"
                                        >
                                            <ArrowClockwise aria-hidden="true" size={16} />
                                            {t('workspace.media.queue.retry')}
                                        </button>
                                    ) : null}
                                </div>

                                {/*
                                    Sebep yalnız bir şey ters gittiğinde
                                    yazılır. Her satırda açıklama görmeye
                                    alışan sahip, gerçek uyarıyı okumaz.
                                */}
                                {job.failureReason === null ? null : (
                                    <p className="text-body text-fg-muted">{job.failureReason}</p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default MediaJobQueueRegion;
