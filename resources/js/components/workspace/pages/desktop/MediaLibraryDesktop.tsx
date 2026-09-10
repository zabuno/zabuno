import { useMemo, useRef, useState } from 'react';
import { Keyboard, LockSimple, Trash } from '@phosphor-icons/react';

import { Button } from '../../../catalog/forms/micro/Button';
import { Checkbox } from '../../../catalog/forms/micro/Checkbox';
import { Select } from '../../../catalog/forms/micro/Select';
import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Tabs } from '../../../catalog/navigation/compound/Tabs';
import { t } from '../../../../i18n/workspace';
import { MediaAssetDetailDrawer } from '../media/MediaAssetDetailDrawer';
import { MediaAssetStatusBadge } from '../media/MediaAssetStatusBadge';
import { MediaDeleteImpactDialog } from '../media/MediaDeleteImpactDialog';
import { MediaLifecycleList } from '../media/MediaLifecycleList';
import { MediaLibrarySlotList } from '../media/MediaLibrarySlotList';
import { MediaTrashList } from '../media/MediaTrashList';
import { MediaLibraryToolbar, type MediaLibraryView } from '../media/MediaLibraryToolbar';
import { MEDIA_SORT_ORDER, type MediaSortKey } from '../media/mediaSort';
import {
    activeFilterCount as countActiveFilters,
    anyFilterActive,
    availableSlots,
    availableStatuses,
    selectVisibleAssets,
} from '../media/mediaLibraryQuery';
import { displayName, formatBytes, formatDate } from '../media/mediaFormat';
import type { MediaLibrarySurfaceContext } from '../media/librarySurface';
import type { MediaAsset } from '../MediaPage';
import { DesktopContextMenu, type DesktopContextMenuItem } from './DesktopContextMenu';
import { useDesktopSelection } from './useDesktopSelection';

/**
 * KÜTÜPHANENİN İŞARETLEYİCİ/KLAVYE YÜZEYİ — `docs/153` §6.
 *
 * `OrderQueueDesktop` ile aynı yerde durur ve aynı sebeple vardır: aynı
 * veriye bakan İKİ AYRI İŞ var. Telefonda kütüphaneye bakan kişi tek bir
 * fotoğrafı arar ve parmağıyla dokunur; masasında oturan kişi kırk dosyayı
 * arka arkaya gözden geçirir, klavyesi vardır ve aynı işi tekrar tekrar
 * yapar. İkisine aynı listeyi vermek, ikincisini yavaşlatmaktan başka iş
 * görmez.
 *
 * ## Ne PAYLAŞILIR, ne AYRIŞIR
 *
 * "Hangi dosyalar görünsün ve hangi sırayla?" sorusu PAYLAŞILIR
 * (`mediaLibraryQuery.ts`): iki yüzey de aynı cevabı verir, yoksa sahip
 * telefonda "3 dosya", masaüstünde "4 dosya" görürdü. Silmenin GÜVENLİK
 * kuralı da paylaşılır ve aşağıda birebir korunur: kullanımda olan bir
 * dosya doğrudan gitmez, önce nerede kullanıldığı gösterilir.
 *
 * AYRIŞAN tek şey GİRİŞ KİPİdir (TOUCH-FIRST-INTERFACE §2): oklarla
 * gezinme, boşlukla işaretleme, Shift ile aralık, sağ tık ve onun klavye
 * karşılığı, ve KALICI ikinci sütun. Bunların hiçbirinin dokunmada
 * karşılığı yok, o yüzden hiçbiri paylaşılan dosyaya yazılmadı ve mobil
 * pakete inmiyor (`scripts/adaptive-bundle-gate`).
 *
 * ## Izgaranın sütun sayısı ÖLÇÜLÜR, sabitlenmez
 *
 * Klavyenin modeli ile ekranın gerçeği AYNI olmak zorundadır: yukarı ok
 * "bir satır yukarı" demektir. Bu, sütun sayısı SABİTLENEREK sağlanmaya
 * çalışıldı ve iki yerden birden kırıldı. LİSTE görünümünde ızgara tek
 * sütundur; sabit üç orada aşağı okun İKİ satır atlamasına yol açtı ve
 * Shift ile atlanan satırlar da seçime girdi — sahip bir dosya seçmek
 * isterken üç dosya seçili buldu. Dar masaüstünde ise aynı sabit sayı,
 * sığmayan bir alana üç sütun çizdirip kutuları kırk iki piksele indirdi.
 *
 * Doğrusu sabitlemek değil ÖLÇMEK. Tuşa basıldığı anda ızgaranın gerçekten
 * kaç sütun çizdiği okunur: `grid-template-columns`'un HESAPLANMIŞ değeri
 * kaç iz taşıyorsa sütun sayısı odur. Ölçüm tuşun kendi anında yapıldığı
 * için pencere yeniden boyutlandığında tazelenmesi gereken saklı bir sayı
 * yoktur — eski bir sayı hiç saklanmaz. Liste görünümünde bir satır bir
 * adımdır ve bu ölçüme bile gerek duymaz.
 */

/**
 * Düzen motoru olmayan bir ortamda (jsdom) hesaplanmış değer BOŞ döner:
 * orada hiçbir kutunun boyu yoktur. O durumda ızgara belgelenmiş
 * varsayılanına düşer; gerçek tarayıcıda bu değere hiç inilmez.
 */
const GRID_COLUMNS_FALLBACK = 3;

/** Izgaranın O ANDA çizdiği sütun sayısı — saklanan değil, okunan. */
function renderedColumnCount(list: HTMLElement | null): number {
    if (list === null) {
        return GRID_COLUMNS_FALLBACK;
    }

    const tracks = window
        .getComputedStyle(list)
        .gridTemplateColumns.split(' ')
        .filter((track) => track !== '' && track !== 'none');

    return tracks.length > 0 ? tracks.length : GRID_COLUMNS_FALLBACK;
}

type MediaLibraryDesktopProps = MediaLibrarySurfaceContext;

export function MediaLibraryDesktop({
    assets,
    onDelete,
    loadState,
    onRetry,
    pendingDeleteIds,
    deleteErrorIds,
    deleteNotice,
    actions,
    trashRetentionDays = 30,
    query,
    folders,
    activeFolderId = null,
    onFolderChange,
}: MediaLibraryDesktopProps) {
    const [ownQuery, setOwnQuery] = useState('');
    const [slot, setSlot] = useState('');
    const [status, setStatus] = useState('');
    const [unusedOnly, setUnusedOnly] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [sort, setSort] = useState<MediaSortKey>('newest');
    const [view, setView] = useState<MediaLibraryView>('grid');
    const [tab, setTab] = useState<'library' | 'trash'>('library');
    const [detailId, setDetailId] = useState<number | null>(null);
    const [impactId, setImpactId] = useState<number | null>(null);
    const [bulkNotice, setBulkNotice] = useState<string | null>(null);
    const [menu, setMenu] = useState<{ assetId: number; x: number; y: number } | null>(null);

    /** Izgaranın kendisi — sütun sayısı ondan OKUNUR, bir durumda tutulmaz. */
    const listRef = useRef<HTMLUListElement | null>(null);

    const externalQuery = query !== undefined;
    const effectiveQuery = query ?? ownQuery;

    const slots = useMemo(() => availableSlots(assets), [assets]);
    const statuses = useMemo(() => availableStatuses(assets), [assets]);

    const libraryQuery = useMemo(
        () => ({
            text: effectiveQuery,
            slot,
            status,
            unusedOnly,
            folderId: activeFolderId,
            sort,
        }),
        [effectiveQuery, slot, status, unusedOnly, activeFolderId, sort],
    );

    const visible = useMemo(
        () => selectVisibleAssets(assets, libraryQuery),
        [assets, libraryQuery],
    );
    const visibleIds = useMemo(() => visible.map((asset) => asset.id), [visible]);

    const selection = useDesktopSelection(visibleIds);
    const { activeId, selectedIds } = selection;

    const detailAsset = assets.find((asset) => asset.id === detailId) ?? null;
    const impactAsset = assets.find((asset) => asset.id === impactId) ?? null;
    const activeAsset = visible.find((asset) => asset.id === activeId) ?? null;
    const activeFilterCount = countActiveFilters(libraryQuery);
    const filtersActive = anyFilterActive(libraryQuery);
    const toolbarVisible = loadState === 'idle' && assets.length > 1;
    const allSelected = visible.length > 0 && selectedIds.length === visible.length;

    /*
        SİLMENİN GÜVENLİK KURALI, dokunmalı kütüphaneyle BİREBİR aynı
        (`docs/49` Faz 5 madde 2).

        Kullanılmayan dosya doğrudan çöpe gider — çöp geri alınabilir ve bir
        onay penceresi daha kullanıcıyı yormaktan başka iş görmez. KULLANILAN
        dosyada ise önce etki önizlemesi açılır: menüde duran bir fotoğrafın
        tek tuşla gitmesi, misafirin gördüğü menüyü sahibin haberi olmadan
        değiştirmek demekti.

        Bu kural masaüstünde KISALTILMAZ. Klavye kısayolu işi hızlandırır,
        kapıyı kaldırmaz.
    */
    function requestDelete(id: number) {
        const asset = assets.find((candidate) => candidate.id === id);

        if (actions && asset && (asset.usageCount ?? 0) > 0) {
            setImpactId(id);

            return;
        }

        setDetailId(null);
        onDelete(id);
    }

    /*
        TOPLU SİLMEDE SESSİZ ATLAMA YOKTUR.

        Sahip beş dosya seçip dördünün silindiğini görürse, beşincinin neden
        durduğunu bir yerde OKUMALI — yoksa onu da silinmiş sanar ve
        menüsünde duran bir fotoğrafı yok bilir. Kullanımda olanlar seçili
        KALIR: cümlenin işaret ettiği dosyalar ekranda hâlâ işaretlidir.
    */
    function deleteTargets(ids: number[]) {
        if (ids.length === 1) {
            const only = ids[0];

            if (only !== undefined) {
                requestDelete(only);
            }

            return;
        }

        const kept: number[] = [];

        ids.forEach((id) => {
            const asset = assets.find((candidate) => candidate.id === id);

            if (asset === undefined) {
                return;
            }

            if ((asset.usageCount ?? 0) > 0) {
                kept.push(asset.id);

                return;
            }

            onDelete(asset.id);
        });

        selection.clearSelection();
        kept.forEach((id) => selection.toggleSelected(id));
        setBulkNotice(
            kept.length > 0
                ? t('workspace.media.library.select.kept', { count: String(kept.length) })
                : null,
        );
    }

    function openMenuAt(id: number, x: number, y: number) {
        selection.setActive(id);
        setMenu({ assetId: id, x, y });
    }

    function closeMenu(refocus: boolean) {
        const target = menu?.assetId ?? null;

        setMenu(null);

        if (refocus && target !== null) {
            selection.focusRow(target);
        }
    }

    const menuTargets = menu === null ? [] : selection.targetsFor(menu.assetId);
    const menuItems: DesktopContextMenuItem[] =
        menu === null
            ? []
            : [
                  ...(actions
                      ? [
                            {
                                key: 'details',
                                label: t('workspace.media.library.desktop.details'),
                                onSelect: () => {
                                    setMenu(null);
                                    setDetailId(menu.assetId);
                                },
                            },
                        ]
                      : []),
                  {
                      key: 'delete',
                      label:
                          menuTargets.length > 1
                              ? t('workspace.media.library.select.delete')
                              : t('workspace.media.library.asset.delete'),
                      icon: <Trash aria-hidden="true" size={16} />,
                      danger: true,
                      onSelect: () => {
                          setMenu(null);
                          deleteTargets(menuTargets);
                      },
                  },
              ];

    const filterPanel = (
        <div
            role="group"
            aria-label={t('workspace.media.library.filters.label')}
            className="flex flex-wrap items-end gap-2"
        >
            <label className="flex flex-col gap-1 text-body text-fg-secondary">
                {t('workspace.media.library.filters.slot')}
                <Select value={slot} onChange={(event) => setSlot(event.target.value)}>
                    <option value="">{t('workspace.media.library.filters.any')}</option>
                    {slots.map((value) => (
                        <option key={value} value={value}>
                            {value}
                        </option>
                    ))}
                </Select>
            </label>
            <label className="flex flex-col gap-1 text-body text-fg-secondary">
                {t('workspace.media.library.filters.status')}
                <Select value={status} onChange={(event) => setStatus(event.target.value)}>
                    <option value="">{t('workspace.media.library.filters.any')}</option>
                    {statuses.map((value) => (
                        <option key={value} value={value}>
                            {t(`workspace.media.library.asset.status.${value}`)}
                        </option>
                    ))}
                </Select>
            </label>
            <label className="flex items-center gap-2 text-body text-fg">
                <Checkbox
                    checked={unusedOnly}
                    onChange={(event) => setUnusedOnly(event.target.checked)}
                />
                {t('workspace.media.library.filters.unusedOnly')}
            </label>
        </div>
    );

    /** Bir SATIRIN kaç kutu ettiği: ızgarada ölçülen sütun kadar, listede bir. */
    function rowStep(): number {
        return view === 'grid' ? renderedColumnCount(listRef.current) : 1;
    }

    function onCellKeyDown(asset: MediaAsset, event: React.KeyboardEvent<HTMLLIElement>) {
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            selection.moveActive(1, event.shiftKey);

            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            selection.moveActive(-1, event.shiftKey);

            return;
        }

        /*
            Bir SATIR aşağı: ızgarada bu, O ANDA çizilen sütun sayısı kadar
            ileridir; listede tam olarak bir satırdır. Sayı tuşun kendi anında
            ölçüldüğü için pencere büyütülüp küçültülse de aynı tuş ekranda
            görünen satırla aynı şeyi yapar.
        */
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            selection.moveActive(rowStep(), event.shiftKey);

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            selection.moveActive(-rowStep(), event.shiftKey);

            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            selection.moveActive(-visible.length, event.shiftKey);

            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            selection.moveActive(visible.length, event.shiftKey);

            return;
        }

        if (event.key === ' ') {
            event.preventDefault();
            setBulkNotice(null);
            selection.toggleSelected(asset.id);

            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            setBulkNotice(null);
            selection.selectAll();

            return;
        }

        /*
            Enter DETAYI AÇAR, silmez. Masaüstünde en sık basılan tuşun geri
            alınamaz bir işi tetiklemesi, hızın bedelini yanlış yere yazardı;
            silme ayrı bir tuştadır ve kendi kapısı vardır.
        */
        if (event.key === 'Enter' && actions) {
            event.preventDefault();
            setDetailId(asset.id);

            return;
        }

        if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            deleteTargets(selection.targetsFor(asset.id));

            return;
        }

        /*
            SAĞ TIKIN KLAVYE KARŞILIĞI — WCAG 2.2 AA (`docs/153` §7). Menü
            yalnız fareyle açılabilseydi, klavyeyle çalışan kullanıcı
            oradaki eylemlere hiç ulaşamazdı.
        */
        if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
            event.preventDefault();

            const rect = selection.rowRect(asset.id);

            openMenuAt(asset.id, rect?.left ?? 0, rect?.bottom ?? 0);

            return;
        }

        if (event.key === 'Escape') {
            selection.clearSelection();
        }
    }

    const libraryPanel = (
        <div className="flex flex-col gap-3">
            <h4 className="text-body font-bold text-fg">
                {t('workspace.media.library.assets.heading')}
            </h4>

            {toolbarVisible && !externalQuery ? (
                <label className="flex min-w-0 flex-col gap-1 text-body text-fg-secondary">
                    {t('workspace.media.library.filters.search')}
                    <TextInput
                        type="search"
                        value={ownQuery}
                        onChange={(event) => setOwnQuery(event.target.value)}
                        placeholder={t('workspace.media.library.filters.searchPlaceholder')}
                    />
                </label>
            ) : null}

            {toolbarVisible ? (
                <MediaLibraryToolbar
                    folders={folders}
                    activeFolderId={activeFolderId}
                    onFolderChange={onFolderChange}
                    filtersOpen={filtersOpen}
                    onToggleFilters={() => setFiltersOpen((open) => !open)}
                    activeFilterCount={activeFilterCount}
                    sort={sort}
                    onSortCycle={() =>
                        setSort(
                            (current) =>
                                MEDIA_SORT_ORDER[
                                    (MEDIA_SORT_ORDER.indexOf(current) + 1) %
                                        MEDIA_SORT_ORDER.length
                                ],
                        )
                    }
                    view={view}
                    onViewChange={setView}
                    resultLabel={
                        filtersActive
                            ? t('workspace.media.library.filters.count', {
                                  shown: String(visible.length),
                                  total: String(assets.length),
                              })
                            : t('workspace.media.library.result.count', {
                                  count: String(visible.length),
                              })
                    }
                    selectedCount={selectedIds.length}
                    allSelected={allSelected}
                    onToggleSelectAll={() => {
                        setBulkNotice(null);

                        if (allSelected) {
                            selection.clearSelection();

                            return;
                        }

                        selection.selectAll();
                    }}
                    onDeleteSelected={() => deleteTargets(selectedIds)}
                    bulkNotice={bulkNotice}
                >
                    {filterPanel}
                </MediaLibraryToolbar>
            ) : null}

            {loadState === 'loading' ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.loading')}
                </p>
            ) : loadState === 'error' ? (
                <div className="flex flex-col items-start gap-2">
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.media.library.error')}
                    </p>
                    <Button onClick={() => onRetry?.()}>{t('workspace.error.retry')}</Button>
                </div>
            ) : assets.length === 0 ? (
                <div className="flex flex-col gap-1 rounded-[var(--radius-lg)] border border-dashed border-border p-[var(--space-4)]">
                    <p role="status" className="text-body text-fg-muted">
                        {t('workspace.media.library.unavailable')}
                    </p>
                    <p className="text-body text-fg-muted">
                        {t('workspace.media.library.empty.hint')}
                    </p>
                </div>
            ) : visible.length === 0 ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.filters.noMatch')}
                </p>
            ) : (
                <>
                    <div className="flex flex-wrap items-center justify-between gap-[var(--space-3)]">
                        {/*
                            KISAYOLLAR YAZILI DURUR. Klavyeyle çalışan bir
                            ızgara, varlığı SÖYLENMEDİKÇE yoktur: kimse
                            boşluğa basmayı denemez. Bu satır telefonda hiç
                            çizilmez, çünkü orada bu tuşlar yok.
                        */}
                        <p className="flex items-center gap-[var(--space-2)] text-meta text-fg-muted">
                            <Keyboard aria-hidden="true" size={16} weight="regular" />
                            {t('workspace.media.library.desktop.shortcuts')}
                        </p>
                    </div>

                    {/*
                        SEÇİM ŞERİDİ AYRICA ÇİZİLMEZ.

                        Sayaç, "hepsini seç" ve "seçilenleri sil" araç
                        çubuğunda ZATEN duruyor ve o çubuk cihaz tanımaz.
                        Izgaranın başına ikinci bir şerit koymak, aynı
                        ekranda aynı adı taşıyan iki düğme demekti — sahip
                        hangisinin geçerli olduğunu denemeden bilemezdi.
                        Klavyeyle seçilen dosyalar da o çubuğa yansır;
                        seçimin ekranda bir karşılığı olması yeter, iki
                        karşılığı olması gürültüdür.
                    */}

                    {/*
                        İKİ BÖLME: ızgara ve KALICI ayrıntı.

                        Telefonda ikinci sütun YOKTUR ve aynı bilgi karta ya
                        da çekmeceye sığar. Burada ayrılmasının sebebi süs
                        değil: kırk dosyayı tararken kutuların KISA kalması
                        gerekir, ayrıntı ise tek bir yerde ve hep aynı yerde
                        okunur — üzerinde durulan dosya değişince yan sütun
                        da değişir, tıklamak gerekmez.
                    */}
                    {/*
                        İKİNCİ SÜTUN KATI DEĞİL, YER VARSA VARDIR.

                        Bölme daha önce `20rem`'e ÇİVİLENMİŞTİ ve ızgara
                        tarafında hiçbir taban yoktu. Sonuç ölçüldü: 800
                        piksellik bir pencerede ızgaraya 149 piksel kalıyor,
                        üç kutu kırk ikişer piksele iniyor, dosya adı tek
                        harfe düşüyor ve rozet komşu kutunun üstüne taşıyordu.
                        Taşma bir kaydırma çubuğu bile üretmiyordu — kutu
                        taşmıyor, ÇÖKÜYORDU; o yüzden yalnız taşmaya bakan
                        bir kontrol bozuk ekranda yeşil diyordu.

                        Kabuk bu sorunu zaten çözüyor ve aynı çözüm burada da
                        kullanılıyor (`app.css`, bağlam paneli): panel
                        gizlenmez, YER KALMAYINCA ana içeriğin ALTINA geçer.
                        `flex-basis` "bu genişlik yoksa sar" demenin kendisidir;
                        cihaz sorusu sorulmaz, kabın gerçek genişliği sorulur.
                    */}
                    <div className="flex flex-wrap items-start gap-[var(--space-4)]">
                        <ul
                            ref={listRef}
                            role="listbox"
                            aria-multiselectable="true"
                            aria-label={t('workspace.media.library.desktop.grid')}
                            className={[
                                'min-w-0 grow basis-[25rem]',
                                view === 'grid'
                                    ? /*
                                          KUTUNUN BİR TABANI VAR. `auto-fit`
                                          sütun sayısını kaba göre seçer,
                                          `minmax` ise kutunun altına inemeyeceği
                                          genişliği çizer: bu taban olmadan üç
                                          sütun her genişlikte üç sütun kalıyor
                                          ve içerik kutunun dışına taşıyordu.
                                          `min(100%,…)` ise kap tabandan da darsa
                                          tek sütuna inmesini sağlar — taban asla
                                          yatay kaydırma üretmez.
                                      */
                                      'grid gap-[var(--space-3)] [grid-template-columns:repeat(auto-fit,minmax(min(100%,12rem),1fr))]'
                                    : 'flex flex-col rounded-[var(--radius-md)] border border-border',
                            ].join(' ')}
                        >
                            {visible.map((asset) => (
                                <MediaCellDesktop
                                    key={asset.id}
                                    asset={asset}
                                    view={view}
                                    active={asset.id === activeId}
                                    selected={selection.isSelected(asset.id)}
                                    deleting={pendingDeleteIds?.has(asset.id) ?? false}
                                    deleteFailed={deleteErrorIds?.has(asset.id) ?? false}
                                    registerRef={selection.registerRow(asset.id)}
                                    onActivate={(additive, range) => {
                                        selection.setActive(asset.id);
                                        setBulkNotice(null);

                                        if (range) {
                                            selection.selectRange(asset.id);

                                            return;
                                        }

                                        if (additive) {
                                            selection.toggleSelected(asset.id);

                                            return;
                                        }

                                        selection.selectOnly(asset.id);
                                    }}
                                    onContextMenu={(x, y) => openMenuAt(asset.id, x, y)}
                                    onKeyDown={(event) => onCellKeyDown(asset, event)}
                                />
                            ))}
                        </ul>

                        <aside
                            aria-label={t('workspace.media.library.desktop.detail.region')}
                            /*
                                Sarınca da GENİŞLEMEZ: altta tam genişliğe
                                yayılan bir bölme, önizlemeyi ekran boyu bir
                                kareye çevirirdi. `20rem` üstte de altta da
                                aynı bölmedir; yalnız yeri değişir.
                            */
                            className="flex h-fit basis-[20rem] grow-0 flex-col gap-[var(--space-2)] rounded-[var(--radius-md)] border border-border p-[var(--space-3)] max-w-[20rem]"
                        >
                            {activeAsset === null ? (
                                <p className="text-meta text-fg-muted">
                                    {t('workspace.media.library.desktop.detail.empty')}
                                </p>
                            ) : (
                                <MediaDetailPane
                                    asset={activeAsset}
                                    canOpenDrawer={actions !== undefined}
                                    onOpenDetails={() => setDetailId(activeAsset.id)}
                                    onDelete={() => requestDelete(activeAsset.id)}
                                    deleting={pendingDeleteIds?.has(activeAsset.id) ?? false}
                                />
                            )}
                        </aside>
                    </div>
                </>
            )}

            {deleteNotice ? (
                <p role="status" className="text-body text-fg-muted">
                    {deleteNotice}
                </p>
            ) : null}
        </div>
    );

    return (
        <div
            role="region"
            aria-label={t('workspace.media.library.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-bold text-fg">{t('workspace.media.library.heading')}</h3>

            {actions ? (
                <Tabs
                    label={t('workspace.media.library.tabs.label')}
                    selectedKey={tab}
                    onChange={(key) => setTab(key === 'trash' ? 'trash' : 'library')}
                    items={[
                        {
                            key: 'library',
                            label: t('workspace.media.library.tabs.library'),
                            panel: libraryPanel,
                        },
                        {
                            key: 'trash',
                            label: t('workspace.media.library.tabs.trash'),
                            panel:
                                tab === 'trash' ? (
                                    <MediaTrashList
                                        loadTrash={actions.loadTrash}
                                        restore={actions.restoreFromTrash}
                                        onRestored={() => onRetry?.()}
                                        retentionDays={trashRetentionDays}
                                    />
                                ) : null,
                        },
                    ]}
                />
            ) : (
                libraryPanel
            )}

            {/*
                `docs/101` A5: yuva envanteri ve yaşam döngüsü uzman
                bilgisidir; ilk ekranda listelenmez, katlanır durur. Mobil
                kütüphanedeki karar burada da geçerli — masaüstünde daha çok
                yer olması, uzman bilgisini öne çıkarmak için sebep değil.
            */}
            <details className="rounded-[var(--radius-lg)] border border-border p-[var(--space-3)]">
                <summary className="cursor-pointer text-body font-medium text-fg-secondary">
                    {t('workspace.media.library.how.summary')}
                </summary>
                <div className="flex flex-col gap-3 pt-3">
                    <p className="text-body font-bold text-fg">
                        {t('workspace.media.library.slots.heading')}
                    </p>
                    <MediaLibrarySlotList />

                    <p className="text-body font-bold text-fg">
                        {t('workspace.media.lifecycle.heading')}
                    </p>
                    <MediaLifecycleList />
                </div>
            </details>

            {actions ? (
                <MediaAssetDetailDrawer
                    asset={detailAsset}
                    actions={actions}
                    onClose={() => setDetailId(null)}
                    onDelete={requestDelete}
                    onChanged={() => onRetry?.()}
                />
            ) : null}

            {actions ? (
                <MediaDeleteImpactDialog
                    asset={impactAsset}
                    loadUsages={actions.loadUsages}
                    onDetachAndDelete={async (id) => {
                        await actions.detach(id);
                        setImpactId(null);
                        setDetailId(null);
                        onDelete(id);
                    }}
                    onClose={() => setImpactId(null)}
                />
            ) : null}

            {menu !== null ? (
                <DesktopContextMenu
                    label={t('workspace.media.library.desktop.menu')}
                    x={menu.x}
                    y={menu.y}
                    items={menuItems}
                    onClose={() => closeMenu(true)}
                />
            ) : null}
        </div>
    );
}

/** Izgaranın tek kutusu — ya da liste görünümünde tek satırı. */
function MediaCellDesktop({
    asset,
    view,
    active,
    selected,
    deleting,
    deleteFailed,
    registerRef,
    onActivate,
    onContextMenu,
    onKeyDown,
}: {
    asset: MediaAsset;
    view: MediaLibraryView;
    active: boolean;
    selected: boolean;
    deleting: boolean;
    deleteFailed: boolean;
    registerRef: (node: HTMLElement | null) => void;
    onActivate: (additive: boolean, range: boolean) => void;
    onContextMenu: (x: number, y: number) => void;
    onKeyDown: (event: React.KeyboardEvent<HTMLLIElement>) => void;
}) {
    const name = displayName(asset);

    return (
        <li
            ref={registerRef}
            role="option"
            aria-selected={selected}
            aria-label={name}
            /*
                ROVING TABINDEX: ızgaraya Tab ile BİR KEZ girilir, içinde
                oklarla gezinilir. Her kutu odaklanabilir olsaydı, kırk
                dosyalık bir kütüphane kırk Tab demekti.
            */
            tabIndex={active ? 0 : -1}
            onKeyDown={onKeyDown}
            onClick={(event) => onActivate(event.ctrlKey || event.metaKey, event.shiftKey)}
            onContextMenu={(event) => {
                event.preventDefault();
                onContextMenu(event.clientX, event.clientY);
            }}
            className={[
                view === 'grid'
                    ? 'flex flex-col gap-2 rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-2)]'
                    : 'flex min-h-[var(--density-row-height)] items-center gap-[var(--space-3)] border-b border-border px-[var(--space-3)] py-[var(--space-2)] last:border-b-0',
                /*
                    `hover` ANLAMLIDIR ve yalnız burada: dokunmada imleç
                    yoktur, o yüzden mobil kütüphanede kutu vurgusu yok.
                */
                'hover:bg-surface-accent',
                selected ? 'bg-surface-accent' : '',
                active ? 'outline outline-2 -outline-offset-2 outline-[var(--color-border)]' : '',
                deleting ? 'opacity-60' : '',
            ].join(' ')}
        >
            {/*
                ÖNİZLEME YALNIZ GERÇEKTEN VARSA çizilir
                (MEDIA-INTAKE-NO-PUBLIC-URL-01): hazır bir türevi olmayan
                dosyanın herkese açık adresi YOKTUR ve uydurma bir görsel,
                taranmamış bir dosyayı taranmış gibi gösterirdi.
            */}
            {asset.previewUrl ? (
                <img
                    src={asset.previewUrl}
                    alt=""
                    className={
                        view === 'grid'
                            ? 'aspect-square w-full rounded-[var(--radius-md)] bg-surface-subtle object-cover'
                            : 'h-[3rem] w-[3rem] shrink-0 rounded-[var(--radius-md)] bg-surface-subtle object-cover'
                    }
                />
            ) : view === 'grid' ? (
                <div
                    aria-hidden="true"
                    className="flex aspect-square w-full items-center justify-center rounded-[var(--radius-md)] bg-surface-subtle text-body text-fg-muted"
                >
                    {t('workspace.media.library.detail.noPreview')}
                </div>
            ) : null}

            <span className="min-w-0 truncate text-body font-medium text-fg">{name}</span>

            <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason} />

            {asset.previewUrl ? null : (
                <span className="flex items-center gap-[var(--space-1)] text-meta text-fg-muted">
                    <LockSimple aria-hidden="true" size={16} />
                    {t('workspace.media.library.access.private')}
                </span>
            )}

            {deleteFailed ? (
                <p role="alert" className="text-body font-medium text-fg-danger">
                    {t('workspace.media.library.asset.delete.failed')}
                </p>
            ) : null}
        </li>
    );
}

/**
 * KALICI AYRINTI BÖLMESİ — üzerinde durulan dosyanın tamamı.
 *
 * Çekmece (`MediaAssetDetailDrawer`) hâlâ duruyor ve kullanım/sürüm/yeniden
 * üretim orada: bu bölme onun yerini almaz, ONDAN ÖNCE gelen soruyu
 * cevaplar — "hangi dosyaya bakıyorum?". Tarama sırasında o soruyu her
 * seferinde bir çekmece açıp kapatarak sormak, kırk dosyada kırk kez
 * kesinti demekti.
 */
function MediaDetailPane({
    asset,
    canOpenDrawer,
    onOpenDetails,
    onDelete,
    deleting,
}: {
    asset: MediaAsset;
    canOpenDrawer: boolean;
    onOpenDetails: () => void;
    onDelete: () => void;
    deleting: boolean;
}) {
    const name = displayName(asset);
    const size = formatBytes(asset.sizeBytes);
    const uploaded = formatDate(asset.createdAt);

    return (
        <div className="flex flex-col gap-[var(--space-2)]">
            {asset.previewUrl ? (
                <img
                    src={asset.previewUrl}
                    alt=""
                    className="aspect-square w-full rounded-[var(--radius-md)] bg-surface-subtle object-cover"
                />
            ) : (
                <div
                    aria-hidden="true"
                    className="flex aspect-square w-full items-center justify-center rounded-[var(--radius-md)] bg-surface-subtle text-body text-fg-muted"
                >
                    {t('workspace.media.library.detail.noPreview')}
                </div>
            )}

            <h4 className="text-section font-bold text-fg">{name}</h4>

            <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason} />

            {asset.previewUrl ? null : (
                <p className="flex items-center gap-[var(--space-1)] text-body text-fg-muted">
                    <LockSimple aria-hidden="true" size={16} />
                    {t('workspace.media.library.access.private')}
                </p>
            )}

            <dl className="flex flex-col gap-1 text-meta text-fg-secondary">
                {asset.originalName ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt>{t('workspace.media.library.detail.file')}</dt>
                        <dd className="min-w-0 truncate text-fg">{asset.originalName}</dd>
                    </div>
                ) : null}
                {size !== '' ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt>{t('workspace.media.library.detail.size')}</dt>
                        <dd className="text-fg tabular-nums">{size}</dd>
                    </div>
                ) : null}
                {uploaded !== '' ? (
                    <div className="flex justify-between gap-[var(--space-2)]">
                        <dt>{t('workspace.media.library.detail.uploaded')}</dt>
                        <dd className="text-fg">{uploaded}</dd>
                    </div>
                ) : null}
                <div className="flex justify-between gap-[var(--space-2)]">
                    <dt>{t('workspace.media.library.detail.slot')}</dt>
                    <dd className="min-w-0 truncate text-fg">{asset.slot}</dd>
                </div>
            </dl>

            {/*
                KULLANIM SAYISI, silmeden ÖNCE okunur. Sıfırdan büyükse
                silme doğrudan gitmez ve sahip bunu düğmeye basmadan önce
                burada görür.
            */}
            {asset.usageCount !== undefined ? (
                <p className="text-meta text-fg-muted">
                    {t('workspace.media.library.asset.usageCount', {
                        count: String(asset.usageCount),
                    })}
                </p>
            ) : null}

            <div className="flex flex-wrap gap-[var(--space-2)]">
                {canOpenDrawer ? (
                    <Button color="light" type="button" onClick={onOpenDetails}>
                        {t('workspace.media.library.desktop.details')}
                    </Button>
                ) : null}
                <Button
                    color="light"
                    type="button"
                    disabled={deleting}
                    onClick={onDelete}
                    aria-label={t('workspace.media.library.asset.delete.named', { name })}
                >
                    {t('workspace.media.library.asset.delete')}
                </Button>
            </div>
        </div>
    );
}

export default MediaLibraryDesktop;
