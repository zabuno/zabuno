/**
 * Semantic map — tasarım sisteminin makine tarafından okunabilir ilişki haritası.
 *
 * docs/35 §2a bir kompozisyon sırası dondurur: micro → compound → macro →
 * surface. Bu dosya o sırayı **çalıştırılabilir** hâle getirir: katman dosya
 * yolundan türetilir, elle bakım gerektiren bir liste tutulmaz (öyle bir liste
 * kaçınılmaz olarak çürür ve haritayı yalana çevirir).
 *
 * Kural tek cümle: bir katman yalnız KENDİNDEN ÖNCEKİ katmanları compose eder.
 * Bu sayede bir micro değiştiğinde onu compose eden herkes değişir; tersi
 * yönde bir bağ kurulursa "master component" fikri çöker.
 */

export const LAYERS = ['micro', 'compound', 'macro', 'surface'] as const;

export type Layer = (typeof LAYERS)[number];

/** Katman sırası: küçük indeks = daha temel. */
export function layerRank(layer: Layer): number {
    return LAYERS.indexOf(layer);
}

/**
 * Bir dosya yolundan katmanını çıkarır.
 *
 * `catalog/**\/{micro,compound,macro}/` tasarım sistemidir. Uygulama kökleri
 * (workspace, admin, auth, public, platform) `surface`'tır: bir surface bir
 * macro'yu use-case'e bağlar, tersi olmaz.
 */
export function layerOf(filePath: string): Layer | null {
    const path = filePath.replace(/\\/g, '/');

    if (/\/(micro)\//.test(path)) return 'micro';
    if (/\/(compound)\//.test(path)) return 'compound';
    if (/\/(macro)\//.test(path)) return 'macro';

    if (/\/components\/(workspace|admin|auth|public|platform)\//.test(path)) {
        return 'surface';
    }

    return null;
}

/**
 * `from` katmanı `to` katmanını compose edebilir mi?
 *
 * Yasak olan tek yön YUKARI doğrudur: bir micro compound/macro/surface'a
 * bağlanamaz. Bir micro üstündeki katmanı tanırsa artık yeniden kullanılabilir
 * bir yapı taşı değildir ve "master component" fikri çöker.
 *
 * Aynı katman içi kompozisyon SERBESTTİR ve kasıtlıdır: bir `IconButton`
 * erişilebilir etiketi için `VisuallyHidden`'ı, bir `PageHeader` de
 * `Breadcrumbs`'ı compose eder. Bunu yasaklamak, paylaşılan davranışı her
 * bileşene kopyalamaya zorlardı — sistemi korumak yerine bozardı.
 */
export function mayCompose(from: Layer, to: Layer): boolean {
    return layerRank(to) <= layerRank(from);
}

/** Ham Tailwind paleti — semantic katmanı atlayan her sınıf. */
export const RAW_PALETTE_PATTERN =
    /\b(bg|text|border|ring|divide|placeholder|from|to|via|fill|stroke|outline|accent|caret|decoration|shadow)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|[1-9]00|950)\b/g;
