/**
 * İSTEMCİDE KÜÇÜLTME — saf karar mantığı, DOM yok.
 *
 * Kanonik kaynak: `docs/reference/media-manager/Medya Yonetimi v2.dc.html`,
 * "Yükle" ekranının 2. adımı ("Telefonda küçültüldü"). Bugün telefonla
 * çekilen 8 MB'lık bir fotoğraf olduğu gibi ağa çıkıyor; kaynak bunu
 * yüklemeden ÖNCE tarayıcıda çözmeyi şart koşuyor.
 *
 * İki ayrı kazanç var ve ikisi de gerçek:
 *
 *   1. **Kota ve mobil veri.** Sunucu görseli zaten küçültüyor; ağdan geçen
 *      fazlalık hiçbir işe yaramıyordu. Kebapçı bunu fatura olarak ödüyor.
 *   2. **Güvenlik.** Dosya kullanıcının KENDİ makinesinde küçülür. Taranmamış
 *      bir dosyayı önizleme diye sunucuya gönderip oradan geri servis etmek,
 *      tam olarak virüs taramasının engellemeye çalıştığı şeydir
 *      (`docs/108` §4).
 *
 * Mantık DOM'dan ayrı durur çünkü asıl kural burada: **hedef, slotun en
 * küçük ölçüsünün ALTINA inemez.** Kırpma piksel eklemez — bir kere
 * küçültülmüş kare geri büyütülemez ve sunucu onu reddeder. Kuralın kendisi
 * `cropGeometry` içinde zaten yaşıyor; burada TEKRARLANMAZ, oradan kullanılır
 * (`canCropInto`, `maxZoomFor`).
 */
import { canCropInto, maxZoomFor, parseAspect, type Size } from './cropGeometry';

/**
 * Uzun kenarın varsayılan hedefi.
 *
 * Kaynağın kendi varsayılanı (`s.maxEdge || 2560`). Menüde bir yemek
 * fotoğrafı hiçbir yerde 2560 pikselden geniş çizilmiyor; bunun üstü ağda
 * taşınan ama hiç görülmeyen piksel demek.
 */
export const DEFAULT_MAX_EDGE = 2560;

/** Kaynağın uzun kenarı için kullanıcıya sunulan aralık (kaynaktaki kaydırıcı). */
export const MIN_MAX_EDGE = 1280;
export const MAX_MAX_EDGE = 4096;

export type DownscaleRequest = {
    /** Kaynağın gerçek piksel ölçüsü. `0` ise ölçü henüz okunmamıştır. */
    source: Size;
    /** Slotun en küçük ölçüsü; `0 × 0` "kısıt yok" demektir. */
    minimum: Size;
    /** Slotun oranı (`'3:1'`); `null` ise kırpma yok. */
    aspect: string | null;
    /** Hedeflenen uzun kenar. */
    maxEdge: number;
};

export type DownscalePlan = {
    /** Küçültme yapılacak mı? `false` ise dosya olduğu gibi gider. */
    apply: boolean;
    /** Küçültülmüş çıktının piksel ölçüsü. */
    target: Size;
    /**
     * Hedefi KİM belirledi.
     *
     * Ekranda bu ayrım görünür: kullanıcı uzun kenarı 1280'e çektiğinde
     * sonucun neden 1200'de durduğunu bilmeli, yoksa kaydırıcıyı bozuk sanar.
     */
    limitedBy: 'source' | 'longEdge' | 'slotMinimum';
};

/**
 * Küçültme oranının TABANI — bunun altına inmek slotu kullanılamaz yapar.
 *
 * `maxZoomFor` "çerçeve en küçük ölçünün altına inemez" kuralını zaten
 * taşıyor: kaynağı `1/maxZoom` katsayısıyla küçültmek, kırpma çerçevesini tam
 * o sınıra oturtur. Kural burada yeniden yazılmaz.
 *
 * TEK sapma, `maxZoomFor`'un 8 katlık tavanıdır. O tavan bir YAKINLAŞTIRMA
 * ürün kararıdır ("birkaç piksellik çerçeve teknik olarak geçerli, ürün
 * olarak anlamsız") ve küçültmeye taşınmaz: taşınsaydı 12000 piksellik bir
 * tarayıcı çıktısı 1500'ün altına indirilemezdi. Bu yüzden kısıtsız slotta
 * taban sıfırdır.
 */
function floorScale(source: Size, minimum: Size, ratio: number | null): number {
    if (minimum.width <= 0 && minimum.height <= 0) {
        return 0;
    }

    return 1 / maxZoomFor(source, minimum, ratio);
}

function scaled(source: Size, scale: number): Size {
    return {
        width: Math.max(1, Math.round(source.width * scale)),
        height: Math.max(1, Math.round(source.height * scale)),
    };
}

/**
 * Küçültme planı.
 *
 * Sıra önemli: önce uzun kenar hedefi, sonra slot tabanı. Taban kazandığında
 * kullanıcının istediği ölçüye İNİLMEZ ve bu `limitedBy` ile söylenir.
 */
export function planDownscale({
    source,
    minimum,
    aspect,
    maxEdge,
}: DownscaleRequest): DownscalePlan {
    const ratio = parseAspect(aspect);
    const longestEdge = Math.max(source.width, source.height);

    // Ölçü henüz okunmadıysa (jsdom, bozuk dosya) karar verilecek bir şey yok.
    if (longestEdge <= 0) {
        return { apply: false, target: source, limitedBy: 'source' };
    }

    const wanted = Number.isFinite(maxEdge) && maxEdge > 0 ? Math.min(1, maxEdge / longestEdge) : 1;

    // Küçültme BÜYÜTME değildir: hedef kaynaktan büyükse yapılacak iş yoktur.
    if (wanted >= 1) {
        return { apply: false, target: source, limitedBy: 'source' };
    }

    const floor = floorScale(source, minimum, ratio);

    if (floor >= 1) {
        // Kaynak zaten sınırda: bir piksel küçültmek onu kullanılamaz yapar.
        return { apply: false, target: source, limitedBy: 'slotMinimum' };
    }

    const limitedBy = wanted < floor ? 'slotMinimum' : 'longEdge';
    let scale = Math.max(wanted, floor);
    let target = scaled(source, scale);

    /*
        Yuvarlama TEK YÖNLÜ güvenli değildir: `Math.round` bir pikseli aşağı
        alabilir ve tam sınırdaki bir çerçeve o bir pikselle geçersiz olur.
        Bu yüzden sonuç kuralın KENDİSİYLE doğrulanır (`canCropInto`) ve
        gerekirse binde bir büyütülerek tekrar denenir. Döngü sınırlıdır;
        sınıra dayanırsa küçültme hiç yapılmaz — sessizce bozuk bir dosya
        üretmektense hiç üretmemek doğrudur.
    */
    for (let attempt = 0; attempt < 24 && !canCropInto(target, minimum, ratio); attempt++) {
        scale = Math.min(1, scale * 1.001);
        target = scaled(source, scale);
    }

    if (!canCropInto(target, minimum, ratio)) {
        return { apply: false, target: source, limitedBy: 'slotMinimum' };
    }

    return { apply: true, target, limitedBy };
}

/** Ölçülmüş kazanç: iki GERÇEK bayt arasındaki fark. */
export type Saving = { bytes: number; percent: number };

/**
 * Kazanç ÖLÇÜLÜR, tahmin edilmez.
 *
 * Ekranda "%86 küçüldü" yazan cümlenin arkasında iki gerçek dosya boyutu
 * durur. Kazanç yoksa `null` döner ve ekran hiçbir şey iddia etmez: yeniden
 * kodlama bazen büyütür (küçük bir PNG'yi JPEG'e çevirmek gibi) ve o durumda
 * küçültme zaten uygulanmamalıdır.
 */
export function measuredSaving(beforeBytes: number, afterBytes: number): Saving | null {
    if (!Number.isFinite(beforeBytes) || !Number.isFinite(afterBytes)) return null;
    if (beforeBytes <= 0 || afterBytes <= 0) return null;
    if (afterBytes >= beforeBytes) return null;

    return {
        bytes: beforeBytes - afterBytes,
        percent: Math.round((1 - afterBytes / beforeBytes) * 100),
    };
}

/**
 * Küçültülmüş çıktının MIME türü.
 *
 * HEIC/HEIF telefonda JPEG'e çevrilir (kaynağın kendi cümlesi): iPhone karesi
 * aksi hâlde çoğu tarayıcıda hiç açılmaz ve kütüphanede boş bir kutu görünür.
 * PNG olduğu gibi kalır — bir logoyu JPEG'e çevirmek saydamlığı düz beyaza
 * gömer (INV-07).
 */
export function downscaleOutputType(mimeType: string): 'image/png' | 'image/jpeg' {
    return mimeType === 'image/png' ? 'image/png' : 'image/jpeg';
}
