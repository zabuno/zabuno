import { useCallback, useEffect, useRef, useState } from 'react';
import { t } from '../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../lib/csrfHeader';
import { AiAssistPanel } from '../ai/AiAssistPanel';
import { MediaUploadRegion } from './media/MediaUploadRegion';
import { MediaLibraryRegion, type MediaLibraryLoadState } from './media/MediaLibraryRegion';
import { WorkspacePageFrame, type WorkspacePageStatusBadge } from './shared/WorkspacePageFrame';

export type MediaAsset = {
    id: number;
    altText: string;
    slot: string;
    status: string;
};

type MediaPageProps = {
    workspaceId?: number;
};

/**
 * Real intake surface: GET on mount, multipart POST to upload, DELETE to
 * remove an own quarantined asset — same-origin credentials throughout, CSRF
 * bootstrapped before every state-changing request (S1-WP03a).
 */
export function MediaPage({ workspaceId }: MediaPageProps) {
    const [assets, setAssets] = useState<MediaAsset[]>([]);
    const [loadState, setLoadState] = useState<MediaLibraryLoadState>('loading');
    const [pendingDeleteIds, setPendingDeleteIds] = useState<Set<number>>(new Set());
    const [deleteErrorIds, setDeleteErrorIds] = useState<Set<number>>(new Set());
    const [deleteNotice, setDeleteNotice] = useState<string | null>(null);
    const requestSeqRef = useRef(0);
    const endpoint = `/api/workspaces/${workspaceId ?? ''}/media`;

    const loadAssets = useCallback(async () => {
        if (workspaceId === undefined) {
            return;
        }

        const requestId = (requestSeqRef.current += 1);
        setLoadState('loading');

        try {
            const response = await fetch(endpoint, { credentials: 'same-origin' });

            if (requestId !== requestSeqRef.current) {
                return;
            }

            if (!response.ok) {
                setLoadState('error');
                return;
            }

            const body = (await response.json()) as { data?: MediaAsset[]; assets?: MediaAsset[] };

            if (requestId !== requestSeqRef.current) {
                return;
            }

            setAssets(body.data ?? body.assets ?? []);
            setLoadState('idle');
        } catch {
            if (requestId !== requestSeqRef.current) {
                return;
            }

            setLoadState('error');
        }
    }, [endpoint, workspaceId]);

    useEffect(() => {
        queueMicrotask(() => {
            void loadAssets();
        });
    }, [loadAssets]);

    async function handleUpload(formData: FormData) {
        const response = await fetch(endpoint, {
            ...buildAuthRequestInit({ method: 'POST', body: formData }),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Upload failed with status ${response.status}`);
        }

        const body = (await response.json()) as { asset?: MediaAsset } & Partial<MediaAsset>;
        const asset = (body.asset ?? body) as MediaAsset;

        requestSeqRef.current += 1;
        setAssets((current) => [...current, asset]);
        setLoadState('idle');
    }

    async function handleDelete(id: number) {
        if (pendingDeleteIds.has(id)) {
            return;
        }

        setPendingDeleteIds((current) => new Set(current).add(id));
        setDeleteErrorIds((current) => {
            if (!current.has(id)) {
                return current;
            }
            const next = new Set(current);
            next.delete(id);
            return next;
        });
        setDeleteNotice(null);

        try {
            const response = await fetch(`${endpoint}/${id}`, {
                ...buildAuthRequestInit({ method: 'DELETE' }),
                credentials: 'same-origin',
            });

            if (!response.ok) {
                setDeleteErrorIds((current) => new Set(current).add(id));
                return;
            }

            setAssets((current) => current.filter((asset) => asset.id !== id));
            setDeleteNotice(t('workspace.media.library.asset.delete.complete'));
        } catch {
            setDeleteErrorIds((current) => new Set(current).add(id));
        } finally {
            setPendingDeleteIds((current) => {
                const next = new Set(current);
                next.delete(id);
                return next;
            });
        }
    }

    const badges: WorkspacePageStatusBadge[] =
        assets.length > 0
            ? [{ key: 'media-count', status: 'success', label: `#${assets.length}` }]
            : [];

    return (
        <div id="section-media">
            <WorkspacePageFrame
                title={t('workspace.media.heading')}
                description={t('workspace.media.operational.description')}
                badges={badges}
            >
                <MediaUploadRegion onSubmit={handleUpload} />
                <MediaLibraryRegion
                    assets={assets}
                    onDelete={(id) => void handleDelete(id)}
                    loadState={loadState}
                    onRetry={() => void loadAssets()}
                    pendingDeleteIds={pendingDeleteIds}
                    deleteErrorIds={deleteErrorIds}
                    deleteNotice={deleteNotice}
                />

                <AiAssistPanel context={t('workspace.shell.nav.media')} />
            </WorkspacePageFrame>
        </div>
    );
}

export default MediaPage;
