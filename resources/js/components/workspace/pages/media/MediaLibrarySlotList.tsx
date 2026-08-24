import { t } from '../../../../i18n/workspace';

const SLOT_CATEGORY_KEYS = [
    'workspace.media.library.slots.category.corporateSite',
    'workspace.media.library.slots.category.restaurant',
    'workspace.media.library.slots.category.menu',
    'workspace.media.library.slots.category.product',
    'workspace.media.library.slots.category.qr',
    'workspace.media.library.slots.category.email',
] as const;

/**
 * No fake asset, count, id, or thumbnail — each category only states that
 * no assets are available yet until a real Media API can back it.
 */
export function MediaLibrarySlotList() {
    return (
        <div data-testid="media-library-slot-inventory" className="flex flex-col gap-2">
            <ul data-testid="media-slot-category-inventory" className="flex flex-col gap-2">
                {SLOT_CATEGORY_KEYS.map((key) => {
                    const category = t(key);
                    return (
                        <li key={key} className="flex flex-col gap-1">
                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                                {category}
                            </span>
                            <p role="status" className="text-sm text-gray-500 dark:text-gray-400">
                                {t('workspace.media.library.slots.status.noAssetsYet', {
                                    category,
                                })}
                            </p>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

export default MediaLibrarySlotList;
