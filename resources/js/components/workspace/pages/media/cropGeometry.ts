/**
 * Kırpma geometrisi — saf hesap, DOM yok (FF-129).
 *
 * Sunucudaki işleyici (`GdMediaAssetProcessor`) slotun oranına göre MERKEZDEN
 * kırpıyor ve bunu kullanıcıya hiç sormuyordu. Bir yemek fotoğrafında bu
 * masumca bir varsayım değil: 3:1 bir kapak görselinde tabak çoğu zaman
 * merkezde durmaz ve otomatik kırpma yemeği çerçevenin dışında bırakır.
 * Restoran sahibi sonucu ancak yayımladıktan sonra görür.
 *
 * Hesap DOM'dan ayrı durur çünkü asıl kural burada: kırpma çerçevesi
 * kaynağın DIŞINA taşamaz ve slotun en küçük ölçüsünün ALTINA inemez.
 * Sürükleme ve yakınlaştırma bu iki kuralın görünen yüzüdür.
 */
export type Size = { readonly width: number; readonly height: number };

/** Kaynak piksellerinde bir dikdörtgen. */
export type CropRect = {
    readonly x: number;
    readonly y: number;
    readonly width: number;
    readonly height: number;
};

/** `'3:1'`, `'1.91:1'` → 3, 1.91. Tanınmayan girdi `null`. */
export function parseAspect(aspect: string | null | undefined): number | null {
    if (aspect === null || aspect === undefined || aspect === '') return null;

    const [left, right] = aspect.split(':');
    const width = Number(left);
    const height = Number(right);

    if (!Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
        return null;
    }

    return width / height;
}

/**
 * Verilen oranda kaynağa sığan EN BÜYÜK dikdörtgen.
 *
 * Yakınlaştırmanın tabanı budur: `zoom = 1` en geniş çerçevedir, kullanıcı
 * yalnız daraltabilir. Genişletmeye izin vermek, kaynağın dışını —yani boş
 * pikselleri— çerçeveye almak olurdu.
 */
export function largestRectFor(source: Size, ratio: number): Size {
    const sourceRatio = source.width / source.height;

    return sourceRatio > ratio
        ? { width: Math.round(source.height * ratio), height: source.height }
        : { width: source.width, height: Math.round(source.width / ratio) };
}

/**
 * Bu kaynak, bu slot için kırpılarak kullanılabilir mi?
 *
 * Kırpma piksel EKLEMEZ. 800×600 bir fotoğraf 1200×400 isteyen bir slota
 * hiçbir çerçeveyle sığmaz; kullanıcıya kırpma aracı gösterip sonunda
 * "olmadı" demek, emeği boşa harcatmaktır. Bu yüzden araç, imkânsız durumda
 * hiç açılmaz.
 */
export function canCropInto(source: Size, minimum: Size, ratio: number | null): boolean {
    if (source.width < minimum.width || source.height < minimum.height) return false;

    if (ratio === null) return true;

    const largest = largestRectFor(source, ratio);

    return largest.width >= minimum.width && largest.height >= minimum.height;
}

/**
 * Yakınlaştırmanın en büyük değeri — çerçeveyi en küçük ölçünün altına
 * indiren yakınlaştırmaya izin verilmez.
 *
 * `zoom = 1` en geniş, büyük değerler daha dar çerçeve demektir; yani
 * çerçeve genişliği `largest.width / zoom`.
 */
export function maxZoomFor(source: Size, minimum: Size, ratio: number | null): number {
    const largest = ratio === null ? source : largestRectFor(source, ratio);

    // En küçük ölçü İSTEMEYEN slot, yakınlaştırmayı KISITLAMAZ — bu yüzden
    // sınır sonsuzdur, 1 değil. İlk hâlinde 1 yazıyordu ve sonuç ters
    // çıkıyordu: kısıtı olmayan slotta hiç yakınlaştırılamıyordu.
    const byWidth = minimum.width > 0 ? largest.width / minimum.width : Infinity;
    const byHeight = minimum.height > 0 ? largest.height / minimum.height : Infinity;

    // Bir slot en küçük ölçü istemiyorsa bile sınırsız yakınlaştırma yoktur:
    // birkaç piksellik bir çerçeve teknik olarak geçerli, ürün olarak
    // anlamsızdır.
    return Math.max(1, Math.min(byWidth, byHeight, 8));
}

/**
 * Çerçeveyi kaynağın içinde tutar.
 *
 * `offset` çerçevenin merkezinin kaynağın merkezine göre kayması; -1 ile 1
 * arasında normalize edilir ki yakınlaştırma değişince kullanıcının seçtiği
 * yer ORANTILI kalsın — piksel tutulsaydı, yakınlaştırma her değiştiğinde
 * çerçeve sıçrardı.
 */
export function cropRectFor(
    source: Size,
    ratio: number | null,
    zoom: number,
    offset: { readonly x: number; readonly y: number },
): CropRect {
    const largest = ratio === null ? source : largestRectFor(source, ratio);
    const safeZoom = Math.max(1, zoom);

    const width = Math.max(1, Math.round(largest.width / safeZoom));
    const height = Math.max(1, Math.round(largest.height / safeZoom));

    const slackX = source.width - width;
    const slackY = source.height - height;

    const clamp = (value: number) => Math.min(1, Math.max(-1, value));

    return {
        x: Math.round((slackX / 2) * (1 + clamp(offset.x))),
        y: Math.round((slackY / 2) * (1 + clamp(offset.y))),
        width,
        height,
    };
}
