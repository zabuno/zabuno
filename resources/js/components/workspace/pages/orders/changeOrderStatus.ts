import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

/**
 * Durum değişikliğinin TEK istemci yolu — `docs/115` S4/S5 (FF-179).
 *
 * Garson kuyruğu ve mutfak monitörü aynı ucu çağırır. İkisi kendi `fetch`
 * çağrısını yazsaydı, birinde 409 çakışması ele alınır ötekinde alınmazdı;
 * ve fark ancak iki kişinin aynı siparişe aynı anda dokunduğu akşam ortaya
 * çıkardı.
 */
export type OrderActionResult =
    | { outcome: 'ok'; status: string }
    /**
     * BAŞKASI ÖNCE DAVRANDI (`docs/115` G5).
     *
     * Bir hata değil: istek geçerliydi, iş çoktan alınmıştı. Sunucu
     * siparişin O ANKİ durumunu gönderir ki ekran listeyi tazelemeden
     * doğru cümleyi kurabilsin.
     */
    | { outcome: 'conflict'; status: string }
    | { outcome: 'failed' };

export async function changeOrderStatus(
    workspaceId: number,
    locationId: number,
    orderId: number,
    status: 'confirmed' | 'rejected' | 'preparing' | 'ready' | 'delivered',
    reason?: string,
): Promise<OrderActionResult> {
    try {
        await bootstrapCsrfCookie();

        const response = await fetch(
            `/api/workspaces/${String(workspaceId)}/locations/${String(locationId)}/orders/${String(orderId)}/status`,
            buildAuthRequestInit({
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(reason === undefined ? { status } : { status, reason }),
            }),
        );

        if (response.ok) {
            const body = (await response.json()) as { status?: string };

            return { outcome: 'ok', status: body.status ?? status };
        }

        if (response.status === 409) {
            const body = (await response.json()) as { status?: string };

            return { outcome: 'conflict', status: body.status ?? status };
        }

        return { outcome: 'failed' };
    } catch {
        // Buraya yalnız istek KURULAMADIĞINDA düşülür. Sipariş sunucuda
        // olduğu gibi durur; ekran bunu "olmadı, yeniden dene" diye söyler.
        return { outcome: 'failed' };
    }
}
