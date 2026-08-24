import { t } from '../../../../i18n/workspace';
import type { CurrentPublication } from './PublicationStatusRegion';

type PublishedSnapshotRegionProps = {
    current: CurrentPublication;
};

export function PublishedSnapshotRegion({ current }: PublishedSnapshotRegionProps) {
    return (
        <div
            role="region"
            aria-label={t('workspace.publication.publishedSnapshot.region')}
            className="flex flex-col gap-3"
        >
            <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                {t('workspace.publication.publishedSnapshot.region')}
            </h3>

            <p className="text-sm text-gray-700 dark:text-gray-300">
                {t('workspace.publication.publishedSnapshot.publishedAt', {
                    publishedAt: current.publishedAt,
                })}
            </p>

            <div className="flex flex-col gap-4">
                {current.snapshot.categories.map((category, categoryIndex) => (
                    <div key={categoryIndex} className="flex flex-col gap-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {category.name}
                        </p>
                        <ul className="flex flex-col gap-2">
                            {category.menuItems.map((item, itemIndex) => (
                                <li
                                    key={itemIndex}
                                    className="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {item.productName}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default PublishedSnapshotRegion;
