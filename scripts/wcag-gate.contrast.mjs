/*
    KONTRAST ARİTMETİĞİ — kapının ölçtüğü sayıların kaynağı.

    ═══ NEDEN AYRI BİR DOSYA ═══

    `scripts/wcag-gate` çağrıldığı anda Chrome başlatır; yani içindeki hiçbir
    işlev bir testten çağrılamaz. Bu dosya o işlevleri tarayıcıdan ayırır ve
    `scripts/wcag-gate.test.sh` onları SENTETİK görüntülerle sınar: kontrastı
    bilinen bir kutu verilir, kapının doğru sayıyı bulması ŞART koşulur.

    Neden gerekli: bir ölçüm aracının en tehlikeli hâli, sessizce hiçbir şey
    bulmayan hâlidir. Bu paketin kendi geçmişi bunu iki kez gösterdi —
    metni gizlediğini sanan bir levha metni gizlemiyordu, punto iki katına
    çıkarken derinlik başına katlanıyordu. İkisi de "kapı çalışıyor" gibi
    görünüyordu. Sentetik bir denek, o sessizliği bozar.
*/

/* --- kontrast ------------------------------------------------------------ */

/** WCAG 2.x bağıl parlaklık. Kanal 0-255. */
export function luminance(r, g, b) {
    const channel = (value) => {
        const v = value / 255;

        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

export function contrastRatio(a, b) {
    const light = Math.max(a, b);
    const dark = Math.min(a, b);

    return (light + 0.05) / (dark + 0.05);
}

/*
    BİR ARKA PLAN TEK BİR RENK DEĞİLDİR.

    ═══ İLK ÖLÇÜM NEYİ KAÇIRDI ═══

    İlk sürüm kutunun içindeki AYRIK RENKLERİ sayıyor ve kutunun %2'sinden
    azını kaplayan her rengi atıyordu. Düz bir zeminde bu doğru çalışır. Bir
    YILDIZ ALANINDA çalışmaz: gökyüzü binlerce ayrı renk taşır ve her biri
    kutunun binde biridir, yani hepsi atılır. Geriye kalan koyu ortalama
    "kontrast 8:1" der — ama ziyaretçinin gözünde harfin ortasında duran
    parlak nokta durmaya devam eder. Sahibin *"yazılar okunmuyor"* cümlesi
    tam olarak orayı işaret ediyordu ve ilk ölçüm orayı göremiyordu.

    ═══ ŞİMDİ ÖLÇÜLEN ŞEY ═══

    Metin satırının altındaki HER piksel için kontrast hesaplanır ve
    dağılımın 5. yüzdeliği alınır: piksellerin en kötü %5'i eşiğin altına
    düşüyorsa metin o zeminde okunmuyor demektir.

    ═══ %5 BİR WCAG SAYISI DEĞİLDİR ═══

    Ölçüt "arka plana karşı 4.5:1" der ve arka planın TEK renk olduğunu
    varsayar; desenli bir zemin için bir sayı vermez. %5 bu boşluğu kapatan
    bir MÜHENDİSLİK KARARIDIR ve açıkça öyle yazılmıştır: tek bir kaçak
    pikselin bütün paragrafı kusurlu göstermesini engelleyecek kadar
    toleranslı, harflerin arkasına düşen bir yıldız kümesini görecek kadar
    dar. Mutlak en kötü piksel ve eşiğin altındaki piksel oranı raporda
    AYRICA durur; karar değiştirilmek istenirse değiştirilecek yer burasıdır.
*/
const BACKGROUND_PERCENTILE = 0.05;

export function worstContrastUnder(image, rect, textColor, alpha) {
    const { width, height, channels, pixels } = image;
    const x0 = Math.max(0, Math.floor(rect.x));
    const y0 = Math.max(0, Math.floor(rect.y));
    const x1 = Math.min(width, Math.ceil(rect.x + rect.w));
    const y1 = Math.min(height, Math.ceil(rect.y + rect.h));

    if (x1 <= x0 || y1 <= y0) return null;

    const histogram = new Map();
    let total = 0;

    for (let y = y0; y < y1; y += 1) {
        for (let x = x0; x < x1; x += 1) {
            const i = (y * width + x) * channels;
            const key = (pixels[i] << 16) | (pixels[i + 1] << 8) | pixels[i + 2];
            histogram.set(key, (histogram.get(key) ?? 0) + 1);
            total += 1;
        }
    }

    if (total === 0) return null;

    const entries = [];

    for (const [key, count] of histogram) {
        const br = (key >> 16) & 255;
        const bg = (key >> 8) & 255;
        const bb = key & 255;
        /* Metnin efektif rengi: ata opaklığıyla arka plana karışmış hâli. */
        const fr = textColor.r * alpha + br * (1 - alpha);
        const fg = textColor.g * alpha + bg * (1 - alpha);
        const fb = textColor.b * alpha + bb * (1 - alpha);
        entries.push({
            ratio: contrastRatio(luminance(fr, fg, fb), luminance(br, bg, bb)),
            count,
            background: { r: br, g: bg, b: bb },
        });
    }

    entries.sort((a, b) => a.ratio - b.ratio);

    const quota = Math.max(1, Math.round(total * BACKGROUND_PERCENTILE));
    let seen = 0;
    let percentile = entries[entries.length - 1];

    for (const entry of entries) {
        seen += entry.count;

        if (seen >= quota) {
            percentile = entry;
            break;
        }
    }

    return {
        ratio: percentile.ratio,
        background: percentile.background,
        absoluteWorst: Number(entries[0].ratio.toFixed(2)),
        absoluteWorstBackground: entries[0].background,
        pixels: total,
    };
}

/**
 * Bir dikdörtgen şeridin renk dağılımı: renk → pay.
 *
 * Ortalama alınmıyor. İki renkli bir şeridin ortalaması ikisinden de olmayan
 * ÜÇÜNCÜ bir renk verir ve o renk ekranda hiç yoktur.
 */
export function bandColors(image, band) {
    const { width, height, channels, pixels } = image;
    const histogram = new Map();
    let total = 0;

    for (const rect of band) {
        const x0 = Math.max(0, Math.floor(rect.x));
        const y0 = Math.max(0, Math.floor(rect.y));
        const x1 = Math.min(width, Math.ceil(rect.x + rect.w));
        const y1 = Math.min(height, Math.ceil(rect.y + rect.h));

        for (let y = y0; y < y1; y += 1) {
            for (let x = x0; x < x1; x += 1) {
                const i = (y * width + x) * channels;
                const key = (pixels[i] << 16) | (pixels[i + 1] << 8) | pixels[i + 2];
                histogram.set(key, (histogram.get(key) ?? 0) + 1);
                total += 1;
            }
        }
    }

    if (total === 0) return null;

    return [...histogram]
        .map(([key, count]) => ({
            r: (key >> 16) & 255,
            g: (key >> 8) & 255,
            b: key & 255,
            share: count / total,
        }))
        .sort((a, b) => b.share - a.share);
}

/*
    KONTROLÜN SINIRI: EN GÜÇLÜ KENAR GEÇİŞİ.

    ═══ İLK ÖLÇÜM NEYİ YANLIŞ YAPTI ═══

    İlk sürüm "içerideki baskın renk" ile "dışarıdaki baskın renk"i
    karşılaştırıyordu ve giriş formunu kusurlu bildirdi: alanın DOLGUSU
    kartın rengiyle aynıydı, oran 1:1 çıktı. Ama ekranda o alanın bir
    KENAR ÇİZGİSİ vardı ve sınırı çizen oydu. Bir pikselik çizgi dört
    piksellik şeridin dörtte biridir; baskın renk sorgusu onu her zaman
    kaybeder.

    ═══ DOĞRU SORU ═══

    "İçerinin ortalaması dışarıdan farklı mı" değil, "kenarın iki yanı
    arasında GÖZÜN GÖREBİLECEĞİ bir geçiş var mı". Yani: dışarıdaki zemine
    karşı, kenarın hemen içindeki şeritte 3:1'i sağlayan, o şeridin anlamlı
    bir payını kaplayan BİR renk var mı. Bir kenar çizgisi de, dolu bir
    düğme yüzeyi de bu soruyu geçer; ikisi de olmayan bir alan geçemez.

    Pay eşiği (%12) bir pikselik bir çizginin üç piksellik şeritteki payından
    (yaklaşık %33) belirgin biçimde düşüktür: kenar yumuşatması bir çizgiyi
    iki tona böler ve ikisi de sayılabilmelidir.
*/
const BOUNDARY_MIN_SHARE = 0.12;

export function boundaryContrast(image, box) {
    const thickness = 3;
    const inset = [
        { x: box.x + 6, y: box.y, w: Math.max(0, box.w - 12), h: thickness },
        { x: box.x + 6, y: box.y + box.h - thickness, w: Math.max(0, box.w - 12), h: thickness },
        { x: box.x, y: box.y + 6, w: thickness, h: Math.max(0, box.h - 12) },
        { x: box.x + box.w - thickness, y: box.y + 6, w: thickness, h: Math.max(0, box.h - 12) },
    ];
    const outset = [
        { x: box.x + 6, y: box.y - thickness, w: Math.max(0, box.w - 12), h: thickness },
        { x: box.x + 6, y: box.y + box.h, w: Math.max(0, box.w - 12), h: thickness },
        { x: box.x - thickness, y: box.y + 6, w: thickness, h: Math.max(0, box.h - 12) },
        { x: box.x + box.w, y: box.y + 6, w: thickness, h: Math.max(0, box.h - 12) },
    ];

    const insideColors = bandColors(image, inset);
    const outsideColors = bandColors(image, outset);

    if (insideColors === null || outsideColors === null) return null;

    const outside = outsideColors[0];
    const outsideLuminance = luminance(outside.r, outside.g, outside.b);
    let best = null;

    for (const color of insideColors) {
        if (color.share < BOUNDARY_MIN_SHARE) continue;

        const ratio = contrastRatio(luminance(color.r, color.g, color.b), outsideLuminance);

        if (best === null || ratio > best.ratio) best = { ratio, color };
    }

    if (best === null) return null;

    return { ratio: best.ratio, inside: best.color, outside };
}
