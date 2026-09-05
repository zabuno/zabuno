import { t } from '../../../../i18n/workspace';
import {
    cardCodeSideMm,
    CARD_GEOMETRY_MM,
    type CardGeometrySizeKey,
    type CardGeometryThemeKey,
} from '../../../../lib/qrCardGeometry';
import type { QrScreenCode } from '../publication/QrTableCardGrid';

/**
 * BASKI PLANI — panel v3.1 kanonik kaynağı (`docs/reference/panel-v3/
 * panel-v3.1.dc.html`, QR bölümü).
 *
 * Kaynağın ekranı bir ayar paneli değil ÜÇ SORUDUR ve sırası sahibin
 * kafasındaki sıradır: *ne basacaksın* → *hangi masalar* → *nasıl görünsün*.
 * Bu dosya o üç sorunun tek cevabıdır; ekranın üç bölümü de aynı nesneyi
 * okur ve alt çubuk onu tek cümleye çevirir.
 *
 * NEDEN TEK NESNE: eski ekranda kart tasarımı üç ayrı yerde seçiliyordu
 * (seçili kod paneli, kart sihirbazı, ham kod teması) ve üçü ayrı durum
 * tutuyordu. Sahip solda "markalı" seçip sağdan klasik bir kart indirebiliyor
 * ve farkı ancak yazıcıdan kâğıt çıkınca görüyordu.
 *
 * FİZİKSEL NESNE SEÇİLİR, kâğıt boyutu bunun SONUCUDUR (kaynağın kendi
 * cümlesi): kullanıcının kararı "A6 mı B5 mi" değil, "masaya mı duvara mı".
 */

export type QrPrintPresetKey = 'table' | 'large' | 'wall' | 'window';

/** Kimin basılacağı: hepsi, bir salon bölümü, ya da tek masa. */
export type QrPrintScopeKey = 'all' | 'area' | 'one';

/**
 * Dosya biçimi.
 *
 * PNG YOK ve bu bir eksiklik değil bir karar (`ExportQrCardController`): raster
 * bir görsel 4 cm'lik bir karekodda modül kenarlarını bulanıklaştırır ve PNG'yi
 * ayrı bir bestecinin çizmesi gerekirdi — iki besteci bir gün iki farklı kart
 * üretir. Kaynak üçüncü bir seçenek gösteriyor; DEPODA KARŞILIĞI YOK, o yüzden
 * çizilmiyor ve sebebi ekranda yazıyor.
 */
export type QrPrintFormat = 'pdf' | 'svg';

export type QrPrintPlan = {
    preset: QrPrintPresetKey;
    /** Sahip "başka bir ölçü gerekiyor" dedi mi? Dediyse hazır ölçü düşer. */
    custom: boolean;
    size: CardGeometrySizeKey;
    landscape: boolean;
    format: QrPrintFormat;
    scope: QrPrintScopeKey;
    areaId: number | null;
    codeId: number | null;
    theme: CardGeometryThemeKey;
    /** Boşsa sunucu misafir alanındaki hazır cümleyi basar; uydurulmaz. */
    headline: string;
};

type PresetDefinition = {
    key: QrPrintPresetKey;
    size: CardGeometrySizeKey;
    labelKey: Parameters<typeof t>[0];
    whereKey: Parameters<typeof t>[0];
    /** Yalnız birinde var: "en çok kullanılan". */
    tag: boolean;
};

/**
 * Kaynağın dört hazır çıktısı, kaynağın sırasıyla.
 *
 * Ölçüler kaynağın kendi eşlemesidir (masa kartı A6, büyük masa kartı A5,
 * duvar afişi A4, vitrin A3) ve hepsi DİKEY başlar. Yön kaynağın presetlerinde
 * de dikeydir; yatay isteyen sahip "başka bir ölçü gerekiyor" altında bulur.
 */
export const QR_PRINT_PRESETS: readonly PresetDefinition[] = [
    {
        key: 'table',
        size: 'A6',
        labelKey: 'workspace.publication.qrScreen.preset.table',
        whereKey: 'workspace.publication.qrScreen.preset.table.where',
        tag: true,
    },
    {
        key: 'large',
        size: 'A5',
        labelKey: 'workspace.publication.qrScreen.preset.large',
        whereKey: 'workspace.publication.qrScreen.preset.large.where',
        tag: false,
    },
    {
        key: 'wall',
        size: 'A4',
        labelKey: 'workspace.publication.qrScreen.preset.wall',
        whereKey: 'workspace.publication.qrScreen.preset.wall.where',
        tag: false,
    },
    {
        key: 'window',
        size: 'A3',
        labelKey: 'workspace.publication.qrScreen.preset.window',
        whereKey: 'workspace.publication.qrScreen.preset.window.where',
        tag: false,
    },
];

/** Kâğıt ailesi: standart bir sayfaya basılır. */
export const QR_PAPER_SIZES = ['A3', 'A4', 'A5', 'A6', 'B3', 'B4', 'B5', 'B6'] as const;

/** Kart ailesi: standın kendi oranı; bir kâğıt boyuna karşılık gelmez. */
export const QR_RATIO_SIZES = ['1:2', '4:3', '16:9'] as const;

export const QR_RATIO_USE_KEYS: Record<(typeof QR_RATIO_SIZES)[number], Parameters<typeof t>[0]> = {
    '1:2': 'workspace.publication.qrScreen.custom.ratioUse.tall',
    '4:3': 'workspace.publication.qrScreen.custom.ratioUse.classic',
    '16:9': 'workspace.publication.qrScreen.custom.ratioUse.screen',
};

/**
 * Kaynağın beş tasarımı, kaynağın sırasıyla: Sade · Çerçeve · Markalı · Koyu ·
 * Tabela.
 *
 * `minimal` bu şeritte YOK — kaynağın listesinde de yok. Silinmedi: sunucuda
 * duruyor ve gelişmiş baskıdaki kart sihirbazından hâlâ seçilebiliyor. Bir
 * yetenek, kaynağın ekranında görünmüyor diye yok edilmez.
 */
export const QR_CARD_THEMES: readonly {
    key: CardGeometryThemeKey;
    labelKey: Parameters<typeof t>[0];
}[] = [
    { key: 'classic', labelKey: 'workspace.publication.qrScreen.cardTheme.plain' },
    { key: 'frame', labelKey: 'workspace.publication.qrScreen.cardTheme.framed' },
    { key: 'banner', labelKey: 'workspace.publication.qrScreen.cardTheme.branded' },
    { key: 'dark', labelKey: 'workspace.publication.qrScreen.cardTheme.dark' },
    { key: 'signage', labelKey: 'workspace.publication.qrScreen.cardTheme.signage' },
];

/**
 * Sunucu tek istekte en fazla bu kadar kart basar
 * (`App\Domain\QrDestination\QrPrintSheet::CARDS_PER_REQUEST`). Sayı burada da
 * biliniyor ki ekran sessizce kırpılmış bir arşiv vermek yerine sınırı
 * SÖYLESİN.
 */
export const CARDS_PER_REQUEST = 48;

export const INITIAL_QR_PRINT_PLAN: QrPrintPlan = {
    preset: 'table',
    custom: false,
    size: 'A6',
    landscape: false,
    format: 'pdf',
    scope: 'all',
    areaId: null,
    codeId: null,
    theme: 'classic',
    headline: '',
};

/**
 * Kodun İNSAN ADI.
 *
 * Masaya bağlı olmayan kod "giriş kodu"dur; 43 karakterlik token bir ad
 * değildir — sahip onunla hiçbir masayı bulamaz.
 */
export function codeName(code: QrScreenCode): string {
    if (code.tableName) {
        return code.areaLabel ? `${code.tableName} · ${code.areaLabel}` : code.tableName;
    }

    return t('workspace.publication.qrDestination.item.entrance');
}

/** Salonun bölümleri, KODLARIN kendisinden türetilir. */
export type QrPrintArea = { id: number; label: string; count: number };

/**
 * Bölümler ikinci bir istekle değil, ZATEN YÜKLÜ listeden çıkarılır.
 *
 * Kimlikle taşınır, etiketle değil: iki bölüm aynı adı taşıyabilir ("Bahçe"
 * iki katta da olabilir) ve o gün süzgeç sessizce yanlış kartları basardı —
 * sunucunun arşiv ucu da `areaId` ile süzüyor (`ExportQrCardsZipController`).
 *
 * Masası olmayan bir bölüm burada hiç görünmez ve görünmemeli: basılacak kartı
 * olmayan bir bölüm, seçilebilir bir seçenek değildir.
 */
export function areasOf(codes: readonly QrScreenCode[]): QrPrintArea[] {
    const areas = new Map<number, QrPrintArea>();

    for (const code of codes) {
        if (code.areaId == null || !code.areaLabel) continue;

        const existing = areas.get(code.areaId);

        if (existing) {
            existing.count += 1;
        } else {
            areas.set(code.areaId, { id: code.areaId, label: code.areaLabel, count: 1 });
        }
    }

    return [...areas.values()];
}

/** Plana göre gerçekten basılacak kodlar. */
export function codesInScope(
    codes: readonly QrScreenCode[],
    plan: QrPrintPlan,
): readonly QrScreenCode[] {
    if (plan.scope === 'area') {
        return plan.areaId === null ? [] : codes.filter((code) => code.areaId === plan.areaId);
    }

    if (plan.scope === 'one') {
        const chosen = codes.find((code) => code.id === plan.codeId) ?? codes[0];

        return chosen ? [chosen] : [];
    }

    return codes;
}

/** Kartın dikey/yatay mm ölçüsü — ekranda YAZAN sayı. */
export function planDimensionsMm(plan: QrPrintPlan): [number, number] {
    const [width, height] = CARD_GEOMETRY_MM[plan.size];

    return plan.landscape ? [height, width] : [width, height];
}

/** Ölçü kâğıt ailesinden mi (mm yazılır) yoksa oran ailesinden mi? */
export function isPaperSize(size: CardGeometrySizeKey): boolean {
    return !size.includes(':');
}

/**
 * Kodun basılacağı gerçek milimetre — sunucunun bestecisiyle aynı hesap
 * (`lib/qrCardGeometry`).
 *
 * Masa adı satırı geometriyi değiştirir, o yüzden sorulur: seçilen kodun bir
 * masası varsa kart iki satır taşır ve kod bir tık küçülür.
 */
export function planCodeSideMm(plan: QrPrintPlan, printsTableName: boolean): number {
    return cardCodeSideMm(plan.theme, plan.size, plan.landscape, printsTableName);
}

function cardParams(plan: QrPrintPlan): URLSearchParams {
    const params = new URLSearchParams();
    params.set('cardTheme', plan.theme);
    params.set('size', plan.size);
    params.set('orientation', plan.landscape ? 'landscape' : 'portrait');

    if (plan.headline.trim() !== '') params.set('headline', plan.headline.trim());

    return params;
}

/** Tek kartın adresi — önizleme (inline) ve indirme (attachment) aynı uç. */
export function cardUrl(
    code: QrScreenCode,
    plan: QrPrintPlan,
    format: QrPrintFormat,
    download: boolean,
): string {
    const params = cardParams(plan);
    if (download) params.set('download', '1');

    return `/api/workspaces/${String(code.workspaceId)}/qr-codes/${String(code.id)}/card.${format}?${params.toString()}`;
}

/**
 * TOPLU ARŞİV. Matbaa her kartı ayrı dosya olarak ister ve dosya adı hangi
 * masa olduğunu söyler; deste PDF'inden ayrı bir iştir.
 */
export function cardsZipUrl(workspaceId: number, locationId: number, plan: QrPrintPlan): string {
    const params = cardParams(plan);
    params.set('format', plan.format);

    if (plan.scope === 'area' && plan.areaId !== null) params.set('areaId', String(plan.areaId));

    return `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/qr-cards.zip?${params.toString()}`;
}

/**
 * KESİLECEK TABAKA — sayfa başına on iki kart, kesme çizgileriyle.
 *
 * Kartın kendi ölçüsünü ve tasarımını TAŞIMAZ; kendi yerleşimi vardır. Bu
 * yüzden alt çubuğun "Yazdır" düğmesi değildir: seçilen presetle basıyormuş
 * gibi davranan bir düğme, sahibin eline başka bir kâğıt verirdi.
 */
export function printSheetUrl(workspaceId: number, locationId: number, chunk: number): string {
    const base = `/api/workspaces/${String(workspaceId)}/brand/locations/${String(locationId)}/qr-codes/print.pdf`;

    return chunk > 1 ? `${base}?chunk=${String(chunk)}` : base;
}

/** Ölçünün ekranda okunan adı: "A6 · 105 × 148 mm" ya da "16:9 · yatay". */
export function dimensionLabel(plan: QrPrintPlan): string {
    const [width, height] = planDimensionsMm(plan);
    const orientation = t(
        plan.landscape
            ? 'workspace.publication.qrScreen.custom.landscape'
            : 'workspace.publication.qrScreen.custom.portrait',
    );

    return isPaperSize(plan.size)
        ? t('workspace.publication.qrScreen.preview.paperDims', {
              size: plan.size,
              width: String(width),
              height: String(height),
          })
        : t('workspace.publication.qrScreen.preview.ratioDims', {
              ratio: plan.size,
              orientation: orientation.toLocaleLowerCase(),
          });
}

/**
 * ALT ÇUBUĞUN TEK CÜMLESİ — kaynağın kendi aygıtı.
 *
 * Seçili durumu KELİMEYLE anlatan yer burasıdır ve bu bir erişilebilirlik
 * gereğidir: seçili seçenek yalnız kenarlık rengiyle işaretlenseydi,
 * kırmızı-yeşil ayırt edemeyen bir sahip beş tasarım arasında hiçbir fark
 * görmezdi (WCAG 2.2 §1.4.1). Cümle "1 kart · A6 dikey · sade" der; renk
 * yalnız ona eşlik eder.
 */
export function planSummary(plan: QrPrintPlan, cardCount: number): string {
    const theme = QR_CARD_THEMES.find((option) => option.key === plan.theme);

    return t('workspace.publication.qrScreen.summary', {
        count: String(cardCount),
        size: plan.size,
        orientation: t(
            plan.landscape
                ? 'workspace.publication.qrScreen.custom.landscape'
                : 'workspace.publication.qrScreen.custom.portrait',
        ).toLocaleLowerCase(),
        theme: theme ? t(theme.labelKey).toLocaleLowerCase() : plan.theme,
    });
}
