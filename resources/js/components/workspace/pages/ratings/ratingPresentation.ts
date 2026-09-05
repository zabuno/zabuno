import { t } from '../../../../i18n/workspace';

/**
 * Puan satırının ekrandaki hâli — `docs/116` §3 (P5).
 *
 * Karar mantığı bu dosyada TOPLANIYOR, bileşenin içine dağılmıyor: "eşik
 * altında sayı yok" kuralı bir `if` olarak çizim kodunun arasında dursaydı,
 * ikinci bir yüzey (yarın bir özet kartı, bir dışa aktarım) onu kopyalar ve
 * kopyanın biri bir gün unuturdu.
 */

export type RatingRow = {
    menuItemId: number;
    productId: number;
    productName: string;
    /** Eşik altında sunucu ZATEN `null` gönderir; ekran onu sıfıra çevirmez. */
    score: number | null;
    scaleMax: number;
    signalCount: number;
    meetsDisplayThreshold: boolean;
    computedAt: string | null;
    reply: { body: string; publishedAt: string | null } | null;
};

/**
 * Satırın sayısı — ya bir puan, ya bilinmezliğin adı.
 *
 * ÜÇÜNCÜ BİR SEÇENEK YOK ve olmamalı: hiçbir şey yazmamak da bir cevap
 * değildir. Sahip "bu ürün puanlanmıyor mu, yoksa kötü mü?" diye sorar ve
 * boş bir yer o soruyu cevaplamaz.
 */
export function scoreLabel(row: RatingRow): string {
    if (!row.meetsDisplayThreshold || row.score === null) {
        return t('workspace.ratings.notEnough');
    }

    return t('workspace.ratings.score', {
        score: row.score.toFixed(1),
        max: String(row.scaleMax),
    });
}

export function hasScore(row: RatingRow): boolean {
    return row.meetsDisplayThreshold && row.score !== null;
}

/**
 * Bir anın okunur hâli — UYDURULMAZ.
 *
 * Tarih gelmediyse `null` döner ve çağıran "henüz hesaplanmadı" der. Bugünün
 * tarihini basmak, çalışmamış bir hesabı çalışmış göstermek olurdu ve
 * donmuş bir ekranla dolu bir ekran zaten aynı görünüyor.
 */
export function formatMoment(iso: string | null): string | null {
    if (iso === null || iso === '') {
        return null;
    }

    const at = new Date(iso);

    if (Number.isNaN(at.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(at);
}

/** "Bu sayı ne kadar eski?" — Ö3'ün ikinci yarısı. */
export function computedAtLabel(row: RatingRow): string {
    const moment = formatMoment(row.computedAt);

    return moment === null
        ? t('workspace.ratings.computedAt.never')
        : t('workspace.ratings.computedAt', { time: moment });
}
