import { Button } from '../../../catalog/forms/micro/Button';
import { t } from '../../../../i18n/workspace';
import { MediaAssetStatusBadge } from './MediaAssetStatusBadge';
import { MediaLifecycleList } from './MediaLifecycleList';
import { MediaLibrarySlotList } from './MediaLibrarySlotList';
import type { MediaAsset } from '../MediaPage';

export type MediaLibraryLoadState = 'loading' | 'idle' | 'error';

type MediaLibraryRegionProps = {
    assets: MediaAsset[];
    onDelete: (id: number) => void;
    loadState: MediaLibraryLoadState;
    onRetry?: () => void;
    pendingDeleteIds?: Set<number>;
    deleteErrorIds?: Set<number>;
    deleteNotice?: string | null;
};

/**
 * Only real quarantined assets returned by the API are rendered here — no
 * image/img element, no public URL, no Ready/Published claim (MEDIA-INTAKE-
 * NO-PUBLIC-URL-01, MEDIA-INTAKE-STATUS-01).
 */
export function MediaLibraryRegion({
    assets,
    onDelete,
    loadState,
    onRetry,
    pendingDeleteIds,
    deleteErrorIds,
    deleteNotice,
}: MediaLibraryRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.media.library.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.media.library.heading')}
            </h3>

            {/*
                Gerçek bir başlık, başlık GİBİ GÖRÜNEN bir paragraf değil.
                Önceden `<p>` idi: ekranda başlık gibi duruyor ama anlamsal
                olarak hiçbir şeyi etiketlemiyordu — ekran okuyucu kullanan biri
                bu bölgedeki üç listeyi (varlıklar, slot kategorileri, yaşam
                döngüsü) birbirinden ayıramıyordu.
            */}
            <h4 className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.library.assets.heading')}
            </h4>

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
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.unavailable')}
                </p>
            ) : (
                <ul
                    aria-label={t('workspace.media.library.assets.heading')}
                    className="flex flex-col gap-2"
                >
                    {assets.map((asset) => {
                        const isDeleting = pendingDeleteIds?.has(asset.id) ?? false;
                        const hasDeleteError = deleteErrorIds?.has(asset.id) ?? false;
                        const assetName =
                            asset.altText.trim() !== ''
                                ? asset.altText
                                : t('workspace.media.library.asset.untitled');

                        return (
                            <li
                                key={asset.id}
                                className="flex flex-col gap-1 border-b border-border pb-2"
                            >
                                {/*
                                    Satırın adı, kullanıcının KENDİ yazdığı alt
                                    metindir. Önceden birincil etiket varlığın
                                    veritabanı kimliğiydi (`#7`) ve alt metin onun
                                    altında ikincil duruyordu: kullanıcının yüklediği
                                    fotoğraf, kendi verdiği adla değil bir tablo
                                    anahtarıyla listeleniyordu.
                                */}
                                <span className="text-body font-medium text-fg">{assetName}</span>
                                <MediaAssetStatusBadge status={asset.status} />
                                <Button
                                    color="light"
                                    type="button"
                                    disabled={isDeleting}
                                    onClick={() => onDelete(asset.id)}
                                    className="self-start text-meta"
                                    // Silme geri alınamaz. Üç satırın üçünde de
                                    // "Delete" yazan düğme, ekran okuyucuda hangi
                                    // görseli sildiğini söylemiyordu.
                                    aria-label={t('workspace.media.library.asset.delete.named', {
                                        name: assetName,
                                    })}
                                >
                                    {t('workspace.media.library.asset.delete')}
                                </Button>
                                {hasDeleteError && (
                                    <p
                                        role="alert"
                                        className="text-meta font-medium text-fg-danger"
                                    >
                                        {t('workspace.media.library.asset.delete.failed')}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {deleteNotice && (
                <p role="status" className="text-meta text-fg-muted">
                    {deleteNotice}
                </p>
            )}

            <p className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.library.slots.heading')}
            </p>
            <MediaLibrarySlotList />

            <p className="text-meta font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.lifecycle.heading')}
            </p>
            <MediaLifecycleList />
        </div>
    );
}

export default MediaLibraryRegion;
