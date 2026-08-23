import { t } from '../../../../i18n/workspace';
import { MediaLifecycleList } from './MediaLifecycleList';
import { MediaLibrarySlotList } from './MediaLibrarySlotList';
import type { MediaAsset } from '../MediaPage';

type MediaLibraryRegionProps = {
    assets: MediaAsset[];
    onDelete: (id: number) => void;
};

/**
 * Only real quarantined assets returned by the API are rendered here — no
 * image/img element, no public URL, no Ready/Published claim (MEDIA-INTAKE-
 * NO-PUBLIC-URL-01, MEDIA-INTAKE-STATUS-01).
 */
export function MediaLibraryRegion({ assets, onDelete }: MediaLibraryRegionProps) {
    return (
        <div role="region" aria-label={t('workspace.media.library.region')} className="flex flex-col gap-3">
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.media.library.heading')}
            </h3>

            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.media.library.assets.heading')}
            </p>

            {assets.length === 0 ? (
                <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                    {t('workspace.media.library.unavailable')}
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {assets.map((asset) => (
                        <li key={asset.id} className="flex flex-col gap-1 border-b border-gray-200 pb-2 dark:border-gray-700">
                            <span className="text-sm font-medium text-gray-900 dark:text-white">{`#${asset.id}`}</span>
                            <span className="text-sm text-gray-700 dark:text-gray-300">{asset.altText}</span>
                            <span role="status" className="text-sm text-gray-500 dark:text-gray-400">
                                {t('workspace.media.library.asset.status.scanPending')}
                            </span>
                            <button
                                type="button"
                                onClick={() => onDelete(asset.id)}
                                className="self-start rounded-lg border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 dark:border-gray-600 dark:text-gray-300"
                            >
                                {t('workspace.media.library.asset.delete')}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.media.library.slots.heading')}
            </p>
            <MediaLibrarySlotList />

            <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {t('workspace.media.lifecycle.heading')}
            </p>
            <MediaLifecycleList />
        </div>
    );
}

export default MediaLibraryRegion;
