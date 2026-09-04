import { readFileSync } from 'node:fs';

/**
 * Jetonların GERÇEK kaynağı artık iki katmanlıdır (FF-131).
 *
 * `resources/css/aep/` teslim paketinin kendi tasarım sistemidir ve ham
 * değerler orada yaşar; `app.css` onun üstüne geçiş takma adları koyar
 * (`--canvas: var(--aep-surface-canvas)`).
 *
 * Kapı testleri yalnız `app.css` okuduğunda bir jetonu çözemeyip SESSİZCE
 * boş dönerdi — yani "ölçülemedi" ile "ölçüldü ve geçti" aynı sonuca
 * çıkardı. Bu yüzden okuma tek bir yerden yapılır ve iki katmanı da içerir.
 *
 * Sıra önemlidir: AEP önce gelir, `app.css` sonra. Aynı adı iki dosya da
 * tanımlarsa depodaki değer kazanır — tarayıcıdaki kaskadla aynı yön.
 */
const AEP_TOKEN_FILES = [
    'resources/css/aep/tokens/fonts.css',
    'resources/css/aep/tokens/colors.css',
    'resources/css/aep/tokens/typography.css',
    'resources/css/aep/tokens/spacing.css',
    'resources/css/aep/tokens/radius.css',
    'resources/css/aep/tokens/elevation.css',
    'resources/css/aep/tokens/motion.css',
    'resources/css/aep/tokens/layout.css',
    'resources/css/aep/tokens/density.css',
    'resources/css/aep/tokens/variants.css',
    'resources/css/aep/tokens/base.css',
] as const;

export const APP_CSS_PATH = 'resources/css/app.css';

/** AEP katmanı + `app.css`, tek bir metin olarak. */
export function readStyleLayers(): string {
    return [
        ...AEP_TOKEN_FILES.map((path) => readFileSync(path, 'utf8')),
        readFileSync(APP_CSS_PATH, 'utf8'),
    ].join('\n');
}

/**
 * Bir temanın çözülmüş jeton kümesi.
 *
 * AEP koyu temayı `[data-theme="dark"]` ile, depo ise `.dark` sınıfıyla
 * anahtarlar; `ThemeRoot` ikisini birden yazar, bu yüzden ölçüm de ikisini
 * birden toplamak ZORUNDA. Yalnız birini okumak, koyu temayı yarım ölçmek
 * olurdu.
 */
/**
 * Bir seçicinin TÜM bloklarını toplar, ilkini değil.
 *
 * `readCustomProperties` tek blok okur ve bu, tek dosyalı dünyada doğruydu.
 * AEP katmanı aynı seçiciyi birden çok kez açar (ilkel palet ayrı, semantic
 * jetonlar ayrı blokta) ve ilk bloğu okuyan bir ölçüm, jetonların yarısını
 * "tanımsız" görürdü — sessizce, çünkü tanımsız bir jeton hata değil `undefined`
 * döndürür.
 */
function collect(css: string, selector: string): Record<string, string> {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`${escaped}\\s*\\{([^{}]*)\\}`, 'g');
    const properties: Record<string, string> = {};

    for (const block of css.matchAll(pattern)) {
        for (const [, name, value] of block[1].matchAll(/(--[a-z0-9-]+):\s*([^;]+);/g)) {
            properties[name] = value.trim();
        }
    }

    return properties;
}

export function themeScope(
    selector: ':root' | '.dark',
    read: (css: string, selector: string) => Record<string, string>,
): Record<string, string> {
    const css = readStyleLayers();
    // `read` imzası korunuyor ki çağıranlar değişmesin; toplama işi burada.
    void read;

    const base = collect(css, ':root');

    if (selector === ':root') return base;

    return { ...base, ...collect(css, '[data-theme="dark"]'), ...collect(css, '.dark') };
}
