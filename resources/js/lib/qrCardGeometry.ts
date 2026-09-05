/**
 * BASILACAK KARTIN GEOMETRİSİ — sunucudaki `App\Support\QrDestination\QrCardSvg`
 * ile aynı hesap.
 *
 * Panel v3.1 kanonik kaynağının önizleme paneli taranabilirliği bir temenniyle
 * değil bir ÖLÇÜYLE anlatıyor: *"Kod 88 mm — masadan da, ayaktayken de rahat
 * okunur."* Bu cümlenin yazılabilmesi için ekranın, sunucunun basacağı kodun
 * kaç milimetre olacağını bilmesi gerekir.
 *
 * NEDEN AYNA, NEDEN İSTEK DEĞİL: sahip adım 1'de bir ölçü, adım 3'te bir tema
 * seçerken cümle her tıklamada değişir. Her tıklama için sunucuya bir istek
 * atmak, kırk masalı bir restoranın sahibine kartını seçtirirken onlarca
 * gereksiz tur attırırdı. Ayna ucuzdur — ama ayna olduğu için AYRIŞABİLİR.
 *
 * Bu yüzden iki taraf da AYNI tabloyu sınayan testlerle çakılıdır
 * (`qrCardGeometry.test.ts` ve `tests/Unit/QrDestination/QrCardSvgTest.php`,
 * `codeSizes` sağlayıcısı). Biri kayarsa diğerinin testi kırılır. Tek taraflı
 * bir sayı, sahibin kırk kart bastırmasını sağlayan cümledir.
 */

/** Kartın kenar boşluğu, kısa kenarın oranı olarak (`QrCardSvg::MARGIN_RATIO`). */
const MARGIN_RATIO = 0.08;

/**
 * Kâğıt ve oran ailelerinin dikey yerleşimdeki mm ölçüleri —
 * `App\Domain\QrDestination\CardSize::dimensionsMm()` ile aynı.
 *
 * Tablo burada TEKRAR EDİLİYOR, `QrCardWizard`'dan alınmıyor: `lib/` katmanı
 * bir bileşene bağlanamaz (bağlansaydı bir yardımcı işlev, bir ekranın React
 * ağacını da beraberinde paketlerdi). Ayrışma riski testle kapatılıyor —
 * `qrCardGeometry.test.ts` iki tabloyu karşılaştırır.
 */
export const CARD_GEOMETRY_MM = {
    A3: [297, 420],
    A4: [210, 297],
    A5: [148, 210],
    A6: [105, 148],
    B3: [353, 500],
    B4: [250, 353],
    B5: [176, 250],
    B6: [125, 176],
    '1:2': [75, 150],
    '4:3': [150, 112.5],
    '16:9': [150, 84.4],
} as const satisfies Record<string, readonly [number, number]>;

export type CardGeometrySizeKey = keyof typeof CARD_GEOMETRY_MM;

/** `App\Domain\QrDestination\CardTheme` ile aynı değerler. */
export type CardGeometryThemeKey = 'classic' | 'minimal' | 'banner' | 'frame' | 'dark' | 'signage';

/**
 * PHP'nin `round($value, 2)` davranışı.
 *
 * `Math.round(v * 100) / 100` YETMEZ ve fark sessizdir: 22,275 ikili tabanda
 * tam olarak temsil edilemez ve çarpım 2227,4999999999995 çıkar — JavaScript
 * aşağı, PHP yukarı yuvarlar. Punto ölçüsü bir kuruş kayınca kodun milimetresi
 * de kayar ve ekran, sunucunun basmadığı bir sayıyı yazar.
 *
 * PHP kayan nokta gürültüsünü yuvarlamadan ÖNCE 15 anlamlı basamağa çekerek
 * temizler; burada aynısı yapılır.
 */
function roundLikePhp(value: number): number {
    const cleaned = Number(value.toPrecision(15));
    const scaled = Number((cleaned * 100).toPrecision(15));

    return Math.round(scaled) / 100;
}

/** `QrCardSvg::brandFontSize()`. */
function brandFontSize(shortEdge: number): number {
    return roundLikePhp(shortEdge * 0.075);
}

/** `QrCardSvg::captionFontSize()`. */
function captionFontSize(shortEdge: number): number {
    return roundLikePhp(shortEdge * 0.045);
}

/**
 * KAREKODUN BASILACAĞI GERÇEK ÖLÇÜ, milimetre.
 *
 * `QrCardSvg::codeSideMm()` ile aynı varsayımları taşır ve varsayım İHTİYATLI
 * yöndedir: markanın adının basıldığı kabul edilir. Adı boş olan bir markada
 * gerçek kod bir satır kadar DAHA BÜYÜK çıkar — yani bu hesap asla "okunur"
 * derken okunmayan bir kod üretmez. Logo varlığı geometriyi hiç değiştirmez:
 * logo kutusu ile marka satırı aynı yüksekliktedir.
 */
export function cardCodeSideMm(
    theme: CardGeometryThemeKey,
    size: CardGeometrySizeKey,
    landscape: boolean,
    printsTableName: boolean,
): number {
    const [portraitWidth, portraitHeight] = CARD_GEOMETRY_MM[size];
    const width = landscape ? portraitHeight : portraitWidth;
    const height = landscape ? portraitWidth : portraitHeight;

    const shortEdge = Math.min(width, height);
    const margin = shortEdge * MARGIN_RATIO;

    // Şeritli tasarımda içerik şeridin ALTINDAN başlar ve marka adı şeridin
    // İÇİNDE yazılır: kartın gövdesinden yer almaz.
    const banner = theme === 'banner';
    const headerTop = banner ? Math.max(margin, height * 0.16 + margin * 0.5) : margin;
    const showsBrandName = theme !== 'minimal';
    const top = banner || !showsBrandName ? headerTop : headerTop + brandFontSize(shortEdge) * 1.6;

    const caption = captionFontSize(shortEdge);
    // Başlık HER ZAMAN basılır: sahip kendi cümlesini yazmazsa sunucu misafir
    // alanındaki hazır cümleyi koyar (`ExportQrCardController`).
    const lineCount = printsTableName ? 2 : 1;
    const captionSpace = caption * 2.2 + (lineCount - 1) * caption * 1.5;

    return Math.max(
        0,
        // Karekod kalan alanın tamamını alır ama kartın kısa kenarını asla
        // aşmaz: taşan bir kod, kesildiğinde kenarından kırpılır.
        Math.min(height - margin - captionSpace - top, width - 2 * margin),
    );
}

/**
 * TARANABİLİRLİK EŞİĞİ.
 *
 * 25 mm, ~25 cm okuma mesafesinin 10:1 kuralıyla karşılığıdır — masaya konmuş
 * bir kartın telefon mesafesi. 60 mm ise ayakta duran bir misafirin
 * mesafesidir (kasa yanı, vitrin).
 *
 * Eşikler EKRANIN kendi cümlesini seçer; sunucu bu ölçüyü bir kısıt olarak
 * uygulamaz. Uygulamadığı bir kısıtı "geçti" diye yazmak, sahibin kırk kart
 * bastırmasını sağlayan cümledir — ekran yalnız ÖLÇÜYÜ söyler ve küçük bir kod
 * için uyarır.
 */
export const CODE_READABLE_AT_TABLE_MM = 25;
export const CODE_READABLE_STANDING_MM = 60;
