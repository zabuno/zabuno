import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Images, Queue, Resize, UploadSimple } from '@phosphor-icons/react';
import { t } from '../../../i18n/workspace';
import { buildAuthRequestInit } from '../../../lib/csrfHeader';
import { readValidationFailure, ServerRejectedError } from '../../../lib/validationErrors';
import { MediaUploadRegion } from './media/MediaUploadRegion';
import { MediaLibraryRegion, type MediaLibraryLoadState } from './media/MediaLibraryRegion';
import { MediaAuditRegion } from './media/MediaAuditRegion';
import { MediaSizeEngineRegion } from './media/MediaSizeEngineRegion';
import { MediaJobQueueRegion } from './media/MediaJobQueueRegion';
import { MediaQuotaRegion, type MediaQuota } from './media/MediaQuotaRegion';
import { MediaManagerShell, type MediaManagerSection } from './media/MediaManagerShell';
import { MediaFolderRail, type MediaFolderId } from './media/MediaFolderRail';
import { useMediaFolders } from './media/mediaFolders';
import { WorkspacePageFrame } from './shared/WorkspacePageFrame';
import { PanelCard } from './shared/PanelCard';

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
    /** Aynı kiracıda daha eski bir kopya varsa onun kimliği (`docs/49` Faz 3). */
    duplicateOfId?: number | null;
    /** En küçük hazır rendition — yalnız `ready` varlıkta dolu (`docs/49` Faz 4). */
    previewUrl?: string | null;
    usageCount?: number;
    versionCount?: number;
    originalName?: string | null;
    sizeBytes?: number;
    createdAt?: string | null;
    lifecycle?: string;
    /**
     * Dosyanın klasörü (`docs/108` §3 madde 1). Klasör uçları henüz
     * inmediği için bugün hiçbir varlıkta dolu değil; klasör hapları da
     * ancak gerçek klasör listesi geldiğinde çizilir.
     */
    folderId?: number | string | null;
};

export type MediaUsage = {
    entityType: string;
    entityId: number;
    slot: string;
    label: string;
    published: boolean;
};

export type MediaVersion = {
    number: number;
    id: number;
    createdBy: string;
    createdAt: string;
    renditionCount: number;
};

/**
 * Kütüphane bölgesinin sayfadan aldığı eylemler (`docs/49` Faz 4-5). Hepsi
 * kiracı adresine bağlıdır; bölge adres bilmez, yalnız çağırır.
 */
export type MediaLibraryActions = {
    loadUsages: (id: number) => Promise<MediaUsage[]>;
    loadVersions: (id: number) => Promise<MediaVersion[]>;
    reprocess: (id: number) => Promise<void>;
    restoreVersion: (id: number, versionNumber: number) => Promise<void>;
    detach: (id: number) => Promise<void>;
    loadTrash: () => Promise<MediaAsset[]>;
    restoreFromTrash: (id: number) => Promise<void>;
    /** 10 dakikalık imzalı asıl indirme adresi (`docs/49` Faz 6 madde 2). */
    downloadOriginal: (id: number) => Promise<string>;
    /** Alt metni (adı) düzelt — `docs/49` §5.2 re-naming (FF-76). */
    updateAltText: (id: number, altText: string) => Promise<void>;
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
    const [trashRetentionDays, setTrashRetentionDays] = useState(30);
    /*
        Kabuğun durumu SAYFADA durur: arama ve klasör seçimi bölüm
        değiştirince kaybolmaz. Sahip "adana" yazıp Yükle'ye geçtiğinde
        geri döndüğünde aradığı şeyi yeniden yazmak zorunda kalmamalı.
    */
    const [section, setSection] = useState('library');
    const [query, setQuery] = useState('');
    const [folderId, setFolderId] = useState<MediaFolderId | null>(null);
    const folders = useMediaFolders(workspaceId);
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

    /*
        Eylemler `useMemo` ile SABİT: çekmece ve çöp sekmesi bu nesneye
        bağımlılık olarak bakar; her render'da yeni nesne, her render'da
        yeniden yükleme demekti.
    */
    const actions = useMemo<MediaLibraryActions>(() => {
        async function getJson<T>(url: string): Promise<T> {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return (await response.json()) as T;
        }

        async function post(url: string): Promise<Response> {
            const response = await fetch(url, {
                ...buildAuthRequestInit({ method: 'POST' }),
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            return response;
        }

        return {
            loadUsages: async (id) =>
                (await getJson<{ usages: MediaUsage[] }>(`${endpoint}/${id}/usages`)).usages,
            loadVersions: async (id) =>
                (await getJson<{ versions: MediaVersion[] }>(`${endpoint}/${id}/versions`))
                    .versions,
            reprocess: async (id) => {
                await post(`${endpoint}/${id}/reprocess`);
            },
            restoreVersion: async (id, versionNumber) => {
                await post(`${endpoint}/${id}/versions/${versionNumber}/restore`);
            },
            detach: async (id) => {
                await post(`${endpoint}/${id}/detach`);
            },
            loadTrash: async () => {
                const body = await getJson<{ data?: MediaAsset[]; assets?: MediaAsset[] }>(
                    `${endpoint}?trashed=1`,
                );
                return body.data ?? body.assets ?? [];
            },
            restoreFromTrash: async (id) => {
                await post(`${endpoint}/${id}/restore`);
            },
            updateAltText: async (id, altText) => {
                const response = await fetch(`${endpoint}/${id}`, {
                    ...buildAuthRequestInit({
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ altText }),
                    }),
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                setAssets((current) =>
                    current.map((row) => (row.id === id ? { ...row, altText } : row)),
                );
            },
            downloadOriginal: async (id) => {
                const body = (await (await post(`${endpoint}/${id}/download-link`)).json()) as {
                    url?: string;
                };
                if (typeof body.url !== 'string') {
                    throw new Error('no-url');
                }
                return body.url;
            },
        };
    }, [endpoint]);

    const handleQuotaLoaded = useCallback((quota: MediaQuota) => {
        setTrashRetentionDays(quota.trashRetentionDays);
    }, []);

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
            setDeleteNotice(t('workspace.media.library.asset.delete.trashed'));
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

    /*
        BÖLÜMLER: kaynak dokuz bölüm gösteriyor, depoda bugün DÖRDÜ gerçek
        (`docs/108` §2). Var olmayan bir bölüme giden sekme, kullanıcıyı boş
        bir odaya sokar ve "burası ne zaman açılacak?" diye kalıcı bir soru
        işareti bırakır — o yüzden yalnız çalışan bölümler yazılıdır.

        "Boyut motoru" ve "Kuyruk" kiracı adresine bağlıdır: `workspaceId`
        bilinmiyorsa o iki bölüm hiç yazılmaz. Adressiz bir bölüm sekmesi,
        açıldığında yalnız bir hata gösterirdi.
    */
    const sections: MediaManagerSection[] = [
        {
            key: 'library',
            label: t('workspace.media.library.tabs.library'),
            icon: <Images aria-hidden="true" size={18} />,
            content: (
                <PanelCard>
                    <MediaLibraryRegion
                        assets={assets}
                        onDelete={(id) => void handleDelete(id)}
                        loadState={loadState}
                        onRetry={() => void loadAssets()}
                        pendingDeleteIds={pendingDeleteIds}
                        deleteErrorIds={deleteErrorIds}
                        deleteNotice={deleteNotice}
                        actions={workspaceId === undefined ? undefined : actions}
                        trashRetentionDays={trashRetentionDays}
                        query={query}
                        folders={folders}
                        activeFolderId={folderId}
                        onFolderChange={setFolderId}
                    />
                </PanelCard>
            ),
        },
        {
            key: 'upload',
            label: t('workspace.media.upload.button'),
            icon: <UploadSimple aria-hidden="true" size={18} />,
            content: (
                <PanelCard>
                    <MediaUploadRegion onSubmit={handleUpload} />
                </PanelCard>
            ),
        },
    ];

    if (workspaceId !== undefined) {
        sections.push(
            {
                key: 'sizes',
                label: t('workspace.media.engine.tab'),
                icon: <Resize aria-hidden="true" size={18} />,
                content: (
                    <PanelCard>
                        <MediaSizeEngineRegion workspaceId={workspaceId} />
                    </PanelCard>
                ),
            },
            {
                key: 'queue',
                label: t('workspace.media.shell.queue'),
                icon: <Queue aria-hidden="true" size={18} />,
                content: (
                    <PanelCard>
                        <MediaJobQueueRegion workspaceId={workspaceId} />
                    </PanelCard>
                ),
            },
        );
    }

    return (
        <div id="section-media">
            <WorkspacePageFrame
                measure="wide"
                description={t('workspace.media.operational.description')}
            >
                {/*
                    Medya kendi kabuğunu taşır: adı, araması ve bölüm
                    gezintisi kabukta durur (`docs/108` §1). Sayfa başlığı bu
                    yüzden çerçeveden ALINDI — aynı ad iki kez yazılsaydı
                    ekran okuyucu iki ayrı başlık duyururdu.
                */}
                <MediaManagerShell
                    title={t('workspace.media.heading')}
                    sections={sections}
                    activeKey={section}
                    onSelect={setSection}
                    query={query}
                    onQueryChange={setQuery}
                    uploadKey="upload"
                    rail={
                        <MediaFolderRail
                            folders={folders}
                            activeFolderId={folderId}
                            onSelect={setFolderId}
                        >
                            {/*
                                DEPOLAMA ŞERİDİ kaynağın sol sütununda durur.
                                Kota kutusu kendi verisi gelmeden hiç
                                çizilmez; şerit de o zaman boş kalır ve yer
                                kaplamaz.
                            */}
                            {workspaceId === undefined ? null : (
                                <MediaQuotaRegion
                                    workspaceId={workspaceId}
                                    onLoaded={handleQuotaLoaded}
                                />
                            )}
                        </MediaFolderRail>
                    }
                />

                {/*
                    Denetim izi EN ALTTA ve kapalı: günlük iş değildir, bir
                    şey ters gittiğinde açılır (`docs/49` Faz 7 madde 4).
                */}
                {workspaceId === undefined ? null : <MediaAuditRegion workspaceId={workspaceId} />}
            </WorkspacePageFrame>
        </div>
    );
}

export default MediaPage;
