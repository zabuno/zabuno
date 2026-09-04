import { useEffect, useState } from 'react';
import type { MediaFolder } from './MediaFolderRail';

function isFolder(value: unknown): value is MediaFolder {
    if (typeof value !== 'object' || value === null) return false;
    const row = value as Record<string, unknown>;
    const idOk = typeof row.id === 'number' || typeof row.id === 'string';
    return idOk && typeof row.name === 'string';
}

/**
 * KLASÖRLER — uç yoksa bölüm HİÇ ÇİZİLMEZ.
 *
 * Klasör uçları bu depoya henüz inmedi (`docs/108` §2: "Kütüphane … klasör
 * yok"). Uç 404 dönerse burada bir hata mesajı GÖSTERİLMEZ: sahibin
 * yapabileceği hiçbir şey yoktur ve ekranda kalıcı bir kırmızı satır
 * bırakmak, gerçek hataları da görünmez kılar. Liste boş kalır, kabuk da
 * klasör şeridini çizmez.
 *
 * Aynı sebeple uydurma bir "Genel" klasörü de yoktur: sahibi olmayan bir
 * yere tıklatmak, olmayan bir özelliği varmış gibi göstermektir.
 */
export function useMediaFolders(workspaceId?: number): MediaFolder[] {
    const [folders, setFolders] = useState<MediaFolder[]>([]);

    useEffect(() => {
        if (workspaceId === undefined) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(`/api/workspaces/${workspaceId}/media/folders`, {
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const body = (await response.json()) as { folders?: unknown; data?: unknown };
                const raw = Array.isArray(body.folders)
                    ? body.folders
                    : Array.isArray(body.data)
                      ? body.data
                      : [];

                if (!cancelled) {
                    setFolders(raw.filter(isFolder));
                }
            } catch {
                // Sessiz: klasör olmadan kütüphane çalışmaya devam eder.
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [workspaceId]);

    return folders;
}
