import type { ReactNode } from 'react';
import { t } from '../../../../i18n/workspace';
import type { MediaUsage } from '../MediaPage';

export type Remote<T> = { state: 'loading' } | { state: 'error' } | { state: 'ready'; rows: T[] };

type MediaRemoteSectionProps<T> = {
    id: string;
    heading: string;
    remote: Remote<T>;
    loading: string;
    failed: string;
    empty: string;
    children: (rows: T[]) => ReactNode;
};

/**
 * Uzaktan gelen bir listenin dört hâli tek yerde: yükleniyor / hata / boş /
 * dolu. Çekmecede kullanım ve sürümler, diyalogda kullanım aynı iskeleti
 * paylaşır — üç kopya, üç ayrı yerde yaşlanırdı.
 */
export function MediaRemoteSection<T>({
    id,
    heading,
    remote,
    loading,
    failed,
    empty,
    children,
}: MediaRemoteSectionProps<T>) {
    return (
        <section aria-labelledby={id} className="flex flex-col gap-2">
            <h4 id={id} className="text-meta font-semibold text-fg-muted">
                {heading}
            </h4>
            {remote.state === 'loading' ? (
                <p role="status" className="text-body text-fg-muted">
                    {loading}
                </p>
            ) : remote.state === 'error' ? (
                <p role="alert" className="text-body text-fg-danger">
                    {failed}
                </p>
            ) : remote.rows.length === 0 ? (
                <p className="text-body text-fg-muted">{empty}</p>
            ) : (
                children(remote.rows)
            )}
        </section>
    );
}

/** "Nerede kullanılıyor?" satırları — ürün adı + taslak/canlı işareti. */
export function MediaUsageList({ usages }: { usages: MediaUsage[] }) {
    return (
        <ul
            aria-label={t('workspace.media.library.usages.heading')}
            className="flex flex-col gap-1"
        >
            {usages.map((usage) => (
                <li
                    key={`${usage.entityType}-${usage.entityId}-${usage.slot}`}
                    className="flex items-center justify-between gap-2 text-body"
                >
                    <span className="text-fg">{usage.label}</span>
                    <span className="text-meta text-fg-muted">
                        {usage.published
                            ? t('workspace.media.library.usages.live')
                            : t('workspace.media.library.usages.draft')}
                    </span>
                </li>
            ))}
        </ul>
    );
}
