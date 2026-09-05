import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

/**
 * SAHİBİN KENDİ CÜMLESİ — tek istemci yolu (`docs/116` §4, P6).
 *
 * ═══ BU DOSYADA PUANA YAZAN BİR YOL YOKTUR ═══
 *
 * Sunucuda aynı kural bir kontrolle değil, elde olmayan bir bağımlılıkla
 * korunuyor. Burada öyle bir doğal koruma yok — adres bir dizedir — o yüzden
 * kural iki yerde birden duruyor: bu dosyada `/reply` dışında bir adres
 * kurulmaz, ve `noRatingRemoval.guard.test.ts` bunu dondurur.
 *
 * Sahip yanıt verir, puanı kaldıramaz: silinebilen bir ortalama, misafire
 * "bu restoranın seçtiği oyların ortalaması" olarak gösterilir; yani bir
 * ölçüm değil, bir reklam.
 */

/**
 * Yanıtın en uzun hâli — sunucudaki `UpdateRatingReplyController` ile aynı
 * sayı, KOPYA olarak değil AYNA olarak: sunucu 422 döndüğünde ekran da aynı
 * cümleyi kurabilsin diye. Sınırın SAHİBİ sunucudur; buradaki sayı yalnız
 * sahibin cümlesini yazarken kaç karakteri kaldığını görmesi içindir.
 */
export const MAX_REPLY_LENGTH = 600;

export type ReplyResult =
    | { outcome: 'ok' }
    /** Sunucu metni reddetti — boş ya da uzun. Sahibin cümlesi durur. */
    | { outcome: 'rejected'; reason: string }
    | { outcome: 'failed' };

function replyUrl(workspaceId: number, productId: number): string {
    return `/api/workspaces/${String(workspaceId)}/ratings/products/${String(productId)}/reply`;
}

export async function publishRatingReply(
    workspaceId: number,
    productId: number,
    body: string,
): Promise<ReplyResult> {
    return send(workspaceId, productId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ body }),
    });
}

export async function withdrawRatingReply(
    workspaceId: number,
    productId: number,
): Promise<ReplyResult> {
    return send(workspaceId, productId, { method: 'DELETE' });
}

async function send(
    workspaceId: number,
    productId: number,
    init: RequestInit,
): Promise<ReplyResult> {
    try {
        await bootstrapCsrfCookie();

        const response = await fetch(replyUrl(workspaceId, productId), buildAuthRequestInit(init));

        if (response.ok) {
            return { outcome: 'ok' };
        }

        if (response.status === 422) {
            const payload = (await response.json()) as { reason?: string };

            return { outcome: 'rejected', reason: payload.reason ?? 'reply_empty' };
        }

        return { outcome: 'failed' };
    } catch {
        /*
            Buraya yalnız istek KURULAMADIĞINDA düşülür. Sahibin cümlesi
            kutuda olduğu gibi durur; ekran bunu "olmadı, yeniden dene" diye
            söyler ve yazdığını silmez.
        */
        return { outcome: 'failed' };
    }
}
