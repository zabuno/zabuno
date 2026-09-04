import { useMemo, useState } from 'react';
import { LockSimple } from '@phosphor-icons/react';
import { Button } from '../../../catalog/forms/micro/Button';
import { Checkbox } from '../../../catalog/forms/micro/Checkbox';
import { Select } from '../../../catalog/forms/micro/Select';
import { TextInput } from '../../../catalog/forms/micro/TextInput';
import { Tabs } from '../../../catalog/navigation/compound/Tabs';
import { t } from '../../../../i18n/workspace';
import { MediaAssetDetailDrawer } from './MediaAssetDetailDrawer';
import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';
import { MediaDeleteImpactDialog } from './MediaDeleteImpactDialog';
import { MediaLifecycleList } from './MediaLifecycleList';
import { MediaLibrarySlotList } from './MediaLibrarySlotList';
import { MediaTrashList } from './MediaTrashList';
import { MediaLibraryToolbar, type MediaLibraryView } from './MediaLibraryToolbar';
import { MEDIA_SORT_ORDER, type MediaSortKey } from './mediaSort';
import type { MediaFolder, MediaFolderId } from './MediaFolderRail';
import { displayName, formatBytes } from './mediaFormat';
import type { MediaAsset, MediaLibraryActions } from '../MediaPage';

export type MediaLibraryLoadState = 'loading' | 'idle' | 'error';

type MediaLibraryRegionProps = {
    assets: MediaAsset[];
    onDelete: (id: number) => void;
    loadState: MediaLibraryLoadState;
    onRetry?: () => void;
    pendingDeleteIds?: Set<number>;
    deleteErrorIds?: Set<number>;
    deleteNotice?: string | null;
    /**
     * Kütüphane eylemleri (kullanım, sürüm, çöp). Verilmezse bölge yalnız
     * listeler ve siler — bileşen tek başına da çalışır.
     */
    actions?: MediaLibraryActions;
    trashRetentionDays?: number;
    /**
     * Arama KABUKTAN gelebilir (`MediaManagerShell`). Verildiğinde bölge
     * kendi arama kutusunu çizmez: aynı ekranda iki arama alanı, hangisinin
     * geçerli olduğunu belirsizleştirir.
     */
    query?: string;
    /** Klasörler — boşsa hap şeridi hiç çizilmez. */
    folders?: MediaFolder[];
    activeFolderId?: MediaFolderId | null;
    onFolderChange?: (id: MediaFolderId | null) => void;
};

const STATUS_ORDER = [
    'ready',
    'processing',
    'accepted',
    'scanning',
    'quarantined',
    'failed',
    'rejected',
] as const;

/**
 * Sıralama karşılaştırıcıları.
 *
 * Elimizde OLMAYAN alana göre sıralamayız: `createdAt` ya da `sizeBytes`
 * gelmediğinde satır sırası KORUNUR (kararlı sıralama), uydurma bir sıraya
 * itilmez.
 */
function compareAssets(a: MediaAsset, b: MediaAsset, sort: MediaSortKey): number {
    if (sort === 'name') {
        return displayName(a).localeCompare(displayName(b));
    }

    if (sort === 'largest') {
        return (b.sizeBytes ?? 0) - (a.sizeBytes ?? 0);
    }

    const left = a.createdAt ? Date.parse(a.createdAt) : Number.NaN;
    const right = b.createdAt ? Date.parse(b.createdAt) : Number.NaN;

    if (Number.isNaN(left) && Number.isNaN(right)) return 0;
    if (Number.isNaN(left)) return 1;
    if (Number.isNaN(right)) return -1;

    return right - left;
}

/**
 * Kütüphane (`docs/49` Faz 4-5, `docs/98` FF-70, FF-131 kanonik kaynak):
 * klasör hapları, Süz/Sırala/Görünüm, sonuç sayısı ve "tümünü seç", çoklu
 * seçim; satıra tıkla → detay çekmecesi; sil → kullanılıyorsa etki
 * önizlemesi, kullanılmıyorsa çöpe; Çöp sekmesi → geri al.
 *
 * Yalnız API'nin döndürdüğü gerçek varlıklar çizilir; önizleme yalnız hazır
 * bir rendition varsa `<img>` olur — karantinadaki dosyanın herkese açık
 * adresi yoktur (MEDIA-INTAKE-NO-PUBLIC-URL-01).
 */
export function MediaLibraryRegion({
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
}: MediaLibraryRegionProps) {
    const [ownQuery, setOwnQuery] = useState('');
    const [slot, setSlot] = useState('');
    const [status, setStatus] = useState('');
    const [unusedOnly, setUnusedOnly] = useState(false);
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [sort, setSort] = useState<MediaSortKey>('newest');
    const [view, setView] = useState<MediaLibraryView>('list');
    const [tab, setTab] = useState<'library' | 'trash'>('library');
    const [detailId, setDetailId] = useState<number | null>(null);
    const [impactId, setImpactId] = useState<number | null>(null);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkNotice, setBulkNotice] = useState<string | null>(null);

    const externalQuery = query !== undefined;
    const effectiveQuery = query ?? ownQuery;

    const slots = useMemo(() => Array.from(new Set(assets.map((a) => a.slot))).sort(), [assets]);
    const statuses = useMemo(() => {
        const present = new Set(assets.map((a) => a.status));
        return STATUS_ORDER.filter((s) => present.has(s));
    }, [assets]);

    const visible = useMemo(() => {
        const needle = effectiveQuery.trim().toLocaleLowerCase();
        const matched = assets.filter((asset) => {
            if (activeFolderId !== null && asset.folderId !== activeFolderId) return false;
            if (slot !== '' && asset.slot !== slot) return false;
            if (status !== '' && asset.status !== status) return false;
            if (unusedOnly && (asset.usageCount ?? 0) > 0) return false;
            if (needle === '') return true;
            return (
                asset.altText.toLocaleLowerCase().includes(needle) ||
                (asset.originalName ?? '').toLocaleLowerCase().includes(needle)
            );
        });

        return [...matched].sort((a, b) => compareAssets(a, b, sort));
    }, [assets, effectiveQuery, slot, status, unusedOnly, activeFolderId, sort]);

    const detailAsset = assets.find((a) => a.id === detailId) ?? null;
    const impactAsset = assets.find((a) => a.id === impactId) ?? null;
    const activeFilterCount =
        (slot !== '' ? 1 : 0) + (status !== '' ? 1 : 0) + (unusedOnly ? 1 : 0);
    const filtersActive = effectiveQuery !== '' || activeFilterCount > 0 || activeFolderId !== null;
    const toolbarVisible = loadState === 'idle' && assets.length > 1;

    const selectedVisible = visible.filter((asset) => selectedIds.has(asset.id));
    const allSelected = visible.length > 0 && selectedVisible.length === visible.length;

    function toggleSelected(id: number) {
        setSelectedIds((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    }

    function toggleSelectAll() {
        setBulkNotice(null);
        setSelectedIds(allSelected ? new Set() : new Set(visible.map((asset) => asset.id)));
    }

    /*
        TOPLU SİLMEDE SESSİZ ATLAMA YOKTUR.

        Menüde duran bir fotoğraf toplu silmeyle GİTMEZ: nerede kullanıldığı
        tek tek gösterilmeli (etki önizlemesi). Ama sahip üç dosya seçip
        ikisinin silindiğini görürse, üçüncüsünün neden durduğunu bir yerde
        okumalı — yoksa onu da silinmiş sanar.
    */
    function deleteSelected() {
        const kept: number[] = [];

        selectedVisible.forEach((asset) => {
            if ((asset.usageCount ?? 0) > 0) {
                kept.push(asset.id);
                return;
            }
            onDelete(asset.id);
        });

        setSelectedIds(new Set(kept));
        setBulkNotice(
            kept.length > 0
                ? t('workspace.media.library.select.kept', { count: String(kept.length) })
                : null,
        );
    }

    /*
        Kullanılmayan görsel doğrudan çöpe gider — çöp geri alınabilir, bir
        onay diyaloğu daha kullanıcıyı yormaktan başka iş görmez. Kullanılan
        görselde önce etki önizlemesi açılır (`docs/49` Faz 5 madde 2).
    */
    function requestDelete(id: number) {
        const asset = assets.find((a) => a.id === id);
        if (actions && asset && (asset.usageCount ?? 0) > 0) {
            setImpactId(id);
            return;
        }
        setDetailId(null);
        onDelete(id);
    }

    function renderRow(asset: MediaAsset) {
        const isDeleting = pendingDeleteIds?.has(asset.id) ?? false;
        const hasDeleteError = deleteErrorIds?.has(asset.id) ?? false;
        const name = displayName(asset);
        const meta = [
            asset.originalName,
            formatBytes(asset.sizeBytes),
            asset.usageCount !== undefined
                ? t('workspace.media.library.asset.usageCount', { count: String(asset.usageCount) })
                : null,
        ].filter(Boolean);

        return (
            <li
                key={asset.id}
                className={
                    view === 'grid'
                        ? 'flex flex-col gap-2 rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-2)]'
                        : /*
                              LİSTE GÖRÜNÜMÜNDE SATIR KART DEĞİLDİR (FF-131).
                              Izgara görünümünde kutu anlamlıdır — her kutu bir
                              görselin sınırıdır. Listede ise sınır bilgi
                              taşımaz; satırlar zaten alt alta ve tek ritimde.
                          */
                          'flex min-h-[var(--density-row-height)] flex-col justify-center gap-[var(--space-1)] border-t border-border px-[var(--density-padding-inline)] py-[var(--space-2)] first:border-t-0'
                }
            >
                {/*
                    Çoklu seçim yalnız SEÇİLECEK BİRDEN FAZLA ŞEY varken
                    çizilir: tek dosyalı bir kütüphanede işaret kutusu, hiçbir
                    şeyi kolaylaştırmayan bir tıklama daha demektir.
                */}
                {toolbarVisible ? (
                    <Checkbox
                        className="self-start"
                        checked={selectedIds.has(asset.id)}
                        onChange={() => toggleSelected(asset.id)}
                        aria-label={t('workspace.media.library.select.named', { name })}
                    />
                ) : null}
                {asset.previewUrl ? (
                    <img
                        src={asset.previewUrl}
                        alt=""
                        className={
                            view === 'grid'
                                ? 'aspect-square w-full rounded-[var(--radius-md)] bg-surface-subtle object-cover'
                                : 'h-[3rem] w-[3rem] rounded-[var(--radius-md)] bg-surface-subtle object-cover'
                        }
                    />
                ) : view === 'grid' ? (
                    <div
                        aria-hidden="true"
                        /*
                            Önizlemesi olmayan varlığa UYDURMA GÖRSEL çizilmez
                            (MEDIA-INTAKE-NO-PUBLIC-URL-01): burada duran şey
                            bir fotoğraf değil bir CÜMLEDİR — "henüz önizleme
                            yok" — ve o yüzden gövde ölçeğindedir.
                        */
                        className="flex aspect-square w-full items-center justify-center rounded-[var(--radius-md)] bg-surface-subtle text-body text-fg-muted"
                    >
                        {t('workspace.media.library.detail.noPreview')}
                    </div>
                ) : null}
                {/*
                    Satırın adı, kullanıcının KENDİ yazdığı alt metindir; tablo
                    anahtarı (`#7`) bir ad değildir. Ad bir düğmedir: tıklayınca
                    detay çekmecesi açılır (kullanım, sürüm, yeniden üretim).
                */}
                {actions ? (
                    <button
                        type="button"
                        data-media-asset-name=""
                        onClick={() => setDetailId(asset.id)}
                        className="self-start text-start text-body font-medium text-fg underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        aria-label={t('workspace.media.library.asset.details.named', { name })}
                    >
                        {name}
                    </button>
                ) : (
                    <span data-media-asset-name="" className="text-body font-medium text-fg">
                        {name}
                    </span>
                )}
                {meta.length > 0 ? (
                    /* Boyut ve kullanım sayısı alt alta okunur: sabit rakam. */
                    <span className="text-meta text-fg-muted tabular-nums">{meta.join(' · ')}</span>
                ) : null}
                {/* Sebep rozetin içindedir; ikinci canlı bölge aynı şeyi iki kez okutur (`docs/76`). */}
                <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason} />
                {/*
                    ERİŞİM İŞARETİ. Hazır bir türevi olmayan dosyanın herkese
                    açık bir adresi YOKTUR — bu bir gecikme değil, bir güvenlik
                    kararıdır (MEDIA-INTAKE-NO-PUBLIC-URL-01). Sahip menüye
                    koyduğu fotoğrafın neden görünmediğini burada okur.
                */}
                {asset.previewUrl ? null : (
                    <span className="flex items-center gap-[var(--space-1)] text-body text-fg-muted">
                        <LockSimple aria-hidden="true" size={16} />
                        {t('workspace.media.library.access.private')}
                    </span>
                )}
                <Button
                    color="light"
                    type="button"
                    disabled={isDeleting}
                    onClick={() => requestDelete(asset.id)}
                    className="self-start"
                    aria-label={t('workspace.media.library.asset.delete.named', { name })}
                >
                    {t('workspace.media.library.asset.delete')}
                </Button>
                {hasDeleteError && (
                    /* Hata metni GÖVDE ölçeğindedir; meta rolü sayaç içindir. */
                    <p role="alert" className="text-body font-medium text-fg-danger">
                        {t('workspace.media.library.asset.delete.failed')}
                    </p>
                )}
            </li>
        );
    }

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

    const libraryPanel = (
        <div className="flex flex-col gap-3">
            <h4 className="text-body font-bold text-fg">
                {t('workspace.media.library.assets.heading')}
            </h4>

            {/*
                Kabuk bir arama alanı taşıyorsa bölge KENDİ kutusunu çizmez:
                aynı ekranda iki arama alanı, hangisinin geçerli olduğunu
                belirsizleştirir.
            */}
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
                    selectedCount={selectedVisible.length}
                    allSelected={allSelected}
                    onToggleSelectAll={toggleSelectAll}
                    onDeleteSelected={deleteSelected}
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
                <ul
                    aria-label={t('workspace.media.library.assets.heading')}
                    className={
                        view === 'grid'
                            ? 'grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-[var(--space-3)]'
                            : /*
                                  LİSTEDE SATIRLAR ARASINDA BOŞLUK YOKTUR:
                                  boşluk + ayraç birlikte çizgiyi satırdan
                                  koparır. Ritim `border-t` ile kurulur.
                              */
                              'flex flex-col'
                    }
                >
                    {visible.map(renderRow)}
                </ul>
            )}

            {deleteNotice && (
                <p role="status" className="text-body text-fg-muted">
                    {deleteNotice}
                </p>
            )}
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
                `docs/101` A5: slot envanteri ve yaşam döngüsü uzman bilgisidir;
                kebapçının ilk ekranında listelenmez, katlanır durur. İçerik
                DOM'da kalır (ekran okuyucu ve sözleşme testleri).
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
        </div>
    );
}

export default MediaLibraryRegion;
