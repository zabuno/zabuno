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
            <h3 className="text-body font-semibold text-fg">
                {t('workspace.publication.publishedSnapshot.region')}
            </h3>

            <p className="text-body text-fg-secondary">
                {t('workspace.publication.publishedSnapshot.publishedAt', {
                    publishedAt: current.publishedAt,
                })}
            </p>

            <div className="flex flex-col gap-4">
                {current.snapshot.categories.map((category, categoryIndex) => (
                    <div key={categoryIndex} className="flex flex-col gap-2">
                        <p className="text-meta font-semibold text-fg-muted">{category.name}</p>
                        <ul className="flex flex-col gap-2">
                            {category.menuItems.map((item, itemIndex) => (
                                <li key={itemIndex} className="text-body text-fg-secondary">
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
