import { useCallback, useEffect, useMemo, useState } from 'react';
import { Check, CheckCircle, FilmStrip, Image, Swap } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { formatBytes } from './mediaFormat';

export type MediaConversionTarget = {
    format: string;
    family: string;
    /**
     * Kaynağın yüzdesi — biçimin GENEL iddiası, bu kiracının ölçümü DEĞİL.
     * Adı bunu söylemek zorunda; söylemeseydi ekranda ölçümle karışırdı.
     */
    claimedSavingPercent: number;
    /** BU KURULUMDA yapılabiliyor mu (`gd_info()` ve video hattı). */
    supported: boolean;
    /** Yapılamıyorsa sebep kodu; cümle burada, katalogda. */
    limitation: string | null;
};

export type MediaConversionSource = {
    id: number;
    name: string;
    sizeBytes: number;
    format: string;
};

export type MediaConversionTargets = {
    targets: MediaConversionTarget[];
    sources: MediaConversionSource[];
    /** Biçim başına GERÇEKTEN tartılmış bayt; ölçüm yoksa biçim hiç yok. */
    measured: Record<string, { assets: number; originalBytes: number; convertedBytes: number }>;
    batchLimit: number;
};

type MediaConvertRegionProps = {
    workspaceId: number;
};

type LoadState = 'loading' | 'error' | 'idle';

/**
 * Biçim adı EKRANDA nasıl yazılır. "webp" bir anahtar, "WebP" bir addır ve
 * kaynak kartlarda ikincisini yazıyor.
 */
const FORMAT_LABEL: Record<string, string> = {
    avif: 'AVIF',
    webp: 'WebP',
    webm: 'WebM',
    jpeg: 'JPEG',
};

/** Her hedefin İŞİ — kaynağın kart altı cümlesi (`docs/108` §6.3). */
const TARGET_NOTE: Record<string, Parameters<typeof t>[0]> = {
    avif: 'workspace.media.convert.target.avif.note',
    webp: 'workspace.media.convert.target.webp.note',
    webm: 'workspace.media.convert.target.webm.note',
    jpeg: 'workspace.media.convert.target.jpeg.note',
};

/**
 * Sunucunun sebep KODU, sahibin okuyacağı CÜMLEYE. Sunucu sebebi bilir; o
 * sebebi hangi dilde nasıl anlatacağını ürün bilir (`docs/37`).
 */
const LIMITATION_LABEL: Record<string, Parameters<typeof t>[0]> = {
    'no-video-pipeline': 'workspace.media.convert.limitation.noVideoPipeline',
    'encoder-missing': 'workspace.media.convert.limitation.encoderMissing',
};

function isTargets(value: unknown): value is MediaConversionTargets {
    if (typeof value !== 'object' || value === null) return false;
    const body = value as Record<string, unknown>;
    return Array.isArray(body.targets) && Array.isArray(body.sources);
}

function formatLabel(format: string): string {
    return FORMAT_LABEL[format] ?? format.toUpperCase();
}

/** Etiket + değer: değer daima `tabular-nums`, çünkü hepsi rakamdır. */
function StatRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border py-[var(--space-2)] first:border-t-0">
            <span className="flex-1 text-body text-fg-secondary">{label}</span>
            <span className="text-meta font-medium text-fg tabular-nums">{value}</span>
        </div>
    );
}

/**
 * DÖNÜŞTÜR — "eski biçimleri modern biçime çevir" (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Dönüştür"; hedef listesi `docs/108` §6.3).
 *
 * Kaynağın kendi cümlesi bu bölümün sözleşmesidir: "Aslı korunur, dönüşen
 * dosya yeni sürüm olur." Sahip bir dosyayı dönüştürüp pişman olabilir;
 * eski sürüm durduğu için geri dönüş vardır ve bu ekranda YAZILIDIR.
 *
 * ÜÇ DÜRÜSTLÜK KURALI bu bölümü biçimlendirir:
 *
 *   1. KAYNAĞIN LİSTESİ TAM, ÜRÜNÜN YETENEĞİ DEĞİL. Bu kurulumda video
 *      dönüştüren bir hat yok. WebM kartı yine de çizilir — gizlemek
 *      kaynağı sessizce kısaltmak olurdu — ama "bu kurulumda yapılamıyor"
 *      der ve SEÇİLEMEZ. Seçilebilseydi sahip düğmeye basar ve yalnız
 *      başarısızlık toplardı.
 *   2. İDDİA İLE ÖLÇÜM AYNI SATIRDA DURMAZ. "~%74" biçimin genel
 *      iddiasıdır ve "yaklaşık" diye yazılır; BU kiracının tartılmış
 *      kazancı ayrı bir cümledir ve yalnız gerçekten ölçüldüyse görünür.
 *   3. Sonuç üç sayıyı birden söyler: dönüşen, düşen ve KALAN. "Bitti"
 *      demek, biri düşmüşken sahibi yanıltır; kalanı söylememek de onu
 *      ekranın önünde beklemeye bırakır.
 *
 * Kaynak dosyalar KART DEĞİL, tek kartın içinde AYRAÇLI SATIRDIR: elli
 * dosya elli kart olsaydı liste elli ayrı duyuru gibi okunurdu (kütüphane
 * ve boyut motoruyla aynı gramer).
 */
export function MediaConvertRegion({ workspaceId }: MediaConvertRegionProps) {
    const [data, setData] = useState<MediaConversionTargets | null>(null);
    const [loadState, setLoadState] = useState<LoadState>('loading');
    const [format, setFormat] = useState<string | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [converting, setConverting] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const [noticeIsError, setNoticeIsError] = useState(false);

    const load = useCallback(async (): Promise<MediaConversionTargets | null> => {
        const response = await fetch(
            `/api/workspaces/${workspaceId}/media/conversion-targets`,
            buildAuthRequestInit(),
        );

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const body = (await response.json()) as unknown;

        return isTargets(body) ? body : null;
    }, [workspaceId]);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const body = await load();
                if (cancelled) return;
                setData(body);
                setLoadState(body === null ? 'error' : 'idle');
                if (body !== null) {
                    /*
                        Varsayılan hedef, YAPILABİLEN ilk hedeftir. Kaynağın
                        ilk kartı AVIF; ama bu sunucu AVIF üretemiyorsa
                        varsayılanı orada bırakmak, ekranı açar açmaz
                        kapalı bir düğme göstermek olurdu.
                    */
                    setFormat(body.targets.find((target) => target.supported)?.format ?? null);
                }
            } catch {
                if (!cancelled) setLoadState('error');
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [load]);

    const sources = data?.sources ?? [];
    const selected = useMemo(
        () => sources.filter((source) => selectedIds.includes(source.id)),
        [sources, selectedIds],
    );
    const selectedBytes = selected.reduce((total, source) => total + source.sizeBytes, 0);
    const allSelected = sources.length > 0 && selected.length === sources.length;

    function toggleAll() {
        setSelectedIds(allSelected ? [] : sources.map((source) => source.id));
    }

    function toggleOne(id: number) {
        setSelectedIds((current) =>
            current.includes(id) ? current.filter((one) => one !== id) : [...current, id],
        );
    }

    async function handleConvert() {
        if (converting || format === null || selectedIds.length === 0) {
            return;
        }

        setConverting(true);
        setNotice(null);
        setNoticeIsError(false);

        try {
            const response = await fetch(`/api/workspaces/${workspaceId}/media/convert`, {
                ...buildAuthRequestInit({ method: 'POST' }),
                credentials: 'same-origin',
                // Sıra KORUNUR: sahip listede gördüğü sırayla seçti ve
                // sunucu sınıra takılırsa aynı sırayla keser.
                body: JSON.stringify({
                    format,
                    assetIds: sources
                        .filter((source) => selectedIds.includes(source.id))
                        .map((source) => source.id),
                }),
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const body = (await response.json()) as {
                succeeded?: number;
                failed?: number;
                remaining?: number;
            };

            const parts = [
                t('workspace.media.convert.done', { count: String(body.succeeded ?? 0) }),
            ];

            if ((body.failed ?? 0) > 0) {
                parts.push(
                    t('workspace.media.convert.someFailed', { count: String(body.failed ?? 0) }),
                );
            }

            if ((body.remaining ?? 0) > 0) {
                parts.push(
                    t('workspace.media.convert.remaining', { count: String(body.remaining ?? 0) }),
                );
            }

            setNotice(parts.join(' '));

            // Ölçülen kazanç dönüşümden SONRA değişir; eski sayıyı ekranda
            // bırakmak, yapılan işi yapılmamış gösterirdi.
            const refreshed = await load();
            if (refreshed !== null) {
                setData(refreshed);
            }
        } catch {
            setNotice(t('workspace.media.convert.runFailed'));
            setNoticeIsError(true);
        } finally {
            setConverting(false);
        }
    }

    if (loadState === 'loading') {
        return <p className="text-body text-fg-muted">{t('workspace.media.convert.loading')}</p>;
    }

    if (loadState === 'error' || data === null) {
        return (
            <p role="alert" className="text-body text-fg-danger">
                {t('workspace.media.convert.failed')}
            </p>
        );
    }

    const targetLabel = format === null ? '' : formatLabel(format);
    const measured = format === null ? undefined : data.measured[format];
    /*
        ÖLÇÜLEN KAZANÇ ancak GERÇEKTEN ölçüldüyse yazılır: hiç dönüştürülmüş
        dosya yoksa, ya da çıktı asıldan küçük değilse ortada gösterilecek
        bir kazanç yoktur ve olmayan bir kazancı yazmak yalan olurdu.
    */
    const hasMeasured =
        measured !== undefined &&
        measured.assets > 0 &&
        measured.originalBytes > 0 &&
        measured.convertedBytes > 0 &&
        measured.convertedBytes < measured.originalBytes;

    const measuredPercent = hasMeasured
        ? Math.round(
              ((measured.originalBytes - measured.convertedBytes) / measured.originalBytes) * 100,
          )
        : 0;

    return (
        <section
            aria-label={t('workspace.media.convert.region')}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.convert.lead')}</p>

            <section className="flex flex-col gap-[var(--space-2)]">
                <h3 className="text-body font-bold text-fg">
                    {t('workspace.media.convert.target.heading')}
                </h3>

                {/*
                    Hedefler BİRBİRİNİN ALTERNATİFİDİR: bir dosya aynı anda
                    hem AVIF hem WebP'ye çevrilmez. Onay kutusu değil radyo
                    grubu; ekran okuyucu kullanan biri "dört seçenekten
                    biri" olduğunu buradan duyar.
                */}
                <div
                    role="radiogroup"
                    aria-label={t('workspace.media.convert.target.heading')}
                    className="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-[var(--space-2)]"
                >
                    {data.targets.map((target) => {
                        const active = target.format === format;

                        return (
                            <button
                                key={target.format}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                disabled={!target.supported}
                                onClick={() => setFormat(target.format)}
                                className={`flex min-h-[var(--control-height)] flex-col items-start gap-[var(--space-1)] rounded-[var(--radius-lg)] border p-[var(--space-3)] text-start ${
                                    active
                                        ? 'border-action bg-surface-active'
                                        : 'border-border bg-surface'
                                } disabled:opacity-60`}
                            >
                                <span className="flex w-full items-center gap-[var(--space-2)]">
                                    {target.family === 'video' ? (
                                        <FilmStrip aria-hidden="true" size={20} />
                                    ) : (
                                        <Image aria-hidden="true" size={20} />
                                    )}
                                    <span className="flex-1 text-body font-bold text-fg">
                                        {formatLabel(target.format)}
                                    </span>
                                    {active ? (
                                        <CheckCircle aria-hidden="true" size={18} weight="fill" />
                                    ) : null}
                                </span>

                                <span className="text-body text-fg-secondary">
                                    {t(
                                        TARGET_NOTE[target.format] ??
                                            'workspace.media.convert.target.jpeg.note',
                                    )}
                                </span>

                                {/*
                                    İDDİA. "yaklaşık" bilerek duruyor: bu
                                    biçimin genel davranışıdır, bu kiracının
                                    dosyalarının tartısı değil.
                                */}
                                <span className="text-meta text-fg-success tabular-nums">
                                    {t('workspace.media.convert.target.claim', {
                                        percent: String(target.claimedSavingPercent),
                                    })}
                                </span>

                                {target.supported ? null : (
                                    <span className="text-body text-fg-warning">
                                        {t(
                                            LIMITATION_LABEL[target.limitation ?? ''] ??
                                                'workspace.media.convert.limitation.unknown',
                                        )}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>

                {hasMeasured ? (
                    <p className="text-body font-medium text-fg tabular-nums">
                        {t('workspace.media.convert.measured', {
                            count: String(measured.assets),
                            percent: String(measuredPercent),
                        })}
                    </p>
                ) : null}
            </section>

            {/*
                Kaynak dosyalar TEK kartın içidir; ayrım 1 piksellik
                çizgidir. Kart başına bir dosya, elli ayrı duyuru gibi
                okunurdu.
            */}
            <section className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface">
                <div className="flex flex-wrap items-center gap-[var(--space-2)] border-b border-border p-[var(--space-3)]">
                    <h3 className="flex-1 text-body font-bold text-fg">
                        {t('workspace.media.convert.sources.heading')}
                    </h3>
                    <button
                        type="button"
                        onClick={toggleAll}
                        disabled={sources.length === 0}
                        className="inline-flex min-h-[var(--control-height)] items-center rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg hover:bg-surface-hover disabled:opacity-60"
                    >
                        {allSelected
                            ? t('workspace.media.convert.clearAll')
                            : t('workspace.media.convert.selectAll')}
                    </button>
                </div>

                {sources.length === 0 ? (
                    <p className="p-[var(--space-3)] text-body text-fg-muted">
                        {t('workspace.media.convert.sources.empty')}
                    </p>
                ) : (
                    <ul className="flex flex-col">
                        {sources.map((source) => {
                            const on = selectedIds.includes(source.id);

                            return (
                                <li
                                    key={source.id}
                                    className="flex min-h-[var(--density-row-height)] items-center gap-[var(--space-3)] border-t border-border px-[var(--space-3)] py-[var(--space-2)] first:border-t-0"
                                >
                                    <button
                                        type="button"
                                        role="checkbox"
                                        aria-checked={on}
                                        aria-label={source.name}
                                        onClick={() => toggleOne(source.id)}
                                        className={`grid size-[1.5rem] flex-none place-items-center rounded-[var(--radius-sm)] border ${
                                            on
                                                ? 'border-action bg-action text-action-fg'
                                                : 'border-border bg-surface text-fg-muted'
                                        }`}
                                    >
                                        {on ? (
                                            <Check aria-hidden="true" size={14} weight="bold" />
                                        ) : null}
                                    </button>

                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="truncate text-body text-fg">
                                            {source.name}
                                        </span>
                                        {/*
                                            Dönüşümün YÖNÜ. "jpeg" yalnız
                                            başına bir bilgi değil; sahibin
                                            okuduğu şey "neyden neye".
                                        */}
                                        <span className="text-body text-fg-muted">
                                            {t('workspace.media.convert.row.direction', {
                                                from: source.format,
                                                to: targetLabel,
                                            })}
                                        </span>
                                    </span>

                                    <span className="flex-none text-meta text-fg-secondary tabular-nums">
                                        {formatBytes(source.sizeBytes)}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            <section className="flex flex-col gap-[var(--space-2)] rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                <div className="flex flex-col">
                    <StatRow
                        label={t('workspace.media.convert.summary.selected')}
                        value={`${selected.length} / ${sources.length}`}
                    />
                    <StatRow
                        label={t('workspace.media.convert.summary.now')}
                        value={formatBytes(selectedBytes)}
                    />
                    {/*
                        Kaynak burada "Sonra (tahmini)" yazıyor. O satır
                        BİLEREK yok: tahmin bir ölçüm değildir ve dönüşümden
                        önce bu kiracının dosyalarının ne kadar küçüleceğini
                        kimse bilmiyor. Yerine, tek çağrının işleyebileceği
                        dosya sayısı yazılır — sahibin gerçekten karar
                        vermesi gereken sayı budur.
                    */}
                    <StatRow
                        label={t('workspace.media.convert.summary.batch')}
                        value={String(data.batchLimit)}
                    />
                </div>

                <button
                    type="button"
                    onClick={() => void handleConvert()}
                    disabled={converting || format === null || selected.length === 0}
                    className="inline-flex min-h-[var(--control-height)] items-center justify-center gap-[var(--space-2)] self-start rounded-[var(--radius-md)] bg-action px-[var(--space-4)] text-body font-bold text-action-fg disabled:opacity-60"
                >
                    <Swap aria-hidden="true" size={18} />
                    {converting
                        ? t('workspace.media.convert.running')
                        : selected.length === 0
                          ? t('workspace.media.convert.start.empty')
                          : t('workspace.media.convert.start', {
                                count: String(selected.length),
                                format: targetLabel,
                            })}
                </button>

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
            </section>
        </section>
    );
}

export default MediaConvertRegion;
