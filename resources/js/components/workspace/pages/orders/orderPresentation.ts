import { t } from '../../../../i18n/workspace';
import { formatMoneyOr } from '../../../../money/format';
import type { OrderFeedRow } from './useOrderFeed';

/**
 * Sipariş ekranlarının ORTAK dili — garson kuyruğu, mutfak monitörü, geçmiş.
 *
 * Üç ekran da aynı satırı gösterir; durum adını ya da bekleme süresini her
 * birinin kendi başına hesaplaması, aynı siparişin üç farklı cümleyle
 * anlatılması demekti. Servis anında iki ekranın farklı şey söylemesi,
 * hiçbirinin söylemediği kadar zararlıdır.
 */

const STATUS_LABEL: Record<string, Parameters<typeof t>[0]> = {
    pending: 'workspace.orders.status.pending',
    confirmed: 'workspace.orders.status.confirmed',
    preparing: 'workspace.orders.status.preparing',
    ready: 'workspace.orders.status.ready',
    delivered: 'workspace.orders.status.delivered',
    cancelled: 'workspace.orders.status.cancelled',
    rejected: 'workspace.orders.status.rejected',
};

/**
 * Sunucunun durum anahtarından ekrandaki cümleye.
 *
 * Tanınmayan bir anahtar HAM HÂLİYLE yazılır, uydurulmaz: ekranda
 * "confirmed" görmek kafa karıştırır ama yanlış çevrilmiş bir durum
 * yanlış davranış üretir.
 */
export function orderStatusLabel(status: string): string {
    const key = STATUS_LABEL[status];

    return key === undefined ? status : t(key);
}

/**
 * Siparişin kaç dakikadır beklediği — SUNUCUNUN saatiyle.
 *
 * `clockOffsetMs`, sunucunun anı ile bu ekranın saati arasındaki farktır.
 * Mutfak duvarındaki ekranın saati genellikle yanlıştır; fark eklenmeseydi,
 * ekran kendi hatasını misafirin bekleme süresi diye gösterirdi — ve o sayı
 * garsonun hangi masaya önce gideceğini belirliyor.
 *
 * Negatif değer sıfıra kırpılır: "eksi iki dakikadır bekliyor" cümlesi
 * ekranda hiçbir işe yaramaz.
 */
export function waitingMinutes(
    placedAtIso: string,
    clockOffsetMs: number,
    now: number = Date.now(),
): number {
    const placedAt = new Date(placedAtIso).getTime();

    if (Number.isNaN(placedAt)) {
        return 0;
    }

    return Math.max(0, Math.floor((now + clockOffsetMs - placedAt) / 60_000));
}

/** "Waiting 9 min" ya da yeni gelmişse "Just arrived". */
export function waitingLabel(minutes: number): string {
    return minutes < 1
        ? t('workspace.orders.queue.waiting.justNow')
        : t('workspace.orders.queue.waiting', { minutes: String(minutes) });
}

/**
 * Son güncelleme anı — ŞUBENİN duvar saatiyle.
 *
 * Dilim siparişten gelir; sabit bir dilim yazmak, Berlin şubesinde
 * sunucunun 03:00'ünü ekranda 04:00 gösteren hatanın aynısını üretirdi
 * (`docs/62`, `PublishScheduleRegion`).
 */
export function updatedAtLabel(at: Date | null, timeZone: string | null): string {
    if (at === null) {
        return t('workspace.orders.updated.never');
    }

    const options: Intl.DateTimeFormatOptions = {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    };

    if (timeZone !== null && timeZone !== '') {
        options.timeZone = timeZone;
    }

    let formatted: string;

    try {
        formatted = new Intl.DateTimeFormat(undefined, options).format(at);
    } catch {
        // Tanınmayan bir dilim geldiğinde okuyanın kendi saatine düşülür;
        // saatin hiç yazılmaması, ekranın donduğunu gizlerdi.
        formatted = new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }).format(at);
    }

    return t('workspace.orders.updated', { time: formatted });
}

/** Satırın tutarı; biçimlendirilemezse sayı uydurulmaz. */
export function lineTotal(minorAmount: number, currencyCode: string): string {
    return formatMoneyOr(minorAmount, currencyCode, t('workspace.orders.price.unavailable'));
}

/** Bu siparişin bütün alerjenleri, tekrarsız — başlıkta tek bakışta okunur. */
export function orderAllergens(order: OrderFeedRow): string[] {
    const seen = new Set<string>();

    for (const line of order.lines) {
        for (const allergen of line.allergens) {
            seen.add(allergen);
        }
    }

    return [...seen];
}
