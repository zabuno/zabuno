import { useCallback, useId, useMemo, useState } from 'react';
import { Keyboard, SortAscending, Trash } from '@phosphor-icons/react';

import { t } from '../../../../i18n/workspace-desktop';
import { PageState } from '../shared/PageState';
import { MediaAssetStatusBadge } from '../media/MediaAssetStatusBadge';
import { MediaFolderRail } from '../media/MediaFolderRail';
import { displayName, formatBytes, formatDate } from '../media/mediaFormat';
import { selectVisibleAssets } from '../media/mediaLibraryQuery';
import { MEDIA_SORT_LABEL_KEY, MEDIA_SORT_ORDER, type MediaSortKey } from '../media/mediaSort';
import type { MediaLibrarySurfaceContext } from '../media/librarySurface';
import type { MediaAsset } from '../MediaPage';
import {
    DesktopContextMenu,
    DesktopMenuItem,
    isContextMenuKey,
    menuPositionFor,
    type DesktopMenuPosition,
} from './DesktopContextMenu';
import { useDesktopSelection } from './useDesktopSelection';

/**
 * MEDYA KÜTÜPHANESİ — İŞARETLEYİCİ SÜRÜMÜ (`docs/151` §B).
 *
 * ## Bu neden dokunmalı kütüphanenin geniş hâli DEĞİL
 *
 * Aynı dosyalara bakan iki farklı İŞ var (`docs/153` §2):
 *
 * - **Telefondaki sahip** tek bir fotoğrafı arar ve onu bulunca işi biter:
 *   yükler, adını düzeltir, siler. Ekranı bir listedir.
 * - **Masaüstündeki sahip** kütüphaneyi TARAR: elli dosyaya aynı anda
 *   bakar, on tanesini seçer, hepsini birden siler ve seçtiği dosyanın
 *   ayrıntısını YANINDA okur. İşi tarama ve toplu işlemdir.
 *
 * İkincisi birincinin büyütülmüş hâli değildir. Tek sütunlu listeyi 1920
 * piksele yaymak, geniş ekranın tek kazancını — aynı anda çok şey görmek —
 * harcar.
 *
 * ## Burada olan, telefonda OLMAYAN şeyler
 *
 * 1. **Izgara + KALICI ayrıntı bölmesi.** Telefonda ayrıntı bir çekmecedir
 *    ve açıldığında listeyi kapatır; burada yanında durur ve seçim
 *    değiştikçe kendini yeniler.
 * 2. **Klavyeyle gezinen ızgara.** Ok tuşları hücre değiştirir; yukarı/aşağı
 *    SATIR değiştirir ve satır genişliği DOM'dan ölçülür (sabit bir sütun
 *    sayısı, kenar çubuğu açıldığında yanlış olurdu).
 * 3. **Çoklu seçim:** Boşluk, Shift+ok aralık, Ctrl/Cmd+A.
 * 4. **Sağ tık bağlam menüsü ve klavye karşılığı** (Shift+F10 / Menü tuşu).
 * 5. **`hover` ile açığa çıkan satır eylemleri** — dokunmada `hover` yoktur
 *    ve orada eylemler her zaman görünür kalır.
 *
 * ## Ortak kalan
 *
 * Veri (`MediaPage`), silme yolu (`onDelete`), süzme ve sıralama
 * (`mediaLibraryQuery`), biçimleme (`mediaFormat`) ve durum rozeti İKİ
 * yüzeyde de aynıdır. Bu dosyada tek bir `fetch` yoktur ve tek bir ürün
 * kararı verilmez (`docs/153` §4).
 *
 * ## Hareket
 *
 * Bu bileşen hiç hareket üretmez. Stil katmanının tek geçişi (satır
 * eylemlerinin görünürlüğü) `prefers-reduced-motion` altında kapanır.
 */

type Menu = { assetId: number } & DesktopMenuPosition;

export function MediaLibraryDesktop({
    assets,
    onDelete,
    loadState,
    onRetry,
    pendingDeleteIds,
    deleteErrorIds,
    deleteNotice,
    query,
    folders,
    activeFolderId = null,
    onFolderChange,
}: MediaLibrarySurfaceContext) {
    const gridLabelId = useId();
    const [sort, setSort] = useState<MediaSortKey>('newest');
    const [menu, setMenu] = useState<Menu | null>(null);
    /*
        TOPLU SİLMEDE SESSİZ ATLAMA YOKTUR — dokunmalı sürümdeki kararın
        aynısı. Menüde duran bir fotoğraf toplu silmeyle GİTMEZ; sahip üç
        dosya seçip ikisinin silindiğini görürse üçüncüsünün neden durduğunu
        bir yerde okumalı, yoksa onu da silinmiş sanar.
    */
    const [keptNotice, setKeptNotice] = useState<string | null>(null);

    const visible = useMemo(
        () =>
            selectVisibleAssets(assets, {
                query: query ?? '',
                slot: '',
                status: '',
                unusedOnly: false,
                folderId: activeFolderId,
                sort,
            }),
        [assets, query, activeFolderId, sort],
    );

    const ids = visible.map((asset) => asset.id);
    const selection = useDesktopSelection(ids);
    const activeAsset = visible.find((asset) => asset.id === selection.activeId) ?? null;

    /**
     * SATIR YÜKSEKLİĞİ DOM'DAN ÖLÇÜLÜR, sabit bir sütun sayısından değil.
     *
     * Izgara `auto-fill` ile dolduğu için sütun sayısı kapsayıcının
     * genişliğine bağlıdır: kenar çubuğu açılıp kapandığında değişir. Kodun
     * içine yazılmış bir "4 sütun" bir gün yanlış olur ve yukarı tuşu
     * kullanıcıyı beklemediği yere götürür.
     *
     * Düzen motoru olmayan bir ortamda (jsdom) her kutunun üst kenarı
     * sıfırdır; o zaman satır hareketi TEK ADIMA düşer. Bu bir kusur değil
     * bilinçli bir geri çekilme: ölçülemeyen bir geometriye göre atlamak,
     * ölçtüğünü sanmaktır.
     */
    const rowStep = useCallback(
        (fromId: number | null): number => {
            if (fromId === null) {
                return 1;
            }

            const origin = selection.nodeFor(fromId);

            if (origin === null) {
                return 1;
            }

            const originTop = origin.getBoundingClientRect().top;
            const index = ids.indexOf(fromId);

            for (let cursor = index + 1; cursor < ids.length; cursor += 1) {
                const candidateId = ids[cursor];

                if (candidateId === undefined) {
                    break;
                }

                const node = selection.nodeFor(candidateId);

                if (node !== null && node.getBoundingClientRect().top > originTop) {
                    return cursor - index;
                }
            }

            return 1;
        },
        [ids, selection],
    );

    const deleteTargets = useCallback(
        (targets: number[]) => {
            const kept: number[] = [];

            for (const id of targets) {
                const asset = assets.find((candidate) => candidate.id === id);

                if (asset === undefined) {
                    continue;
                }

                if ((asset.usageCount ?? 0) > 0) {
                    kept.push(id);

                    continue;
                }

                onDelete(id);
            }

            setKeptNotice(
                kept.length > 0
                    ? t('workspace.media.library.select.kept', { count: String(kept.length) })
                    : null,
            );
        },
        [assets, onDelete],
    );

    if (loadState === 'loading') {
        return (
            <PageState
                kind="loading"
                screen="media_library"
                title={t('workspace.media.library.loading')}
            />
        );
    }

    if (loadState === 'error') {
        return (
            <PageState
                kind="error"
                screen="media_library"
                title={t('workspace.media.library.error')}
                /*
                    ÇIKIŞ YOLU OLMADAN HATA YAZILMAZ (`docs/59`). Yeniden
                    deneme yolu verilmediyse ekran neden eylem sunmadığını
                    söyler; boş bir hata kutusu kullanıcıyı bekletmekten
                    başka iş görmez.
                */
                {...(onRetry
                    ? {
                          action: (
                              <button type="button" className="underline" onClick={onRetry}>
                                  {t('workspace.error.retry')}
                              </button>
                          ),
                      }
                    : { whyNoAction: t('workspace.media.library.error') })}
            />
        );
    }

    if (assets.length === 0) {
        return (
            <PageState
                kind="empty"
                screen="media_library"
                title={t('workspace.media.library.unavailable')}
                whyNoAction={t('workspace.media.library.unavailable')}
            />
        );
    }

    const menuTargets = menu === null ? [] : selection.targetsFor(menu.assetId);

    return (
        <section
            aria-label={t('workspace.media.library.region')}
            className="dk-frame flex flex-col gap-[var(--space-3)]"
        >
            <div className="dk-toolbar">
                <p className="text-meta text-fg-secondary" id={gridLabelId}>
                    {t('workspace.media.library.desktop.count', {
                        shown: String(visible.length),
                        total: String(assets.length),
                    })}
                </p>

                <div className="flex items-center gap-[var(--space-3)]">
                    {/*
                        SIRALAMA TEK DÜĞMEDE DÖNER — dokunmalı araç
                        çubuğundaki kararın aynısı. Üç seçenek için açılır
                        liste açmak, üç tıklamayı dörde çıkarırdı.
                    */}
                    <button
                        type="button"
                        className="dk-btn"
                        onClick={() =>
                            setSort(
                                (current) =>
                                    MEDIA_SORT_ORDER[
                                        (MEDIA_SORT_ORDER.indexOf(current) + 1) %
                                            MEDIA_SORT_ORDER.length
                                    ] ?? 'newest',
                            )
                        }
                    >
                        <SortAscending size={16} weight="regular" aria-hidden="true" />
                        {t('workspace.media.library.sort', {
                            label: t(MEDIA_SORT_LABEL_KEY[sort]),
                        })}
                    </button>

                    {/*
                        KISAYOLLAR YAZILI DURUR. Telefonda bu satır hiç
                        çizilmez, çünkü orada bu tuşlar yok.
                    */}
                    <p className="dk-hint text-meta">
                        <Keyboard size={16} weight="regular" aria-hidden="true" />
                        {t('workspace.media.library.desktop.shortcuts')}
                    </p>
                </div>
            </div>

            {folders !== undefined && folders.length > 0 && onFolderChange !== undefined ? (
                <MediaFolderRail
                    folders={folders}
                    activeFolderId={activeFolderId}
                    onSelect={onFolderChange}
                />
            ) : null}

            {selection.selectedIds.length > 0 ? (
                <div
                    role="group"
                    aria-label={t('workspace.media.library.desktop.bulkRegion')}
                    className="dk-bulkbar"
                >
                    <p className="text-body font-medium text-fg">
                        {t('workspace.media.library.select.count', {
                            count: String(selection.selectedIds.length),
                        })}
                    </p>
                    <button
                        type="button"
                        data-tone="danger"
                        className="dk-btn"
                        onClick={() => deleteTargets(selection.selectedIds)}
                    >
                        <Trash size={16} weight="bold" aria-hidden="true" />
                        {t('workspace.media.library.select.delete')}
                    </button>
                    <button type="button" className="dk-btn" onClick={selection.clear}>
                        {t('workspace.media.library.select.clear')}
                    </button>
                </div>
            ) : null}

            {deleteNotice !== null && deleteNotice !== undefined ? (
                <p role="status" className="text-body text-fg-secondary">
                    {deleteNotice}
                </p>
            ) : null}

            {keptNotice !== null ? (
                <p role="status" className="text-body text-fg-secondary">
                    {keptNotice}
                </p>
            ) : null}

            {visible.length === 0 ? (
                <PageState
                    kind="empty"
                    screen="media_library"
                    title={t('workspace.media.library.filters.noMatch')}
                    whyNoAction={t('workspace.media.library.filters.noMatch')}
                />
            ) : (
                <div className="dk-split">
                    <ul
                        role="listbox"
                        aria-multiselectable="true"
                        aria-labelledby={gridLabelId}
                        className="dk-grid"
                    >
                        {visible.map((asset) => (
                            <MediaTileDesktop
                                key={asset.id}
                                asset={asset}
                                active={asset.id === selection.activeId}
                                selected={selection.isSelected(asset.id)}
                                busy={pendingDeleteIds?.has(asset.id) ?? false}
                                failed={deleteErrorIds?.has(asset.id) ?? false}
                                registerRef={selection.registerRef(asset.id)}
                                onActivate={(additive, range) =>
                                    selection.activate(asset.id, additive, range)
                                }
                                onDelete={() => deleteTargets(selection.targetsFor(asset.id))}
                                onContextMenu={(position) => {
                                    selection.activate(asset.id, false, false);
                                    setMenu({ assetId: asset.id, ...position });
                                }}
                                onKeyDown={(event) => {
                                    if (event.key === 'ArrowRight') {
                                        event.preventDefault();
                                        selection.move(1, event.shiftKey);

                                        return;
                                    }

                                    if (event.key === 'ArrowLeft') {
                                        event.preventDefault();
                                        selection.move(-1, event.shiftKey);

                                        return;
                                    }

                                    if (event.key === 'ArrowDown') {
                                        event.preventDefault();
                                        selection.move(rowStep(asset.id), event.shiftKey);

                                        return;
                                    }

                                    if (event.key === 'ArrowUp') {
                                        event.preventDefault();
                                        selection.move(-rowStep(asset.id), event.shiftKey);

                                        return;
                                    }

                                    if (event.key === 'Home') {
                                        event.preventDefault();
                                        selection.move(-visible.length, event.shiftKey);

                                        return;
                                    }

                                    if (event.key === 'End') {
                                        event.preventDefault();
                                        selection.move(visible.length, event.shiftKey);

                                        return;
                                    }

                                    if (event.key === ' ') {
                                        event.preventDefault();
                                        selection.toggle(asset.id);

                                        return;
                                    }

                                    if (
                                        (event.ctrlKey || event.metaKey) &&
                                        event.key.toLowerCase() === 'a'
                                    ) {
                                        event.preventDefault();
                                        selection.selectAll();

                                        return;
                                    }

                                    if (event.key === 'Delete' || event.key === 'Backspace') {
                                        event.preventDefault();
                                        deleteTargets(selection.targetsFor(asset.id));

                                        return;
                                    }

                                    if (isContextMenuKey(event)) {
                                        event.preventDefault();
                                        setMenu({
                                            assetId: asset.id,
                                            ...menuPositionFor(selection.nodeFor(asset.id)),
                                        });

                                        return;
                                    }

                                    if (event.key === 'Escape') {
                                        selection.clear();
                                    }
                                }}
                            />
                        ))}
                    </ul>

                    <aside
                        aria-label={t('workspace.media.library.desktop.detail.region')}
                        className="dk-pane"
                    >
                        {activeAsset === null ? (
                            <p className="text-meta text-fg-muted">
                                {t('workspace.media.library.desktop.detail.empty')}
                            </p>
                        ) : (
                            <MediaAssetDetailPane asset={activeAsset} />
                        )}
                    </aside>
                </div>
            )}

            {menu !== null ? (
                <DesktopContextMenu
                    label={t('workspace.media.library.desktop.menu')}
                    position={{ x: menu.x, y: menu.y }}
                    onClose={() => {
                        const target = menu.assetId;

                        setMenu(null);
                        selection.focusRow(target);
                    }}
                >
                    <DesktopMenuItem
                        autoFocus
                        tone="danger"
                        onSelect={() => {
                            setMenu(null);
                            deleteTargets(menuTargets);
                        }}
                    >
                        <Trash size={16} weight="bold" aria-hidden="true" />
                        {menuTargets.length > 1
                            ? t('workspace.media.library.select.delete')
                            : t('workspace.media.library.asset.delete')}
                    </DesktopMenuItem>
                </DesktopContextMenu>
            ) : null}
        </section>
    );
}

/** Kalıcı bölmenin içeriği — seçili dosyanın tamamı, hep aynı yerde. */
function MediaAssetDetailPane({ asset }: { asset: MediaAsset }) {
    const name = displayName(asset);

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            <h3 className="text-section font-bold text-fg">{name}</h3>

            <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason ?? null} />

            {asset.previewUrl ? (
                <span className="dk-thumb">
                    <img src={asset.previewUrl} alt="" />
                </span>
            ) : (
                /*
                    Önizlemesi olmayan varlığa UYDURMA GÖRSEL çizilmez
                    (MEDIA-INTAKE-NO-PUBLIC-URL-01): karantinadaki dosyanın
                    herkese açık adresi YOKTUR. Burada duran şey bir fotoğraf
                    değil bir cümledir.
                */
                <p className="text-meta text-fg-muted">
                    {t('workspace.media.library.detail.noPreview')}
                </p>
            )}

            <dl className="flex flex-col gap-1">
                {asset.originalName ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt className="text-meta text-fg-secondary">
                            {t('workspace.media.library.detail.file')}
                        </dt>
                        <dd className="truncate text-body text-fg">{asset.originalName}</dd>
                    </div>
                ) : null}

                {formatBytes(asset.sizeBytes) !== '' ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt className="text-meta text-fg-secondary">
                            {t('workspace.media.library.detail.size')}
                        </dt>
                        <dd className="text-body text-fg">{formatBytes(asset.sizeBytes)}</dd>
                    </div>
                ) : null}

                {formatDate(asset.createdAt) !== '' ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt className="text-meta text-fg-secondary">
                            {t('workspace.media.library.detail.uploaded')}
                        </dt>
                        <dd className="text-body text-fg">{formatDate(asset.createdAt)}</dd>
                    </div>
                ) : null}

                <div className="flex justify-between gap-[var(--space-2)]">
                    <dt className="text-meta text-fg-secondary">
                        {t('workspace.media.library.detail.slot')}
                    </dt>
                    <dd className="text-body text-fg">{asset.slot}</dd>
                </div>
            </dl>

            {asset.usageCount !== undefined ? (
                /*
                    KULLANIM SAYISI BÖLMEDE DURUR ve bir uyarı değildir:
                    silmenin neden reddedildiğini sahip BASMADAN ÖNCE okur.
                */
                <p className="text-body text-fg-secondary">
                    {t('workspace.media.library.desktop.detail.usage', {
                        count: String(asset.usageCount),
                    })}
                </p>
            ) : null}
        </div>
    );
}

function MediaTileDesktop({
    asset,
    active,
    selected,
    busy,
    failed,
    registerRef,
    onActivate,
    onDelete,
    onContextMenu,
    onKeyDown,
}: {
    asset: MediaAsset;
    active: boolean;
    selected: boolean;
    busy: boolean;
    failed: boolean;
    registerRef: (node: HTMLElement | null) => void;
    onActivate: (additive: boolean, range: boolean) => void;
    onDelete: () => void;
    onContextMenu: (position: DesktopMenuPosition) => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLLIElement>) => void;
}) {
    const name = displayName(asset);

    return (
        <li
            ref={registerRef}
            role="option"
            aria-selected={selected}
            /*
                ROVING TABINDEX: ızgaraya Tab ile BİR KEZ girilir, içinde
                oklarla gezinilir. Her kutu odaklanabilir olsaydı, elli
                dosyalık bir kütüphane Tab tuşuna elli kez basmak demekti.
            */
            tabIndex={active ? 0 : -1}
            data-active={active ? 'true' : 'false'}
            className="dk-tile"
            onKeyDown={onKeyDown}
            onClick={(event) => onActivate(event.ctrlKey || event.metaKey, event.shiftKey)}
            onContextMenu={(event) => {
                event.preventDefault();
                onContextMenu({ x: event.clientX, y: event.clientY });
            }}
        >
            <span className="dk-thumb">
                {asset.previewUrl ? (
                    <img src={asset.previewUrl} alt="" />
                ) : (
                    <span className="text-meta text-fg-muted">
                        {t('workspace.media.library.detail.noPreview')}
                    </span>
                )}
            </span>

            <span className="truncate text-body font-medium text-fg">{name}</span>

            <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason ?? null} />

            {failed ? (
                <span role="alert" className="text-meta text-fg-danger">
                    {t('workspace.media.library.asset.delete.failed')}
                </span>
            ) : null}

            {/*
                KUTU EYLEMLERİ `hover` ya da ODAKLA görünür — ve aynı eylem
                bağlam menüsünde, Delete tuşunda ve toplu şeritte de var.
                `hover` burada bir KISA YOLdur, tek yol değil (`docs/151`
                stil katmanı §5).
            */}
            <span className="dk-rowactions">
                <button
                    type="button"
                    tabIndex={-1}
                    disabled={busy}
                    onClick={(event) => {
                        event.stopPropagation();
                        onDelete();
                    }}
                    aria-label={t('workspace.media.library.asset.delete.named', { name })}
                    className="dk-btn"
                    data-tone="danger"
                >
                    <Trash size={16} weight="bold" aria-hidden="true" />
                </button>
            </span>
        </li>
    );
}

export default MediaLibraryDesktop;
