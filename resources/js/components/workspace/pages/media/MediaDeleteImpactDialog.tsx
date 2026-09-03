import { useEffect, useState } from 'react';
import { ConfirmDialog } from '../../../catalog/overlays/compound/ConfirmDialog';
import { t } from '../../../../i18n/workspace';
import { displayName } from './mediaFormat';
import { MediaUsageList } from './MediaRemoteSection';
import type { MediaAsset, MediaUsage } from '../MediaPage';

type MediaDeleteImpactDialogProps = {
    asset: MediaAsset | null;
    loadUsages: (id: number) => Promise<MediaUsage[]>;
    /** Taslak bağları koparıp çöpe atar. */
    onDetachAndDelete: (id: number) => Promise<void>;
    onClose: () => void;
};

/**
 * Silme ETKİ ÖNİZLEMESİ (`docs/49` Faz 5 madde 2).
 *
 * Kullanıcı yolculuğu: Ayşe "kebap.jpg"i siler → "Adana Kebap ve Urfa Kebap
 * bu fotoğrafı kullanıyor" → iki seçenek: vazgeç ya da bağları koparıp çöpe
 * at. Yayındaki menünün gösterdiği bir görsel buradan hiç silinemez;
 * diyalog bunu söyler, düğmeyi kapatır — sunucudaki 409'u kullanıcıya
 * yaşatmaz.
 *
 * Varlık değişince iç bileşen `key` ile sıfırdan kurulur: durum sıfırlama
 * için effect içinde setState gerekmez.
 */
export function MediaDeleteImpactDialog({ asset, ...rest }: MediaDeleteImpactDialogProps) {
    if (asset === null) {
        return null;
    }

    return <ImpactDialog key={asset.id} asset={asset} {...rest} />;
}

function ImpactDialog({
    asset,
    loadUsages,
    onDetachAndDelete,
    onClose,
}: Omit<MediaDeleteImpactDialogProps, 'asset'> & { asset: MediaAsset }) {
    const [usages, setUsages] = useState<MediaUsage[] | null>(null);
    const [failed, setFailed] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let cancelled = false;

        void loadUsages(asset.id)
            .then((rows) => {
                if (!cancelled) setUsages(rows);
            })
            .catch(() => {
                if (!cancelled) setFailed(true);
            });

        return () => {
            cancelled = true;
        };
    }, [asset.id, loadUsages]);

    const name = displayName(asset);
    const publishedCount = usages?.filter((u) => u.published).length ?? 0;
    const draftCount = usages?.filter((u) => !u.published).length ?? 0;
    const blocked = publishedCount > 0;

    return (
        <ConfirmDialog
            open
            onClose={onClose}
            onConfirm={() => {
                if (blocked || usages === null) return;
                setBusy(true);
                void onDetachAndDelete(asset.id).finally(() => setBusy(false));
            }}
            title={t('workspace.media.library.impact.title', { name })}
            confirmLabel={t('workspace.media.library.impact.confirm', {
                count: String(draftCount),
            })}
            cancelLabel={t('workspace.media.library.impact.cancel')}
            destructive
            confirmLoading={busy}
        >
            {usages === null && !failed ? (
                <p role="status" className="text-body text-fg-muted">
                    {t('workspace.media.library.impact.loading')}
                </p>
            ) : failed ? (
                <p role="alert" className="text-body text-fg-danger">
                    {t('workspace.media.library.impact.failed')}
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    <p className="text-body text-fg-secondary">
                        {t('workspace.media.library.impact.lead', {
                            count: String(usages?.length ?? 0),
                        })}
                    </p>
                    <MediaUsageList usages={usages ?? []} />
                    {blocked ? (
                        <p role="alert" className="text-body font-medium text-fg-danger">
                            {t('workspace.media.library.impact.blocked')}
                        </p>
                    ) : (
                        <p className="text-meta text-fg-muted">
                            {t('workspace.media.library.impact.trashNote')}
                        </p>
                    )}
                </div>
            )}
        </ConfirmDialog>
    );
}
