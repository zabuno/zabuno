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
    if (depth > 8) return null;

    const raw = value.trim();

    const oklch = /^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)/i.exec(raw);
    if (oklch) {
        const lightness = parseFloat(oklch[1]);

        return oklchToLinearRgb(
            oklch[2] === '%' || lightness > 1 ? lightness / 100 : lightness,
            parseFloat(oklch[3]),
            parseFloat(oklch[4]),
        );
    }

    const hex = hexToLinearRgb(raw);
    if (hex) return hex;

    const reference = /^var\(\s*(--[a-z0-9-]+)\s*\)$/i.exec(raw);
    if (reference) {
        const next = scope[reference[1]];

        return next === undefined ? null : resolveColor(next, scope, depth + 1);
    }

    return null;
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
