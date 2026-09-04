import { useState, type ReactNode } from 'react';
import { CaretLeft, CaretRight, Folder, FolderOpen, Images } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';

export type MediaFolderId = number | string;

export type MediaFolder = {
    id: MediaFolderId;
    name: string;
    /** Klasördeki dosya sayısı — sunucu vermezse hiç yazılmaz. */
    assetCount?: number;
};

type MediaFolderRailProps = {
    folders: MediaFolder[];
    activeFolderId: MediaFolderId | null;
    onSelect: (id: MediaFolderId | null) => void;
    /** Şeridin altındaki depolama kutusu (kota bölgesi) — isteğe bağlı. */
    children?: ReactNode;
};

/**
 * Bir sayfada kaç klasör durur.
 *
 * Kaynak şeridi KAYDIRIYOR; kaydırma, elli klasörde "listenin sonu nerede?"
 * sorusunu doğurur ve şeridin yanındaki dosya ızgarasıyla iki ayrı kaydırma
 * alanı yarışır. Sayfalama bunun yerine sabit bir yükseklik verir: şerit
 * hiçbir zaman ekranı yutmaz.
 */
const PAGE_SIZE = 8;

/**
 * KLASÖR ŞERİDİ (kanonik kaynak, "Medya yönetimi" kabuğunun sol sütunu).
 *
 * `docs/108` §3 madde 1: bugün elli fotoğraf tek düz listede duruyor ve
 * arama, ADINI HATIRLAMADIĞIN dosyayı bulmaz. "Geçen yazki kampanya
 * görselleri" bir klasör adıdır, bir arama terimi değil.
 *
 * Klasör uçları bu depoya henüz inmedi. Bu yüzden şerit KENDİ verisini
 * çekmez: klasör listesi dışarıdan verilir ve boş geldiğinde şerit hiç
 * çizilmez — uydurma bir "Genel" klasörü, sahibi olmayan bir yere
 * tıklatmaktır.
 */
export function MediaFolderRail({
    folders,
    activeFolderId,
    onSelect,
    children,
}: MediaFolderRailProps) {
    const [page, setPage] = useState(0);

    if (folders.length === 0) {
        /*
            Klasör yoksa ŞERİT DE YOKTUR — depolama kutusu varsa yalnız o
            durur, sarmalayıcı bile çizilmez. Boş bir sarmalayıcı geniş
            ekranda 15rem'lik bir sütunu işgal eder ve yanındaki dosya
            ızgarasını sebepsiz daraltır.
        */
        return <>{children ?? null}</>;
    }

    const pageCount = Math.max(1, Math.ceil(folders.length / PAGE_SIZE));
    const safePage = Math.min(page, pageCount - 1);
    const visible = folders.slice(safePage * PAGE_SIZE, safePage * PAGE_SIZE + PAGE_SIZE);

    function rowClass(active: boolean): string {
        /*
            Şerit satırı KART DEĞİLDİR: kutu burada bilgi taşımaz, satırlar
            zaten alt alta ve tek ritimdedir. Seçili olan RENKLE ayrışır.
        */
        return [
            'flex w-full min-h-[var(--density-row-height)] items-center gap-[var(--space-2)]',
            'rounded-[var(--radius-md)] px-[var(--space-2)] text-start text-body',
            active ? 'bg-surface-active font-bold text-fg' : 'font-medium text-fg-secondary',
        ].join(' ');
    }

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            <ul
                aria-label={t('workspace.media.folders.heading')}
                className="flex flex-col gap-[var(--space-1)]"
            >
                <li>
                    <button
                        type="button"
                        aria-current={activeFolderId === null ? 'true' : undefined}
                        onClick={() => onSelect(null)}
                        className={rowClass(activeFolderId === null)}
                    >
                        <Images aria-hidden="true" size={20} weight="regular" />
                        <span className="min-w-0 flex-1 truncate">
                            {t('workspace.media.folders.all')}
                        </span>
                    </button>
                </li>
                {visible.map((folder) => {
                    const active = folder.id === activeFolderId;
                    const Icon = active ? FolderOpen : Folder;

                    return (
                        <li key={String(folder.id)}>
                            <button
                                type="button"
                                aria-current={active ? 'true' : undefined}
                                onClick={() => onSelect(folder.id)}
                                className={rowClass(active)}
                            >
                                <Icon aria-hidden="true" size={20} weight="regular" />
                                <span className="min-w-0 flex-1 truncate">{folder.name}</span>
                                {/* Sayaç `text-meta`nın meşru kullanımıdır; sabit rakam. */}
                                {folder.assetCount === undefined ? null : (
                                    <span className="text-meta text-fg-muted tabular-nums">
                                        {String(folder.assetCount)}
                                    </span>
                                )}
                            </button>
                        </li>
                    );
                })}
            </ul>

            {/*
                Sayfa kontrolü YALNIZ taşma varsa çizilir: tek sayfalık bir
                listede "sonraki" düğmesi, hiçbir zaman bir yere götürmeyen
                bir kontroldür.
            */}
            {pageCount > 1 ? (
                <div className="flex items-center gap-[var(--space-2)]">
                    {safePage > 0 ? (
                        <button
                            type="button"
                            aria-label={t('workspace.media.folders.previous')}
                            onClick={() => setPage(safePage - 1)}
                            className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] border border-border text-fg-secondary"
                        >
                            <CaretLeft aria-hidden="true" size={18} />
                        </button>
                    ) : null}
                    <span className="text-meta text-fg-muted tabular-nums">
                        {t('workspace.media.folders.page', {
                            page: String(safePage + 1),
                            total: String(pageCount),
                        })}
                    </span>
                    {safePage < pageCount - 1 ? (
                        <button
                            type="button"
                            aria-label={t('workspace.media.folders.next')}
                            onClick={() => setPage(safePage + 1)}
                            className="grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] border border-border text-fg-secondary"
                        >
                            <CaretRight aria-hidden="true" size={18} />
                        </button>
                    ) : null}
                </div>
            ) : null}

            {children}
        </div>
    );
}

export default MediaFolderRail;
