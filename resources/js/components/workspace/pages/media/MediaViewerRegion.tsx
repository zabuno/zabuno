import { useCallback, useEffect, useState } from 'react';
import type { KeyboardEvent } from 'react';
import {
    CaretLeft,
    CaretRight,
    DownloadSimple,
    File as FileIcon,
    FilePdf,
    Image as ImageIcon,
    X,
} from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';
import { displayName, formatBytes } from './mediaFormat';
import type { MediaAsset } from '../MediaPage';

/** Sunucunun "bu dosyayı panelde nasıl açacağız?" cevabı. */
export type MediaViewerFacts = {
    id: number;
    /** `pdf` | `image` | `other` — okuyucuyu bu seçer. */
    kind: string;
    mimeType: string;
    originalName: string;
    sizeBytes: number;
    status: string;
    embeddable: boolean;
    /** `scan` | `type` | null — açılmıyorsa NEDEN. */
    blockedReason: string | null;
    previewUrl: string | null;
    /** Yalnız baytlar gerçekten söylüyorsa dolu; aksi halde `null`. */
    pageCount: number | null;
};

type MediaViewerRegionProps = {
    workspaceId: number;
    assets: MediaAsset[];
};

type LoadState = 'loading' | 'error' | 'idle';

function isFacts(value: unknown): value is MediaViewerFacts {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const body = value as Record<string, unknown>;

    return typeof body.kind === 'string' && typeof body.embeddable === 'boolean';
}

/** Hapın ikonu dosyanın KENDİ adından okunur; karar sunucunundur. */
function chipIcon(name: string) {
    const extension = name.toLowerCase().split('.').pop() ?? '';

    if (extension === 'pdf') {
        return <FilePdf aria-hidden="true" size={18} />;
    }

    if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'heic', 'heif'].includes(extension)) {
        return <ImageIcon aria-hidden="true" size={18} />;
    }

    return <FileIcon aria-hidden="true" size={18} />;
}

/**
 * Etiket + değer. `numeric` yalnız ÖLÇÜ ve SAYAÇ içindir (boyut, sayfa
 * sayısı): `text-meta` ile küçülür ve `tabular-nums` ile sütun titremez.
 * Dosyanın türü bir ölçü değildir; o gövde metniyle yazılır.
 */
function FactRow({ label, value, numeric }: { label: string; value: string; numeric?: boolean }) {
    return (
        <div className="flex min-h-[var(--density-row-height)] flex-wrap items-baseline gap-[var(--space-2)] border-t border-border py-[var(--space-2)] first:border-t-0">
            <span className="flex-1 text-body text-fg-secondary">{label}</span>
            <span
                className={
                    numeric === true
                        ? 'text-meta font-medium text-fg tabular-nums'
                        : 'text-body font-medium text-fg'
                }
            >
                {value}
            </span>
        </div>
    );
}

/**
 * GÖRÜNTÜLE — dosya türüne göre okuyucu (kanonik kaynak:
 * `docs/reference/media-manager/Medya Yonetimi v2.dc.html`, ekran etiketi
 * "Görüntüle"; sıra `docs/108` §3 madde 8).
 *
 * Restoran sahibi kütüphanede bir belge satırı görüyor ve içinde ne
 * yazdığını okumak istiyor. Bugün yapabildiği tek şey İNDİRMEK: dosya
 * telefonunda indirilenler klasörüne düşüyor, başka bir uygulamada
 * açılıyor, panele döndüğünde hangi dosyaya baktığını unutuyor.
 *
 * DÖRT KARAR bu bölümü biçimlendirir:
 *
 *  1. TÜRÜ EKRAN TAHMİN ETMEZ. Kütüphane listesinde MIME türü yok, yalnız
 *     kullanıcının yazdığı ad var — ve uzantı YÜKLEYENİN denetimindedir.
 *     Türü sunucu söyler (`media/{id}/viewer`); hapın ikonu adından
 *     türetilir, çünkü ikon bir karar değil bir ipucudur.
 *
 *  2. SAYFA SAYISI UYDURULMAZ. Sunucu sayfa sayısını ancak dosyanın kendi
 *     baytları söylüyorsa verir. Bilinmiyorsa bu ekran sayfa gezintisini
 *     HİÇ çizmez: nereye gittiğini bilmeyen bir "ileri" düğmesi, son
 *     sayfadan sonra sessizce yalan söylerdi — etiket "Sayfa 40" derken
 *     belge 12'de durur.
 *
 *  3. GÖMÜLÜ PDF BİR SÖZ DEĞİLDİR. Panel tarayıcının KENDİ PDF
 *     görüntüleyicisini bir `<iframe>` ile kullanır; yeni bir kütüphane
 *     kurulmaz. Bazı tarayıcılar (özellikle mobil Safari ve Android
 *     Chrome, ayrıca `sandbox`lı bir yanıtta bazı masaüstü sürümleri)
 *     gömülü PDF açmaz ve çerçeve boş kalır. Bunu tespit etmenin güvenilir
 *     bir yolu yok, o yüzden UYDURULMAZ: çerçevenin altında her zaman
 *     "açılmazsa indir" cümlesi ve indirme düğmesi durur.
 *
 *  4. AÇILAMAYAN DOSYA ÇIKMAZ SOKAK DEĞİLDİR. Taraması temiz dönmemiş ya
 *     da desteklenmeyen türdeki dosya sebebiyle söylenir ve bir sonraki
 *     adım (indir, ya da taramanın bitmesini beklemek) aynı ekranda durur.
 *
 * Sayfa değişimi çerçeveyi YENİDEN YÜKLER (`key` değişir). Adres
 * parçacığını (`#page=`) yerinde değiştirmek daha ucuz olurdu ama her
 * tarayıcının gömülü okuyucusu parçacık değişimine yanıt vermez; yeniden
 * yükleme, açabilen her tarayıcıda istenen sayfayı gerçekten açar.
 */
export function MediaViewerRegion({ workspaceId, assets }: MediaViewerRegionProps) {
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [dismissed, setDismissed] = useState(false);
    const [facts, setFacts] = useState<MediaViewerFacts | null>(null);
    const [loadState, setLoadState] = useState<LoadState>('loading');
    const [page, setPage] = useState(1);
    const [downloadError, setDownloadError] = useState(false);

    /*
        Seçim TÜRETİLİR: liste geç geldiğinde ilk dosya kendiliğinden
        açılır, ama kullanıcı kapattıysa (`dismissed`) ekran onu geri
        açmaz — kapattığı şeyin geri gelmesi, kapatma düğmesini bozuk
        gösterirdi.
    */
    const activeId = selectedId ?? (dismissed ? null : (assets[0]?.id ?? null));
    const activeAsset = assets.find((asset) => asset.id === activeId) ?? null;

    /*
        DOSYA DEĞİŞTİĞİNDE eski dosyanın cevabı ve sayfası ANINDA düşer —
        ve bu sıfırlama RENDER sırasında yapılır, `useEffect` içinde değil.
        Efekt içinde yapılsaydı ekran bir kare boyunca ESKİ dosyanın
        sayfasını ve türünü yeni dosyanın adıyla birlikte gösterirdi;
        React'in kendi kuralı da (`react-hooks/set-state-in-effect`) bunu
        yasaklar.
    */
    const [loadedFor, setLoadedFor] = useState<number | null>(activeId);

    if (loadedFor !== activeId) {
        setLoadedFor(activeId);
        setFacts(null);
        setLoadState('loading');
        setPage(1);
        setDownloadError(false);
    }

    useEffect(() => {
        if (activeId === null) {
            return;
        }

        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media/${activeId}/viewer`,
                    buildAuthRequestInit(),
                );

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const body = (await response.json()) as unknown;

                if (cancelled) {
                    return;
                }

                if (!isFacts(body)) {
                    setLoadState('error');

                    return;
                }

                setFacts(body);
                setLoadState('idle');
            } catch {
                if (!cancelled) {
                    setLoadState('error');
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId, activeId]);

    const totalPages = facts?.pageCount ?? null;

    const goToPage = useCallback(
        (next: number) => {
            if (totalPages === null) {
                return;
            }

            setPage((current) => {
                const target = Math.min(Math.max(next, 1), totalPages);

                return target === current ? current : target;
            });
        },
        [totalPages],
    );

    function close() {
        setSelectedId(null);
        setDismissed(true);
    }

    /*
        KLAVYE tek başına yeter (`docs/37` erişilebilirlik): ileri/geri ok
        tuşlarıyla, kapatma Escape ile. Dinleyici BÖLÜMÜN üzerindedir,
        belgenin değil: sayfanın herhangi bir yerinde basılan Escape'i
        yakalamak, başka bir bileşenin kendi kapatma tuşunu çalardı.
    */
    function handleKeyDown(event: KeyboardEvent<HTMLElement>) {
        if (activeId === null) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close();

            return;
        }

        if (totalPages === null || facts?.kind !== 'pdf') {
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goToPage(page + 1);
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goToPage(page - 1);
        }
    }

    async function handleDownload() {
        if (activeId === null) {
            return;
        }

        setDownloadError(false);

        try {
            const response = await fetch(
                `/api/workspaces/${workspaceId}/media/${activeId}/download-link`,
                {
                    ...buildAuthRequestInit({ method: 'POST' }),
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const body = (await response.json()) as { url?: string };

            if (typeof body.url !== 'string') {
                throw new Error('no-url');
            }

            window.open(body.url, '_blank', 'noopener,noreferrer');
        } catch {
            setDownloadError(true);
        }
    }

    function downloadButton() {
        return (
            <div className="flex flex-col gap-[var(--space-1)]">
                <button
                    type="button"
                    onClick={() => void handleDownload()}
                    className="inline-flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] self-start rounded-[var(--radius-md)] border border-border px-[var(--space-3)] text-body font-medium text-fg hover:bg-surface-hover"
                >
                    <DownloadSimple aria-hidden="true" size={18} />
                    {t('workspace.media.viewer.download')}
                </button>
                {downloadError ? (
                    <p role="alert" className="text-body text-fg-danger">
                        {t('workspace.media.viewer.download.failed')}
                    </p>
                ) : null}
            </div>
        );
    }

    function body() {
        if (loadState === 'loading') {
            return <p className="text-body text-fg-muted">{t('workspace.media.viewer.loading')}</p>;
        }

        if (loadState === 'error' || facts === null) {
            return (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.media.viewer.failed')}
                </p>
            );
        }

        return (
            <div className="flex flex-col gap-[var(--space-3)]">
                <div className="flex flex-wrap items-baseline gap-[var(--space-2)]">
                    <span className="flex-1 text-body font-medium text-fg">
                        {facts.originalName}
                    </span>
                    <button
                        type="button"
                        onClick={close}
                        aria-label={t('workspace.media.viewer.close')}
                        className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] border border-border text-fg-secondary hover:bg-surface-hover"
                    >
                        <X aria-hidden="true" size={18} />
                    </button>
                </div>

                {facts.embeddable ? null : (
                    <div className="flex flex-col gap-[var(--space-2)]">
                        <p className="text-body text-fg-secondary">
                            {facts.blockedReason === 'scan'
                                ? t('workspace.media.viewer.blocked.scan')
                                : t('workspace.media.viewer.blocked.type', {
                                      type: facts.mimeType,
                                  })}
                        </p>
                        {/*
                            Tarama bitmemişse indirme de doğru adım
                            değildir: taranmamış dosyayı kullanıcının
                            kendi makinesine indirtmek, panelde açmaktan
                            daha güvenli değildir.
                        */}
                        {facts.blockedReason === 'type' ? downloadButton() : null}
                    </div>
                )}

                {facts.embeddable && facts.kind === 'image' && facts.previewUrl !== null ? (
                    <div className="grid aspect-[4/3] w-full place-items-center overflow-hidden rounded-[var(--radius-lg)] border border-border bg-surface-subtle">
                        <img
                            src={facts.previewUrl}
                            alt={
                                activeAsset === null ? facts.originalName : displayName(activeAsset)
                            }
                            className="max-h-full max-w-full object-contain"
                        />
                    </div>
                ) : null}

                {facts.embeddable && facts.kind === 'pdf' && facts.previewUrl !== null ? (
                    <div className="flex flex-col gap-[var(--space-2)]">
                        {totalPages === null ? (
                            <p className="text-body text-fg-muted">
                                {t('workspace.media.viewer.pdf.pagesUnknown')}
                            </p>
                        ) : (
                            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                                <button
                                    type="button"
                                    onClick={() => goToPage(page - 1)}
                                    disabled={page <= 1}
                                    aria-label={t('workspace.media.viewer.pdf.previous')}
                                    className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] border border-border text-fg hover:bg-surface-hover disabled:opacity-60"
                                >
                                    <CaretLeft aria-hidden="true" size={18} />
                                </button>
                                <button
                                    type="button"
                                    onClick={() => goToPage(page + 1)}
                                    disabled={page >= totalPages}
                                    aria-label={t('workspace.media.viewer.pdf.next')}
                                    className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] border border-border text-fg hover:bg-surface-hover disabled:opacity-60"
                                >
                                    <CaretRight aria-hidden="true" size={18} />
                                </button>
                                {/* Sayaç: `text-meta` burada meşru, rakam sütunu titremesin diye `tabular-nums`. */}
                                <span
                                    aria-live="polite"
                                    className="text-meta text-fg-secondary tabular-nums"
                                >
                                    {t('workspace.media.viewer.pdf.page', {
                                        page: String(page),
                                        total: String(totalPages),
                                    })}
                                </span>
                            </div>
                        )}

                        <iframe
                            key={`${facts.previewUrl}#page=${page}`}
                            src={`${facts.previewUrl}#page=${page}`}
                            title={t('workspace.media.viewer.pdf.frame', {
                                name: facts.originalName,
                            })}
                            className="aspect-[4/3] w-full rounded-[var(--radius-lg)] border border-border bg-surface-subtle"
                        />

                        <p className="text-body text-fg-muted">
                            {t('workspace.media.viewer.pdf.embedNote')}
                        </p>
                        {downloadButton()}
                    </div>
                ) : null}

                <div className="flex flex-col rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
                    <FactRow label={t('workspace.media.viewer.fact.type')} value={facts.mimeType} />
                    <FactRow
                        label={t('workspace.media.viewer.fact.size')}
                        value={formatBytes(facts.sizeBytes)}
                        numeric
                    />
                    {/* Sayfa satırı yalnız GERÇEKTEN okunduğunda çizilir. */}
                    {totalPages === null ? null : (
                        <FactRow
                            label={t('workspace.media.viewer.fact.pages')}
                            value={String(totalPages)}
                            numeric
                        />
                    )}
                </div>
            </div>
        );
    }

    return (
        <section
            aria-label={t('workspace.media.viewer.region')}
            onKeyDown={handleKeyDown}
            className="flex flex-col gap-[var(--space-4)]"
        >
            <p className="text-body text-fg-secondary">{t('workspace.media.viewer.lead')}</p>

            {assets.length === 0 ? (
                <p className="text-body text-fg-muted">{t('workspace.media.viewer.empty')}</p>
            ) : (
                <>
                    {/*
                        DOSYA HAPLARI (kaynağın kendi şeridi): hangi dosyaya
                        bakıldığı her an yazılıdır. Şerit yatay kayar, çünkü
                        elli dosyayı alt alta dizmek okuyucuyu ekrandan
                        aşağı iterdi.
                    */}
                    <div
                        role="group"
                        aria-label={t('workspace.media.viewer.files')}
                        className="flex gap-[var(--space-2)] overflow-x-auto"
                    >
                        {assets.map((asset) => {
                            const name = asset.originalName ?? displayName(asset);
                            const active = asset.id === activeId;

                            return (
                                <button
                                    key={asset.id}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => {
                                        setSelectedId(asset.id);
                                        setDismissed(false);
                                    }}
                                    /*
                                        Seçili hap yalnız RENKLE ayrılmaz:
                                        kenarı koyulaşır ve yazısı kalınlaşır
                                        (kabuğun sekmeleriyle aynı gramer).
                                        Tek başına arka plan tonu, yüksek
                                        karşıtlık kipinde ya da renk görmeyen
                                        gözde hiç ayrım bırakmazdı.
                                    */
                                    className={`inline-flex min-h-[var(--control-height)] flex-none items-center gap-[var(--space-2)] rounded-[var(--radius-md)] border px-[var(--space-3)] text-body whitespace-nowrap text-fg hover:bg-surface-hover ${
                                        active
                                            ? 'border-border-strong bg-surface-active font-bold'
                                            : 'border-border font-medium'
                                    }`}
                                >
                                    {chipIcon(name)}
                                    {name}
                                </button>
                            );
                        })}
                    </div>

                    {activeId === null ? (
                        <p className="text-body text-fg-muted">
                            {t('workspace.media.viewer.none')}
                        </p>
                    ) : (
                        body()
                    )}
                </>
            )}
        </section>
    );
}
