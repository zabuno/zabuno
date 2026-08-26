import { PlainButton } from '../../../catalog/forms/micro/PlainButton';
import { Button } from 'flowbite-react';
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
            <h3 className="text-sm font-semibold text-fg">
                {t('workspace.media.library.heading')}
            </h3>

            <p className="text-xs font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.library.assets.heading')}
            </p>

            {loadState === 'loading' ? (
                <p role="status" className="text-sm text-fg-muted">
                    {t('workspace.media.library.loading')}
                </p>
            ) : loadState === 'error' ? (
                <div className="flex flex-col items-start gap-2">
                    <p role="alert" className="text-sm font-medium text-fg-danger">
                        {t('workspace.media.library.error')}
                    </p>
                    <Button onClick={() => onRetry?.()}>{t('workspace.error.retry')}</Button>
                </div>
            ) : assets.length === 0 ? (
                <p role="status" className="text-sm text-fg-muted">
                    {t('workspace.media.library.unavailable')}
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {assets.map((asset) => {
                        const isDeleting = pendingDeleteIds?.has(asset.id) ?? false;
                        const hasDeleteError = deleteErrorIds?.has(asset.id) ?? false;

                        return (
                            <li
                                key={asset.id}
                                className="flex flex-col gap-1 border-b border-border pb-2"
                            >
                                <span className="text-sm font-medium text-fg">{`#${asset.id}`}</span>
                                <span className="text-sm text-fg-secondary">{asset.altText}</span>
                                <MediaAssetStatusBadge status={asset.status} />
                                <PlainButton
                                    type="button"
                                    disabled={isDeleting}
                                    onClick={() => onDelete(asset.id)}
                                    className="self-start text-xs"
                                >
                                    {t('workspace.media.library.asset.delete')}
                                </PlainButton>
                                {hasDeleteError && (
                                    <p role="alert" className="text-xs font-medium text-fg-danger">
                                        {t('workspace.media.library.asset.delete.failed')}
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}

            {deleteNotice && (
                <p role="status" className="text-xs text-fg-muted">
                    {deleteNotice}
                </p>
            )}

            <p className="text-xs font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.library.slots.heading')}
            </p>
            <MediaLibrarySlotList />

            <p className="text-xs font-semibold uppercase tracking-wide text-fg-muted">
                {t('workspace.media.lifecycle.heading')}
            </p>
            <MediaLifecycleList />
        </div>
    );
}

export default MediaLibraryRegion;
