/**
 * Sahte-yerelleştirme, istemci tarafı — `docs/121` §4.
 *
 * Sunucudaki eşi `app/Support/Localization/PseudoLocalizer.php`. İkisi de
 * gerekli, çünkü metnin bir kısmı sunucuda (Blade), bir kısmı tarayıcıda
 * (React katalogları) yazılıyor; yalnız birini dönüştürmek, dönüşmeyen
 * yarıyı "kodda gömülü" gibi gösterirdi — yani ölçüm aracı yalancı pozitif
 * üretirdi.
 *
 * BU BİR ÇEVİRİ DEĞİLDİR. Üretilen metin hiçbir dile ait değil; çeviri
 * kilidi kapalı, `shipped_locales` genişlemedi. Yalnız bir ÖLÇÜM DİLİ.
 */

/** Almancanın İngilizceye göre ortalama uzaması (`docs/121` §7). */
const EXPANSION_RATIO = 0.4;

const OPEN = '⟦';
const CLOSE = '⟧';
const PADDING = '·';

/**
 * Latin harfleri, GÖZLE OKUNABİLİR kalacak biçimde aksanlanır.
 *
 * Okunmaz hâle getirmek ölçümü işe yaramaz kılardı: geliştirici hangi metnin
 * dönüştüğünü değil, hiçbir şeyi göremezdi.
 */
const LETTERS: Record<string, string> = {
    a: 'å',
    b: 'ß',
    c: 'ç',
    d: 'ð',
    e: 'ê',
    f: 'ƒ',
    g: 'ğ',
    h: 'h',
    i: 'ï',
    j: 'j',
    k: 'k',
    l: 'ł',
    m: 'm',
    n: 'ñ',
    o: 'ô',
    p: 'þ',
    q: 'q',
    r: 'ř',
    s: 'š',
    t: 'ţ',
    u: 'û',
    v: 'v',
    w: 'ŵ',
    x: 'x',
    y: 'ý',
    z: 'ž',
    A: 'Å',
    B: 'Ɓ',
    C: 'Ç',
    D: 'Ð',
    E: 'Ê',
    F: 'Ƒ',
    G: 'Ğ',
    H: 'Ĥ',
    I: 'Ï',
    J: 'Ĵ',
    K: 'Ķ',
    L: 'Ł',
    M: 'Ṁ',
    N: 'Ñ',
    O: 'Ô',
    P: 'Þ',
    Q: 'Q',
    R: 'Ř',
    S: 'Ş',
    T: 'Ţ',
    U: 'Û',
    V: 'V',
    W: 'Ŵ',
    X: 'X',
    Y: 'Ý',
    Z: 'Ž',
};

/**
 * Bir metni ölçüm diline çevirir.
 *
 * Adlı yer tutucular (`{count}`) dokunulmadan kalır: aksanlansaydı çalışma
 * anında değiştirilemezdi ve ekranda ham bir `{çôûñt}` görünürdü. Ölçüm
 * aracının kendisi ürünü bozarsa, ölçtüğü hiçbir şeye güvenilmez.
 */
export function pseudoLocalize(text: string): string {
    if (text === '') {
        return '';
    }

    const accented = text
        .split(/(\{[a-zA-Z0-9_]+\})/)
        .map((part) =>
            part.startsWith('{')
                ? part
                : part.replace(/[a-zA-Z]/g, (letter) => LETTERS[letter] ?? letter),
        )
        .join('');

    const padding = PADDING.repeat(Math.max(1, Math.ceil(text.length * EXPANSION_RATIO)));

    return `${OPEN}${accented} ${padding}${CLOSE}`;
}

/**
 * Kip açık mı — yalnız derleme zamanı bayrağıyla.
 *
 * `VITE_I18N_PSEUDO=1 npm run build-storybook` ile açılır ve ölçüm için
 * ayrı bir çıktı üretilir. Ürün paketine giren derlemede bayrak yoktur;
 * çalışma anında açılabilen bir anahtar olsaydı, bir gün üretimde açık
 * kalırdı.
 */
export function pseudoLocalizationEnabled(): boolean {
    return import.meta.env?.VITE_I18N_PSEUDO === '1';
}
