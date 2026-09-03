import { useCallback, useEffect, useRef, useState } from 'react';
import { t } from '../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../lib/csrfHeader';
import { readValidationFailure, ServerRejectedError } from '../../../lib/validationErrors';
import { MediaUploadRegion } from './media/MediaUploadRegion';
import { MediaLibraryRegion, type MediaLibraryLoadState } from './media/MediaLibraryRegion';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';

export type MediaAsset = {
    id: number;
    altText: string;
    slot: string;
    status: string;
    /**
     * Durumun İNSANCA açıklaması; yalnız bir şey ters gittiğinde dolu
     * (`docs/76`). Sorunsuz bir dosyaya sebep yazmak gürültüdür ve sahip
     * her satırda açıklama görmeye başlarsa gerçek uyarıyı okumaz.
     */
    statusReason?: string | null;
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

    /**
     * Yükleme `XMLHttpRequest` ile — `fetch` gönderim ilerlemesini
     * BİLDİRMEZ. Telefonda 8 MB'lık bir fotoğrafta kullanıcı otuz saniye
     * boyunca hiçbir şey görmüyordu (`docs/49` Faz 2 madde 2).
     *
     * `idempotencyKey` yeniden denemede AYNI kalır: bağlantı koptuysa sunucu
     * ikinci gönderimi ikinci bir görsel sanmaz, ilkini döner (FF-68).
     */
    async function handleUpload(
        formData: FormData,
        options: { idempotencyKey: string; onProgress: (fraction: number) => void },
    ) {
        const init = buildAuthRequestInit({ method: 'POST' });
        const headers = init.headers as Headers;

        const response = await new Promise<Response>((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint);
            // Aynı kökten istek: çerezler zaten gider (eski `same-origin` ile aynı).
            headers.forEach((value, name) => xhr.setRequestHeader(name, value));
            xhr.setRequestHeader('X-Idempotency-Key', options.idempotencyKey);
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable && event.total > 0) {
                    options.onProgress(event.loaded / event.total);
                }
            };
            xhr.onerror = () => reject(new Error('network'));
            xhr.onabort = () => reject(new Error('aborted'));
            xhr.onload = () => {
                resolve(
                    new Response(xhr.responseText, {
                        status: xhr.status,
                        headers: {
                            'Content-Type':
                                xhr.getResponseHeader('Content-Type') ?? 'application/json',
                        },
                    }),
                );
            };
            xhr.send(formData);
        });

        if (!response.ok) {
            // Durum kodunu metne çevirip gövdeyi atmak, sunucunun söylediği
            // tek yararlı şeyi kaybeder. 50 MB sınırını aşan bir yüklemede
            // kullanıcının gördüğü "Upload failed with status 413" oluyordu;
            // oysa sunucu hangi dosyanın ne kadar büyük olduğunu söylüyor.
            const failure = await readValidationFailure(
                response,
                t('workspace.media.upload.failed'),
            );

            throw new ServerRejectedError(
                failure.fields.file ?? failure.message ?? t('workspace.media.upload.failed'),
            );
        }

        const body = (await response.json()) as {
            asset?: MediaAsset;
            replayed?: boolean;
        } & Partial<MediaAsset>;
        const asset = (body.asset ?? body) as MediaAsset;

        requestSeqRef.current += 1;
        // Yeniden denemenin tekrar oynatılmış cevabı listede zaten olabilir;
        // aynı kimliği iki kez göstermeyiz.
        setAssets((current) =>
            current.some((row) => row.id === asset.id) ? current : [...current, asset],
        );
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

    return (
        <div id="section-media">
            <WorkspacePageFrame
                measure="wide"
                title={t('workspace.media.heading')}
                description={t('workspace.media.operational.description')}
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
            </WorkspacePageFrame>
        </div>
    );
}

export default MediaPage;
