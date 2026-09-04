import { type ReactNode } from 'react';
import { ListBullets, SlidersHorizontal, SortAscending, SquaresFour } from '@phosphor-icons/react';
import { t } from '../../../../i18n/workspace';
import type { MediaFolder, MediaFolderId } from './MediaFolderRail';
import { MEDIA_SORT_LABEL_KEY, type MediaSortKey } from './mediaSort';

export type MediaLibraryView = 'list' | 'grid';

type MediaLibraryToolbarProps = {
    folders?: MediaFolder[];
    activeFolderId?: MediaFolderId | null;
    onFolderChange?: (id: MediaFolderId | null) => void;

    filtersOpen: boolean;
    onToggleFilters: () => void;
    activeFilterCount: number;
    /** Açık süzgeç paneli — kapalıyken hiç çizilmez. */
    children?: ReactNode;

    sort: MediaSortKey;
    onSortCycle: () => void;

    view: MediaLibraryView;
    onViewChange: (view: MediaLibraryView) => void;

    resultLabel: string;
    selectedCount: number;
    allSelected: boolean;
    onToggleSelectAll: () => void;
    onDeleteSelected: () => void;
    bulkNotice?: string | null;
};

const CONTROL_CLASS = [
    'flex min-h-[var(--control-height)] items-center gap-[var(--space-2)]',
    'rounded-[var(--radius-lg)] border border-border px-[var(--space-3)]',
    'text-body font-medium text-fg',
].join(' ');

/**
 * KÜTÜPHANE ARAÇ ÇUBUĞU (kanonik kaynak, "Kütüphane" bölümü).
 *
 * Elli dosyalık bir kütüphanede sahibin sorusu tek değildir: "hangi
 * fotoğraftı?" göze bakar (ızgara), "hangisi hâlâ taranıyor?" okumaya bakar
 * (liste), "geçen ayki kampanya" klasöre bakar, "yerimi ne dolduruyor?"
 * sıralamaya bakar. Hepsini tek düz listeye sıkıştırmak, her seferinde gözle
 * tarama yaptırır.
 *
 * Süzgeçler KAPALI başlar. Üç açılır kutuyu her açılışta ekrana sermek
 * kütüphaneyi bir forma çevirir ve asıl iş olan dosyaları aşağı iter.
 */
export function MediaLibraryToolbar({
    folders = [],
    activeFolderId = null,
    onFolderChange,
    filtersOpen,
    onToggleFilters,
    activeFilterCount,
    children,
    sort,
    onSortCycle,
    view,
    onViewChange,
    resultLabel,
    selectedCount,
    allSelected,
    onToggleSelectAll,
    onDeleteSelected,
    bulkNotice,
}: MediaLibraryToolbarProps) {
    const showFolders = folders.length > 0 && onFolderChange !== undefined;

    return (
        <div className="flex flex-col gap-[var(--space-3)]">
            {/*
                Klasör hapları YALNIZ gerçek klasör geldiğinde çizilir: klasör
                uçları bu depoya henüz inmedi ve boş bir hap şeridi, sahibi
                olmayan bir yere tıklatır.
            */}
            {showFolders ? (
                <div
                    role="group"
                    aria-label={t('workspace.media.folders.heading')}
                    className="flex flex-wrap gap-[var(--space-2)]"
                >
                    <button
                        type="button"
                        aria-pressed={activeFolderId === null}
                        onClick={() => onFolderChange?.(null)}
                        className={`flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-pill border border-border px-[var(--space-3)] text-body ${
                            activeFolderId === null ? 'bg-surface-active font-bold' : 'font-medium'
                        } text-fg`}
                    >
                        {t('workspace.media.folders.all')}
                    </button>
                    {folders.map((folder) => {
                        const active = folder.id === activeFolderId;

                        return (
                            <button
                                key={String(folder.id)}
                                type="button"
                                aria-pressed={active}
                                onClick={() => onFolderChange?.(folder.id)}
                                className={`flex min-h-[var(--control-height)] items-center gap-[var(--space-2)] rounded-pill border border-border px-[var(--space-3)] text-body ${
                                    active ? 'bg-surface-active font-bold' : 'font-medium'
                                } text-fg`}
                            >
                                {folder.name}
                                {folder.assetCount === undefined ? null : (
                                    <span className="text-meta text-fg-muted tabular-nums">
                                        {String(folder.assetCount)}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            ) : null}

            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                <button
                    type="button"
                    aria-expanded={filtersOpen}
                    onClick={onToggleFilters}
                    className={CONTROL_CLASS}
                >
                    <SlidersHorizontal aria-hidden="true" size={18} />
                    {t('workspace.media.library.filters.toggle')}
                    {activeFilterCount > 0 ? (
                        <span className="rounded-pill bg-action px-[var(--space-2)] text-meta font-bold text-action-fg tabular-nums">
                            {String(activeFilterCount)}
                        </span>
                    ) : null}
                </button>

                {/*
                    Sıralama tek düğmede DÖNER: üç seçenek için bir açılır
                    kutu, tek tıkla dönen bir düğmeden daha çok iş çıkarır.
                    Düğmenin üstünde HANGİ sıralamanın açık olduğu yazar —
                    yoksa sahip listeyi neden o sırada gördüğünü bilemez.
                */}
                <button type="button" onClick={onSortCycle} className={CONTROL_CLASS}>
                    <SortAscending aria-hidden="true" size={18} />
                    {t('workspace.media.library.sort', { label: t(MEDIA_SORT_LABEL_KEY[sort]) })}
                </button>

                <div
                    role="group"
                    aria-label={t('workspace.media.library.view.label')}
                    className="ms-auto flex gap-[var(--space-1)] rounded-[var(--radius-lg)] border border-border p-[var(--space-1)]"
                >
                    <button
                        type="button"
                        aria-label={t('workspace.media.library.view.list')}
                        aria-pressed={view === 'list'}
                        onClick={() => onViewChange('list')}
                        className={`grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] ${
                            view === 'list' ? 'bg-surface-active text-fg' : 'text-fg-secondary'
                        }`}
                    >
                        <ListBullets aria-hidden="true" size={18} />
                    </button>
                    <button
                        type="button"
                        aria-label={t('workspace.media.library.view.grid')}
                        aria-pressed={view === 'grid'}
                        onClick={() => onViewChange('grid')}
                        className={`grid min-h-[var(--control-height)] min-w-[var(--control-height)] place-items-center rounded-[var(--radius-md)] ${
                            view === 'grid' ? 'bg-surface-active text-fg' : 'text-fg-secondary'
                        }`}
                    >
                        <SquaresFour aria-hidden="true" size={18} />
                    </button>
                </div>
            </div>

            {filtersOpen ? children : null}

            <div className="flex flex-wrap items-center gap-[var(--space-2)]">
                {/* Sayaç `text-meta`nın meşru kullanımı; rakam sabit genişlikte. */}
                <span className="text-meta text-fg-muted tabular-nums">{resultLabel}</span>
                <button type="button" onClick={onToggleSelectAll} className={CONTROL_CLASS}>
                    {allSelected
                        ? t('workspace.media.library.select.clear')
                        : t('workspace.media.library.select.all')}
                </button>
                {selectedCount > 0 ? (
                    <>
                        <span className="text-meta text-fg tabular-nums">
                            {t('workspace.media.library.select.count', {
                                count: String(selectedCount),
                            })}
                        </span>
                        <button type="button" onClick={onDeleteSelected} className={CONTROL_CLASS}>
                            {t('workspace.media.library.select.delete')}
                        </button>
                    </>
                ) : null}
            </div>

            {bulkNotice ? (
                <p role="status" className="text-body text-fg-muted">
                    {bulkNotice}
                </p>
            ) : null}
        </div>
    );
}

export default MediaLibraryToolbar;
