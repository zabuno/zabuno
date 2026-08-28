import { useEffect, useState } from 'react';
import type { CurrentPublication } from '../publication/PublicationStatusRegion';

export type CurrentPublicationState = {
    current: CurrentPublication | null;
    loading: boolean;
    loadError: boolean;
};

/**
 * Menünün YAYINDAKİ sürümünü okur.
 *
 * Aynı sorguyu iki sayfa da soruyor: yayınlama ekranı ve QR kodları ekranı.
 * İkisine ayrı ayrı yazmak, ikisinin ayrışacağı bir gün yaratırdı — biri
 * 404'ü "yayın yok" sayarken diğeri hata sayabilir ve QR ekranı, yayınlama
 * ekranının "yayında" dediği bir menü için "önce yayınlayın" diyebilirdi.
 *
 * 404 bir HATA DEĞİLDİR: menü henüz yayınlanmamış demektir ve bu normal bir
 * başlangıç durumudur. Hata olarak sunmak, kullanıcıya bozulmuş bir şey
 * varmış gibi gösterirdi.
 */
export function useCurrentPublication(
    workspaceId: number | undefined,
    menuId: number | null,
): CurrentPublicationState {
    const enabled = workspaceId !== undefined && menuId !== null;
    const requestKey = enabled ? `${String(workspaceId)}:${String(menuId)}` : '';

    const [resolved, setResolved] = useState<{
        key: string;
        current: CurrentPublication | null;
        loadError: boolean;
    } | null>(null);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let cancelled = false;

        (async () => {
            try {
                const response = await fetch(
                    `/api/workspaces/${String(workspaceId)}/menu/${String(menuId)}/publications/current`,
                    { credentials: 'include', headers: { Accept: 'application/json' } },
                );

                if (cancelled) return;

                if (response.ok) {
                    setResolved({
                        key: requestKey,
                        current: (await response.json()) as CurrentPublication,
                        loadError: false,
                    });
                } else if (response.status === 404) {
                    setResolved({ key: requestKey, current: null, loadError: false });
                } else {
                    setResolved({ key: requestKey, current: null, loadError: true });
                }
            } catch {
                if (!cancelled) {
                    setResolved({ key: requestKey, current: null, loadError: true });
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [enabled, workspaceId, menuId, requestKey]);

    /*
        `loading` TÜRETİLİR, efekt içinde ayarlanmaz.

        Efektin başında `setLoading(true)` çağırmak fazladan bir render turu
        üretir ve React derleyicisi bunu zincirleme render olarak işaretler.
        Oysa "yükleniyor" bağımsız bir bilgi değil: elimizdeki cevabın ŞU ANKİ
        isteğe ait olup olmadığıdır. Anahtarı karşılaştırmak hem doğru hem
        bedava — ve istek parametreleri değişince kendiliğinden yeniden
        "yükleniyor" olur.
    */
    return {
        current: resolved?.key === requestKey ? resolved.current : null,
        loading: enabled && resolved?.key !== requestKey,
        loadError: resolved?.key === requestKey ? resolved.loadError : false,
    };
}
