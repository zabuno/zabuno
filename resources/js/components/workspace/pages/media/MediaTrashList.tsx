import { useEffect, useState } from 'react';
import { Button } from '../../../catalog/forms/micro/Button';
import { t } from '../../../../i18n/workspace';
import { formatBytes } from './mediaFormat';
import type { MediaAsset } from '../MediaPage';

type MediaTrashListProps = {
    loadTrash: () => Promise<MediaAsset[]>;
    restore: (id: number) => Promise<void>;
    /** Geri alınan varlık ana listeye döner; sayfa listeyi tazeler. */
    onRestored: () => void;
    retentionDays: number;
};

/**
 * Çöp (`docs/49` Faz 5 madde 3): silinen görseller burada bekler, tek
 * tıkla geri gelir; süresi dolan `media:purge-trash` ile kalıcı gider.
 * Sekme açılınca çekilir — çöp, listeyle birlikte yüklenmez.
 */
export function MediaTrashList({
    loadTrash,
    restore,
    onRestored,
    retentionDays,
}: MediaTrashListProps) {
    const [rows, setRows] = useState<MediaAsset[] | null>(null);
    const [failed, setFailed] = useState(false);
    const [busyId, setBusyId] = useState<number | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        void loadTrash()
            .then((list) => {
                if (!cancelled) setRows(list);
            })
            .catch(() => {
                if (!cancelled) setFailed(true);
            });
        return () => {
            cancelled = true;
        };
    }, [loadTrash]);

    async function handleRestore(id: number) {
        if (busyId !== null) return;
        setBusyId(id);
        setNotice(null);
        try {
            await restore(id);
            setRows((current) => (current ?? []).filter((row) => row.id !== id));
            setNotice(t('workspace.media.library.trash.restored'));
            onRestored();
        } catch {
            setNotice(t('workspace.media.library.trash.restoreFailed'));
        } finally {
            setBusyId(null);
        }
    }

    return (
        <div className="flex flex-col gap-3">
            <p className="text-meta text-fg-muted">
                {t('workspace.media.library.trash.lead', { days: String(retentionDays) })}
            </p>
            {failed ? (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.media.library.trash.failed')}
                </p>
            ) : rows === null ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.trash.loading')}
                </p>
            ) : rows.length === 0 ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.trash.empty')}
                </p>
            ) : (
                <ul
                    aria-label={t('workspace.media.library.trash.heading')}
                    className="flex flex-col gap-2"
                >
                    {rows.map((row) => {
                        const name =
                            row.altText.trim() !== ''
                                ? row.altText
                                : t('workspace.media.library.asset.untitled');
                        return (
                            <li
                                key={row.id}
                                className="flex items-center justify-between gap-3 border-b border-border pb-2"
                            >
                                <div className="flex min-w-0 items-center gap-3">
                                    {row.previewUrl ? (
                                        <img
                                            src={row.previewUrl}
                                            alt=""
                                            className="h-[3rem] w-[3rem] shrink-0 rounded-md object-cover"
                                        />
                                    ) : null}
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate text-body font-medium text-fg">
                                            {name}
                                        </span>
                                        <span className="text-meta text-fg-muted">
                                            {[row.originalName, formatBytes(row.sizeBytes)]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    color="light"
                                    type="button"
                                    className="text-meta"
                                    disabled={busyId !== null}
                                    onClick={() => void handleRestore(row.id)}
                                    aria-label={t('workspace.media.library.trash.restore.named', {
                                        name,
                                    })}
                                >
                                    {t('workspace.media.library.trash.restore')}
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
            {notice ? (
                <p role="status" className="text-meta text-fg-secondary">
                    {notice}
                </p>
            ) : null}
        </div>
    );
}
