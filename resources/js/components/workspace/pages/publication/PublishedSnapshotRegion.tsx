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
            <h3 className="text-body font-bold text-fg">
                {t('workspace.publication.publishedSnapshot.region')}
            </h3>

            {/*
                Zaman damgası `--text-meta`nın MEŞRU kullanımıdır ve
                `tabular-nums` taşır: aynı damga sürüm listesinde de geçer,
                iki yerde farklı ritimde çizilmemeli.
            */}
            <p className="text-meta tabular-nums text-fg-muted">
                {t('workspace.publication.publishedSnapshot.publishedAt', {
                    publishedAt: current.publishedAt,
                })}
            </p>

            <div className="flex flex-col gap-4">
                {current.snapshot.categories.map((category, categoryIndex) => (
                    <div key={categoryIndex} className="flex flex-col">
                        {/*
                            Kategori adı GÖVDE tabanındadır. `--text-meta`
                            yalnız zaman damgası ve sayaç içindir; sahip
                            yayındaki menüsünü "ikincil bilgi" olarak okumaz.
                        */}
                        <p className="text-body font-bold text-fg">{category.name}</p>
                        {/*
                            Ürünler KART DEĞİL, ince ayraçlı satırlar: yayında
                            ne olduğu tek ritimde okunan bir listedir.
                        */}
                        <ul className="flex flex-col">
                            {category.menuItems.map((item, itemIndex) => (
                                <li
                                    key={itemIndex}
                                    className="flex min-h-[var(--density-row-height)] items-center border-t border-border py-[var(--space-1)] text-body text-fg-secondary first:border-t-0"
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
