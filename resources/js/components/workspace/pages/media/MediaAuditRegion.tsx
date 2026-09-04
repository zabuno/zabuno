import { useEffect, useState } from 'react';
import { t } from '../../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../../lib/csrfHeader';

type MediaAuditRow = {
    id: number;
    mediaAssetId: number;
    action: string;
    actor: string | null;
    at: string | null;
};

type MediaAuditRegionProps = {
    workspaceId: number;
};

/** Sunucunun bildirdiği eylem kodunu kullanıcının cümlesine çevirir. */
const ACTION_LABEL: Record<string, Parameters<typeof t>[0]> = {
    uploaded: 'workspace.media.audit.action.uploaded',
    renamed: 'workspace.media.audit.action.renamed',
    trashed: 'workspace.media.audit.action.trashed',
    restored: 'workspace.media.audit.action.restored',
    reprocessed: 'workspace.media.audit.action.reprocessed',
    version_restored: 'workspace.media.audit.action.versionRestored',
    original_download_requested: 'workspace.media.audit.action.downloaded',
};

/**
 * "Bu fotoğrafı kim sildi?" — medya denetim izi (`docs/49` Faz 7 madde 4).
 *
 * Menüden bir yemeğin görseli kaybolduğunda restoran sahibinin sorduğu ilk
 * soru budur ve bugüne kadar cevabı hiçbir ekranda yoktu; kota, izin ve
 * mutabakat vardı, kaydı tutan yoktu.
 *
 * Bölüm KAPALI açılır (`<details>`): günlük iş değildir, bir şey ters
 * gittiğinde açılır. Her gün açık durması, asıl işin (yükleme, kütüphane)
 * altına düşmesi demek olurdu.
 *
 * İzin yoksa (`403`) ya da uç okunamazsa bölüm hiç çizilmez: boş bir
 * "denetim izi" başlığı, kaydın tutulmadığını sanmaya yol açardı.
 */
export function MediaAuditRegion({ workspaceId }: MediaAuditRegionProps) {
    const [rows, setRows] = useState<MediaAuditRow[] | null>(null);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${workspaceId}/media/audits`,
                    buildAuthRequestInit(),
                );

                if (cancelled) return;

                if (!response.ok) {
                    setRows([]);

                    return;
                }

                const body = (await response.json()) as { data?: MediaAuditRow[] };
                setRows(body.data ?? []);
            } catch {
                if (!cancelled) setRows([]);
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    if (rows === null || rows.length === 0) {
        return null;
    }

    return (
        <details className="rounded-[var(--radius-lg)] border border-border bg-surface p-[var(--space-4)]">
            <summary className="cursor-pointer text-body font-semibold text-fg">
                {t('workspace.media.audit.heading')}
            </summary>
            <p className="mt-[var(--space-2)] text-meta text-fg-muted">
                {t('workspace.media.audit.help')}
            </p>
            <ul className="mt-[var(--space-3)] flex flex-col gap-[var(--space-2)]">
                {rows.map((row) => (
                    <li
                        key={row.id}
                        className="flex flex-wrap items-baseline gap-[var(--space-2)] border-b border-border pb-[var(--space-2)] text-body text-fg-secondary last:border-b-0"
                    >
                        <span className="font-medium text-fg">
                            {t(ACTION_LABEL[row.action] ?? 'workspace.media.audit.action.unknown', {
                                id: String(row.mediaAssetId),
                            })}
                        </span>
                        {/*
                            Fail bilinmiyorsa kayıt SİLİNMEZ, "bilinmiyor"
                            yazılır: eylemin olduğu gerçeği, kimin yaptığının
                            bilinmemesinden bağımsızdır.
                        */}
                        <span className="text-meta text-fg-muted">
                            {row.actor ?? t('workspace.media.audit.actor.unknown')}
                        </span>
                        {row.at !== null ? (
                            <span className="text-meta text-fg-muted">{row.at}</span>
                        ) : null}
                    </li>
                ))}
            </ul>
        </details>
    );
}

export default MediaAuditRegion;
