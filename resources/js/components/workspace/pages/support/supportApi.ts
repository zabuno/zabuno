import { bootstrapCsrfCookie, buildAuthRequestInit } from '../../../../lib/csrfHeader';

export type SupportRequestStatus = 'received' | 'answered' | 'closed';

export type SupportRequestRow = {
    id: number;
    reference: string;
    subject: string;
    status: SupportRequestStatus | string;
    received_at: string;
    first_response_at: string | null;
};

/**
 * Yanıt taahhüdü SUNUCUDAN gelir (`docs/125` §3). `null` = yapılandırılmamış
 * = ekran hiçbir şey yazmaz. Cümle hazır gelir; ekran `{hours}` doldurmaz,
 * çünkü cümlenin kaynağı iletişim sayfasıyla ve e-postayla aynıdır.
 */
export type SupportCommitment = { hours: number; sentence: string } | null;

export type SupportListResponse = {
    requests: SupportRequestRow[];
    commitment: SupportCommitment;
};

/**
 * Alındı e-postasının hâli — `sent` bir söz değildir (taşıyıcı devraldı),
 * `failed` denendi ve düştü, `unknown` hiç denenmedi (taşıyıcı yok).
 */
export type SupportAcknowledgement = 'sent' | 'failed' | 'unknown';

export type SupportCreateResponse = SupportRequestRow & {
    acknowledgement: SupportAcknowledgement;
};

export type SupportApi = {
    list(): Promise<SupportListResponse>;
    create(subject: string, message: string): Promise<SupportCreateResponse>;
};

/**
 * Sayfanın sunucuyla konuşan tek yeri.
 *
 * Ekran bu arayüzü PROP olarak alır: hikâye ve test sahtesini geçirir,
 * ürün varsayılanı kullanır. `window.fetch`'i hikâyede ezmek yerine
 * arayüzü değiştirmek, "ekran neyi çağırıyor" sorusunu tek dosyada
 * cevaplar ve sahte ile gerçeğin aynı biçimi paylaşmasını zorlar.
 */
export function createSupportApi(workspaceId: number): SupportApi {
    const base = `/api/workspaces/${workspaceId}/support-requests`;

    return {
        async list(): Promise<SupportListResponse> {
            const response = await fetch(base, { credentials: 'same-origin' });

            if (!response.ok) {
                throw new Error(`support list failed: ${response.status}`);
            }

            return (await response.json()) as SupportListResponse;
        },

        async create(subject: string, message: string): Promise<SupportCreateResponse> {
            await bootstrapCsrfCookie();

            const requestInit = buildAuthRequestInit({
                method: 'POST',
                body: JSON.stringify({ subject, message }),
            });
            const headers = new Headers(requestInit.headers);
            headers.set('Content-Type', 'application/json');

            const response = await fetch(base, {
                ...requestInit,
                credentials: 'same-origin',
                headers,
            });

            if (!response.ok) {
                throw new Error(`support create failed: ${response.status}`);
            }

            return (await response.json()) as SupportCreateResponse;
        },
    };
}
