import { useMemo, useState } from 'react';
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
};

type ViewMode = 'list' | 'grid';

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
 * Kütüphane (`docs/49` Faz 4-5, `docs/98` FF-70): ara, slot/durum süz,
 * "kullanılmayanlar", liste/ızgara; satıra tıkla → detay çekmecesi; sil →
 * kullanılıyorsa etki önizlemesi, kullanılmıyorsa çöpe; Çöp sekmesi →
 * geri al.
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
}: MediaLibraryRegionProps) {
    const [query, setQuery] = useState('');
    const [slot, setSlot] = useState('');
    const [status, setStatus] = useState('');
    const [unusedOnly, setUnusedOnly] = useState(false);
    const [view, setView] = useState<ViewMode>('list');
    const [tab, setTab] = useState<'library' | 'trash'>('library');
    const [detailId, setDetailId] = useState<number | null>(null);
    const [impactId, setImpactId] = useState<number | null>(null);

    const slots = useMemo(() => Array.from(new Set(assets.map((a) => a.slot))).sort(), [assets]);
    const statuses = useMemo(() => {
        const present = new Set(assets.map((a) => a.status));
        return STATUS_ORDER.filter((s) => present.has(s));
    }, [assets]);

    const visible = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase();
        return assets.filter((asset) => {
            if (slot !== '' && asset.slot !== slot) return false;
            if (status !== '' && asset.status !== status) return false;
            if (unusedOnly && (asset.usageCount ?? 0) > 0) return false;
            if (needle === '') return true;
            return (
                asset.altText.toLocaleLowerCase().includes(needle) ||
                (asset.originalName ?? '').toLocaleLowerCase().includes(needle)
            );
        });
    }, [assets, query, slot, status, unusedOnly]);

    const detailAsset = assets.find((a) => a.id === detailId) ?? null;
    const impactAsset = assets.find((a) => a.id === impactId) ?? null;
    const filtersActive = query !== '' || slot !== '' || status !== '' || unusedOnly;

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
                {asset.previewUrl ? (
                    <img
                        src={asset.previewUrl}
                        alt=""
                        className={
                            view === 'grid'
                                ? 'aspect-square w-full rounded-[var(--radius-md)] bg-surface-muted object-cover'
                                : 'h-[3rem] w-[3rem] rounded-[var(--radius-md)] bg-surface-muted object-cover'
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
                        className="flex aspect-square w-full items-center justify-center rounded-[var(--radius-md)] bg-surface-muted text-body text-fg-muted"
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
                        onClick={() => setDetailId(asset.id)}
                        className="self-start text-start text-body font-medium text-fg underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        aria-label={t('workspace.media.library.asset.details.named', { name })}
                    >
                        {name}
                    </button>
                ) : (
                    <span className="text-body font-medium text-fg">{name}</span>
                )}
                {meta.length > 0 ? (
                    /* Boyut ve kullanım sayısı alt alta okunur: sabit rakam. */
                    <span className="text-meta text-fg-muted tabular-nums">{meta.join(' · ')}</span>
                ) : null}
                {/* Sebep rozetin içindedir; ikinci canlı bölge aynı şeyi iki kez okutur (`docs/76`). */}
                <MediaAssetStatusBadge status={asset.status} reason={asset.statusReason} />
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

    const libraryPanel = (
        <div className="flex flex-col gap-3">
            <h4 className="text-body font-bold text-fg">
                {t('workspace.media.library.assets.heading')}
            </h4>

            {/* Tek varlıkta arama/süzgeç anlamsızdır; iki ve üzerinde açılır. */}
            {loadState === 'idle' && assets.length > 1 ? (
                <div
                    role="group"
                    aria-label={t('workspace.media.library.filters.label')}
                    className="flex flex-wrap items-end gap-2"
                >
                    {/*
                        Süzgeç ETİKETLERİ gövde metnidir: "Search", "Slot",
                        "Status" birer sayaç değil, kullanıcının okuduğu addır
                        (`app.css` meta rolünü zaman damgası/sayaçla sınırlar).
                    */}
                    <label className="flex min-w-0 flex-1 flex-col gap-1 text-body text-fg-secondary">
                        {t('workspace.media.library.filters.search')}
                        <TextInput
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t('workspace.media.library.filters.searchPlaceholder')}
                        />
                    </label>
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
                    <div
                        role="group"
                        aria-label={t('workspace.media.library.view.label')}
                        className="flex gap-1"
                    >
                        <Button
                            color="light"
                            type="button"
                            aria-pressed={view === 'list'}
                            onClick={() => setView('list')}
                        >
                            {t('workspace.media.library.view.list')}
                        </Button>
                        <Button
                            color="light"
                            type="button"
                            aria-pressed={view === 'grid'}
                            onClick={() => setView('grid')}
                        >
                            {t('workspace.media.library.view.grid')}
                        </Button>
                    </div>
                </div>
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

            {filtersActive && loadState === 'idle' && assets.length > 1 ? (
                <p className="text-meta text-fg-muted tabular-nums">
                    {t('workspace.media.library.filters.count', {
                        shown: String(visible.length),
                        total: String(assets.length),
                    })}
                </p>
            ) : null}

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
