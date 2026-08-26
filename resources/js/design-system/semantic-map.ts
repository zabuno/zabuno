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
 * **Külliyattan bilinçli sapma.** `10-frontend-katman-mimarisi` yatay bağı da
 * yasaklar. O yasak R1–R8 gibi ince bir modelde doğrudur: orada `VisuallyHidden`
 * R4, `IconButton` R6'dır, yani aralarındaki bağ zaten yataydan sayılmaz. Bu
 * deponun üç katmanlı modeli ikisini aynı kutuya koyduğu için düz bir yatay
 * yasak, paylaşılan davranışı her bileşene KOPYALAMAYA zorlardı — korumak
 * isterken bozardı.
 *
 * Yatay yasağın gerçekte koruduğu şey DÖNGÜdür. Bu yüzden burada yukarı bağ
 * yasaklanır ve döngü ayrı bir kuralla (`DS-NO-CYCLE-03`) yasaklanır; döngüsüz
 * yatay kompozisyon serbesttir. Model R4/R6 ayrımını kazandığında düz yasak
 * geri getirilebilir.
 */
export function mayCompose(from: Layer, to: Layer): boolean {
    return layerRank(to) <= layerRank(from);
}

/** Ham Tailwind paleti — semantic katmanı atlayan her sınıf. */
export const RAW_PALETTE_PATTERN =
    /\b(bg|text|border|ring|divide|placeholder|from|to|via|fill|stroke|outline|accent|caret|decoration|shadow)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-(50|[1-9]00|950)\b/g;

/**
 * Bir import grafiğinde döngü arar. Döngü, "hangisi master" sorusunu
 * cevapsız bırakır ve yükleme sırasına bağlı, teşhisi zor hatalar üretir.
 *
 * @param graph düğüm -> bağımlı olduğu düğümler
 * @returns bulunan ilk döngünün yolu; döngü yoksa `null`
 */
export function findCycle(graph: Map<string, string[]>): string[] | null {
    const VISITING = 1;
    const DONE = 2;
    const state = new Map<string, number>();
    const stack: string[] = [];

    function walk(node: string): string[] | null {
        state.set(node, VISITING);
        stack.push(node);

        for (const next of graph.get(node) ?? []) {
            if (state.get(next) === VISITING) {
                return [...stack.slice(stack.indexOf(next)), next];
            }
            if (state.get(next) === undefined) {
                const found = walk(next);
                if (found) return found;
            }
        }

        stack.pop();
        state.set(node, DONE);

        return null;
    }

    for (const node of graph.keys()) {
        if (state.get(node) === undefined) {
            const found = walk(node);
            if (found) return found;
        }
    }

    return null;
}
