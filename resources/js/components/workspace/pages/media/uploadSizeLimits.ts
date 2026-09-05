/**
 * Tür bazlı yükleme sınırları — İSTEMCİ tarafı (FF-158).
 *
 * Sunucu artık tek düz bir sayı değil, TÜRE göre sınır bildiriyor
 * (`GET /api/media/slot-policies` → `limits.maxBytesByKind`). Bu dosya o
 * sözlüğü okur; sayı ÜRETMEZ.
 *
 * Neden ayrı bir modül: bileşenin hem bileşen hem yardımcı yayımlaması hızlı
 * yenilemeyi bozar; ayrıca bu eşleme tablo ve form tarafından ORTAK
 * kullanılır ve ikisinin aynı cevabı vermesi şart — biri "25 MB" yazarken
 * diğerinin 2 MB'ta reddetmesi, sahibin ekrandaki söze güvenini bitirir.
 *
 * SUNUCU SON SÖZÜ SÖYLER. Buradaki kontrol bir kolaylıktır: sahip 40 MB'lık
 * bir dosyayı telefon hattından geçirdikten SONRA öğrenmesin diye
 * (`docs/47`: istemci yalnız hızlı yardım).
 */

export type UploadLimitKind = 'image' | 'vector' | 'document';

export type UploadLimits = {
    /**
     * MUTLAK TAVAN — türü tanınmayan bir dosyanın tutunacağı sayı.
     *
     * Bir tür sınırının yerine geçmez; yalnız sözlükte karşılığı
     * bulunmayan durumda kullanılır.
     */
    maxBytes: number;
    /**
     * Tür → bayt. Eski bir sunucu bunu hiç göndermeyebilir; o durumda
     * ekran `maxBytes`e düşer ve eskisi gibi davranır — yanlış bir sayı
     * uydurmaz.
     */
    maxBytesByKind?: Record<string, number>;
    maxMegapixels: number;
};

/**
 * Bir dosya, sınır bakımından hangi türden?
 *
 * Ölçüt MIME, o yoksa uzantıdır. İkisi de tarayıcının/dosya adının
 * söylediğidir ve YANILTICI olabilir — bu yüzden buradaki cevap yalnız
 * ekranda ne yazılacağını belirler. Gerçek kararı sunucu dosyanın KENDİ
 * baytlarına bakarak verir (`StoreMediaRequest`).
 */
export function limitKindOfFile(file: File): UploadLimitKind {
    const type = file.type.toLowerCase();

    if (type === 'image/svg+xml' || file.name.toLowerCase().endsWith('.svg')) {
        return 'vector';
    }

    if (type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
        return 'document';
    }

    return 'image';
}

/**
 * Slot politikasındaki biçim adı (`jpeg`, `svg`, `pdf`) → tür.
 *
 * Tanınmayan biçimde `null`: uydurulmuş bir tür, uydurulmuş bir sınır
 * demektir.
 */
export function limitKindOfFormat(format: string): UploadLimitKind | null {
    switch (format.trim().toLowerCase().replace(/^\./, '')) {
        case 'jpeg':
        case 'jpg':
        case 'png':
        case 'gif':
        case 'webp':
        case 'avif':
        case 'heic':
        case 'heif':
        case 'tiff':
        case 'tif':
            return 'image';
        case 'svg':
            return 'vector';
        case 'pdf':
            return 'document';
        default:
            return null;
    }
}

/** Bu tür için sunucunun bildirdiği sınır; bildirmediyse mutlak tavan. */
export function maxBytesForKind(limits: UploadLimits, kind: UploadLimitKind): number {
    const declared = limits.maxBytesByKind?.[kind];

    return typeof declared === 'number' && Number.isFinite(declared) ? declared : limits.maxBytes;
}
