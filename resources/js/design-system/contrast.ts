/**
 * WCAG kontrast hesabı — semantic token'ların erişilebilirlik kapısı.
 *
 * `docs/06` §8 ve `docs/design-corpus/saas-panel-tasarim-sistemi.md` metin
 * kontrastını her state ve her iki temada en az 4.5:1 tutmayı şart koşar.
 * Bu dosya o şartı hesaplanabilir kılar; `design-system.guard.test.ts` onu
 * build'i kıran bir kurala çevirir.
 *
 * Neden var: token değerleri elle seçilir ve göz onları doğrulayamaz. Bu
 * depoda `--fg-subtle` açık temada 2.88:1 olarak yayınlandı — AA'nın, hatta
 * büyük-metin eşiğinin bile altında — ve hiçbir şey fark etmedi. Ölçmeyen bir
 * sistem er ya da geç okunmaz metin üretir.
 */

export const WCAG_AA_NORMAL_TEXT = 4.5;

export const WCAG_AA_LARGE_TEXT = 3;

type Rgb = readonly [number, number, number];

/**
 * Bir renk ve ONUN SAYDAMLIĞI.
 *
 * AEP jeton merdiveni ikincil metni `rgb(8 6 22 / 66%)` gibi yazıyor ve bu
 * doğru bir karar: aynı ton her yüzeyin üstünde çalışır. Ama saydam bir metin
 * rengi TEK BAŞINA ölçülemez — okunabilirliği altındaki zemine bağlıdır.
 * Ölçmeden önce zeminle harmanlanmalı.
 */
export type ResolvedColor = { readonly rgb: Rgb; readonly alpha: number };

const clamp01 = (value: number): number => Math.min(1, Math.max(0, value));

/** OKLCH -> lineer sRGB. Gamma kodlaması UYGULANMAZ; parlaklık lineer ışıkta ölçülür. */
export function oklchToLinearRgb(l: number, c: number, hDegrees: number): Rgb {
    const h = (hDegrees * Math.PI) / 180;
    const a = c * Math.cos(h);
    const b = c * Math.sin(h);

    const lCube = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3;
    const mCube = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3;
    const sCube = (l - 0.0894841775 * a - 1.291485548 * b) ** 3;

    return [
        clamp01(4.0767416621 * lCube - 3.3077115913 * mCube + 0.2309699292 * sCube),
        clamp01(-1.2684380046 * lCube + 2.6097574011 * mCube - 0.3413193965 * sCube),
        clamp01(-0.0041960863 * lCube - 0.7034186147 * mCube + 1.707614701 * sCube),
    ] as const;
}

const srgbChannelToLinear = (channel: number): number =>
    channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

/** `srgbChannelToLinear`'ın tersi — saydam harmanlama gamma uzayında yapılır. */
const linearChannelToSrgb = (channel: number): number =>
    channel <= 0.0031308 ? channel * 12.92 : 1.055 * channel ** (1 / 2.4) - 0.055;

/**
 * Saydam bir rengi zeminin üstüne YERLEŞTİRİR.
 *
 * Harmanlama GAMMA KODLU sRGB'de yapılır, lineer ışıkta değil — çünkü
 * tarayıcı da öyle yapar. Bu ayrım küçük değil: `rgb(8 6 22 / 66%)` beyaz
 * üstünde lineer harmanlanınca 2.68:1, tarayıcının gerçekten çizdiği gamma
 * uzayında ise 6.6:1 verir. Yani lineer harmanlayan bir kapı, ekranda gayet
 * okunur olan bir metni "okunmuyor" diye reddeder — ya da tersi.
 *
 * Bu dosyanın her rengi lineer tutulur (parlaklık orada ölçülür), o yüzden
 * burada geri kodlanır, harmanlanır ve yeniden lineerleştirilir.
 */
export function compositeOver(foreground: ResolvedColor, background: Rgb): Rgb {
    const alpha = clamp01(foreground.alpha);

    const blend = (channelIndex: 0 | 1 | 2): number =>
        srgbChannelToLinear(
            linearChannelToSrgb(foreground.rgb[channelIndex]) * alpha +
                linearChannelToSrgb(background[channelIndex]) * (1 - alpha),
        );

    return [blend(0), blend(1), blend(2)] as const;
}

export function hexToLinearRgb(hex: string): Rgb | null {
    const match = /^#([0-9a-f]{6})$/i.exec(hex.trim());
    if (!match) return null;

    const digits = match[1];

    return [
        srgbChannelToLinear(parseInt(digits.slice(0, 2), 16) / 255),
        srgbChannelToLinear(parseInt(digits.slice(2, 4), 16) / 255),
        srgbChannelToLinear(parseInt(digits.slice(4, 6), 16) / 255),
    ] as const;
}

const relativeLuminance = (rgb: Rgb): number => 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];

/** WCAG 2.x kontrast oranı. Siyah/beyaz için 21 döner. */
export function contrastRatio(a: Rgb, b: Rgb): number {
    const first = relativeLuminance(a) + 0.05;
    const second = relativeLuminance(b) + 0.05;

    return Math.max(first, second) / Math.min(first, second);
}

/**
 * Bir CSS renk değerini lineer RGB'ye çözer. `var(--x)` zincirini verilen
 * kapsam üzerinden takip eder; sistem renkleri (CanvasText gibi) `null`
 * döner ve ölçüm dışında kalır — onların kontrastını tarayıcı garanti eder.
 */
export function resolveColor(value: string, scope: Record<string, string>, depth = 0): Rgb | null {
    return resolveColorWithAlpha(value, scope, depth)?.rgb ?? null;
}

/**
 * Bir CSS renk değerini SAYDAMLIĞIYLA BİRLİKTE çözer.
 *
 * `resolveColor` saydamlığı düşürür ve bu, ölçümü sessizce yanıltır:
 * `rgb(8 6 22 / 66%)` tam donuk sanıldığında 19:1 ölçülür, oysa beyaz zeminin
 * üstünde gerçek değeri ~5:1'dir. Kapı testi bu yüzden alfayı okumak ve zemine
 * yerleştirmek zorunda.
 */
export function resolveColorWithAlpha(
    value: string,
    scope: Record<string, string>,
    depth = 0,
): ResolvedColor | null {
    if (depth > 8) return null;

    const raw = value.trim();

    const oklch = /^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+)(%?))?/i.exec(
        raw,
    );
    if (oklch) {
        const lightness = parseFloat(oklch[1]);

        return {
            rgb: oklchToLinearRgb(
                oklch[2] === '%' || lightness > 1 ? lightness / 100 : lightness,
                parseFloat(oklch[3]),
                parseFloat(oklch[4]),
            ),
            alpha: parseAlpha(oklch[5], oklch[6]),
        };
    }

    /*
        `rgb(8 6 22 / 66%)` — AEP merdiveninin ikincil metin yazımı. Hem boşluklu
        (CSS Color 4) hem virgüllü eski yazım kabul edilir; ikisi de bu depoda
        geçerli CSS'tir ve ölçüm biçime göre değişmemeli.
    */
    const rgb =
        /^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[/,]\s*([\d.]+)(%?))?\s*\)$/i.exec(
            raw,
        );
    if (rgb) {
        return {
            rgb: [
                srgbChannelToLinear(parseFloat(rgb[1]) / 255),
                srgbChannelToLinear(parseFloat(rgb[2]) / 255),
                srgbChannelToLinear(parseFloat(rgb[3]) / 255),
            ] as const,
            alpha: parseAlpha(rgb[4], rgb[5]),
        };
    }

    const hex = hexToLinearRgb(raw);
    if (hex) return { rgb: hex, alpha: 1 };

    const reference = /^var\(\s*(--[a-z0-9-]+)\s*\)$/i.exec(raw);
    if (reference) {
        const next = scope[reference[1]];

        return next === undefined ? null : resolveColorWithAlpha(next, scope, depth + 1);
    }

    return null;
}

function parseAlpha(value: string | undefined, percent: string | undefined): number {
    if (value === undefined) return 1;

    const parsed = parseFloat(value);

    return clamp01(percent === '%' ? parsed / 100 : parsed);
}

/** Bir CSS bloğundaki custom property'leri okur (`:root`, `.dark`, …). */
export function readCustomProperties(css: string, selector: string): Record<string, string> {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const block = new RegExp(`${escaped}\\s*\\{([\\s\\S]*?)\\n\\}`).exec(css);

    if (!block) return {};

    const properties: Record<string, string> = {};
    for (const [, name, value] of block[1].matchAll(/(--[a-z0-9-]+):\s*([^;]+);/g)) {
        properties[name] = value.trim();
    }

    return properties;
}
