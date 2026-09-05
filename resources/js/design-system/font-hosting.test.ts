/**
 * YAZI TİPİ DEPODA BARINDIRILIR — ölçülen şey SONUÇTUR.
 *
 * ═══ NEDEN VAR ═══
 *
 * `--font-sans` yıllarca "Roboto" dedi ve depoda tek bir `@font-face` yoktu.
 * Yani hiçbir cihaz Roboto görmedi: macOS SF Pro'ya, Linux DejaVu Sans'a,
 * Windows Segoe UI'a düştü. Üçü belirgin biçimde farklı genişlikte harfler
 * çizer.
 *
 * Bunun bedeli iki yerde ödendi:
 *
 * 1. ÜRÜNDE — aynı ekran her cihazda başka bir tipografiyle çizildi.
 *    Tasarım sisteminin "master değişince hepsi değişir" sözü, tipografinin
 *    EN TEMEL kararında hiç geçerli değildi.
 * 2. ÖLÇÜMDE — `scripts/mobile-ux-audit` metin kutularının uçlarını ölçer.
 *    Ölçüm (2026-09-05): `macro-layout-dashboardoverview--default` aynı
 *    commit'te yerelde 321/320 (oran 1.003), CI'nin Linux'unda eşiğin
 *    altında. Kimse bir şey bozmamıştı; yalnız harfler farklı genişlikteydi.
 *
 * ═══ NEDEN CDN DEĞİL ═══
 *
 * Üretimdeki kendi CSP'miz `font-src 'self' data:` diyor: Google Fonts'tan
 * gelen bir yazı tipi TARAYICI TARAFINDAN ENGELLENİR. Yani bir CDN bağlantısı
 * yalnız bir gizlilik yüzeyi değil, ÇALIŞMAYAN bir çözümdür — ve çalışmadığı
 * sessizce olur: sayfa açılır, yazı tipi gelmez, kimse fark etmez.
 *
 * ═══ NE ÖLÇÜYOR ═══
 *
 * Bu testler bir uygulama biçimini değil, sonucu ölçer: yığında adı geçen her
 * yazı tipi gerçekten indirilebiliyor mu, dosyası depoda mı, kaç bayt ve
 * nereden geliyor.
 *
 * Requirement ID'leri: DS-FONT-SELF-HOSTED-01, DS-FONT-NO-REMOTE-02,
 * DS-FONT-STACK-HONEST-03, DS-FONT-DISPLAY-04, DS-FONT-BUDGET-05.
 */
import { describe, expect, it } from 'vitest';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join, normalize, posix } from 'node:path';
import budget from './font-budget.json';

const CSS_ROOT = 'resources/css';

/** Depodaki bütün CSS kaynakları — AEP katmanı dâhil. */
function cssFiles(dir: string): string[] {
    const found: string[] = [];

    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);

        if (entry.isDirectory()) {
            found.push(...cssFiles(path));
        } else if (entry.name.endsWith('.css')) {
            found.push(path);
        }
    }

    return found.sort();
}

/*
    YORUMLAR ÖLÇÜMDEN ÇIKARILIR.

    `aep/tokens/fonts.css` kaldırılmış üç `@import` satırını yorum içinde
    KAYIT olarak saklıyor — ve bu doğru bir karar: kaldırılan şeyin ne olduğu
    görünür kalmalı. Yorumu tarayan bir ölçüm, bir kaydı bir ihlal sayardı.
*/
function withoutComments(css: string): string {
    /*
        TIRNAK İÇİNDEKİ `/*` BİR YORUM DEĞİLDİR.

        Ölçüldü: `app.css` içindeki `@source '.../*.blade.php'` bir GLOB'dur.
        Naif bir düzenli ifade onu yorum başlangıcı sayıyor ve dosyanın geri
        kalanını — `--font-sans` dâhil — sessizce yutuyordu. Ölçüm hata
        vermiyordu; yalnız "ölçülemedi" ile "ölçüldü ve geçti" aynı sonuca
        çıkıyordu ki bu, kırık bir kapının en tehlikeli hâlidir.
    */
    let out = '';
    let quote: string | null = null;

    for (let i = 0; i < css.length; i += 1) {
        const char = css[i];

        if (quote !== null) {
            out += char;
            if (char === '\\') {
                out += css[i + 1] ?? '';
                i += 1;
            } else if (char === quote) {
                quote = null;
            }
            continue;
        }

        if (char === '"' || char === "'") {
            quote = char;
            out += char;
            continue;
        }

        if (char === '/' && css[i + 1] === '*') {
            const end = css.indexOf('*/', i + 2);
            i = end === -1 ? css.length : end + 1;
            continue;
        }

        out += char;
    }

    return out;
}

type FontFace = {
    file: string;
    family: string;
    weight: string;
    display: string;
    sources: string[];
};

/** Bir `@font-face` bloğunun okunabilir hâli. */
function fontFaces(): FontFace[] {
    const faces: FontFace[] = [];

    for (const file of cssFiles(CSS_ROOT)) {
        const css = withoutComments(readFileSync(file, 'utf8'));

        for (const block of css.matchAll(/@font-face\s*\{([^{}]*)\}/g)) {
            const body = block[1];
            const read = (name: string): string =>
                (body.match(new RegExp(`${name}\\s*:\\s*([^;]+)`))?.[1] ?? '').trim();

            faces.push({
                file,
                family: read('font-family').replace(/['"]/g, ''),
                weight: read('font-weight'),
                display: read('font-display'),
                sources: [...body.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/g)].map((m) => m[1]),
            });
        }
    }

    return faces;
}

/** `url(...)` veya `@import url(...)` içindeki her dış adres. */
function remoteReferences(): { file: string; url: string }[] {
    const found: { file: string; url: string }[] = [];

    for (const file of cssFiles(CSS_ROOT)) {
        const css = withoutComments(readFileSync(file, 'utf8'));

        for (const match of css.matchAll(/url\(\s*['"]?((?:https?:)?\/\/[^'")]+)['"]?\s*\)/g)) {
            found.push({ file, url: match[1] });
        }

        for (const match of css.matchAll(/@import\s+['"]((?:https?:)?\/\/[^'"]+)['"]/g)) {
            found.push({ file, url: match[1] });
        }
    }

    return found;
}

/**
 * Bir `font-family` yığınındaki aileler, yazıldıkları sırayla.
 *
 * Yığın satır sonlarına bölünmüş olabilir; bu yüzden virgülden önce boşluklar
 * tekilleştirilir.
 */
function stackOf(declaration: string): string[] {
    return declaration
        .split(',')
        .map((part) => part.trim().replace(/['"]/g, '').replace(/\s+/g, ' '))
        .filter((part) => part !== '');
}

function declaredStack(name: string): string[] {
    for (const file of cssFiles(CSS_ROOT)) {
        const css = withoutComments(readFileSync(file, 'utf8'));
        // `(?<![\w-])`: `--font-sans` ararken `--aep-font-sans` eşleşmemeli.
        const match = css.match(new RegExp(`(?<![\\w-])${name}\\s*:\\s*([^;]+);`));

        if (match) return stackOf(match[1]);
    }

    return [];
}

/*
    YIĞINDA MEŞRU OLARAK BARINDIRILMAYAN ADLAR.

    İki grup var ve ikisi de bilinçli:

    1. GENERIC AİLELER — `system-ui`, `sans-serif` ve akrabaları bir dosya
       değil, "işletim sistemi ne veriyorsa" demektir. Yedeğin kendisidir.
    2. EMOJİ AİLELERİ — renkli emoji yazı tipleri onlarca megabayttır ve
       yalnız emoji glifi taşır; latin metnin ölçüsüne HİÇ karışmazlar.
       Onları barındırmak, çözdüğü sorunun yüz katı bayt maliyeti olurdu.

    Bu listede OLMAYAN her ad barındırılmak ZORUNDADIR: yüklenmeyen bir yazı
    tipi adı, yığında yalnız gürültüdür — okuyana "bu yazı tipi kullanılıyor"
    der, tarayıcıya hiçbir şey söylemez.
*/
const GENERIC_FAMILIES = new Set([
    'ui-sans-serif',
    'ui-serif',
    'ui-monospace',
    'ui-rounded',
    'system-ui',
    'sans-serif',
    'serif',
    'monospace',
    'cursive',
    'fantasy',
    'math',
    '-apple-system',
    'BlinkMacSystemFont',
    'SFMono-Regular',
    'Menlo',
]);

const EMOJI_FAMILIES = new Set([
    'Apple Color Emoji',
    'Segoe UI Emoji',
    'Segoe UI Symbol',
    'Noto Color Emoji',
]);

/** Depoda gerçekten kullanılan `font-weight` değerleri. */
const USED_WEIGHTS = [400, 500, 600, 700];

describe('yazı tipi barındırma', () => {
    // --- DS-FONT-SELF-HOSTED-01 -------------------------------------------

    it('yığının ilk ailesi depoda barındırılıyor', () => {
        const primary = declaredStack('--font-sans')[0];

        expect(primary, 'DS-FONT-SELF-HOSTED-01: `--font-sans` tanımlı değil.').toBeDefined();

        const hosted = fontFaces().filter((face) => face.family === primary);

        expect(
            hosted.length,
            `DS-FONT-SELF-HOSTED-01: \`--font-sans\` "${primary}" diyor ama depoda o aile için ` +
                'hiçbir `@font-face` yok. Adı yazılan ama indirilmeyen bir yazı tipi, her ' +
                'cihazda başka bir yazı tipi demektir.',
        ).toBeGreaterThan(0);
    });

    it('her `@font-face` dosyası gerçekten diskte', () => {
        const missing: string[] = [];

        for (const face of fontFaces()) {
            for (const source of face.sources) {
                if (/^(https?:)?\/\//.test(source) || source.startsWith('data:')) continue;

                const path = normalize(join(dirname(face.file), source.split('?')[0]));

                if (!existsSync(path)) missing.push(`${face.file} → ${source}`);
            }
        }

        expect(
            missing,
            'DS-FONT-SELF-HOSTED-01: bir `@font-face` var olmayan bir dosyaya işaret ediyor; ' +
                'tarayıcı sessizce yedeğe düşer.',
        ).toEqual([]);
    });

    it('barındırılan ağırlık aralığı depoda kullanılan her ağırlığı kapsıyor', () => {
        const primary = declaredStack('--font-sans')[0];
        const faces = fontFaces().filter((face) => face.family === primary);

        const uncovered = USED_WEIGHTS.filter(
            (weight) =>
                !faces.some((face) => {
                    const bounds = face.weight.match(/\d+/g)?.map(Number) ?? [];

                    if (bounds.length === 0) return false;
                    if (bounds.length === 1) return bounds[0] === weight;

                    return weight >= bounds[0] && weight <= bounds[1];
                }),
        );

        expect(
            uncovered,
            'DS-FONT-SELF-HOSTED-01: bu ağırlıklar kod tabanında kullanılıyor ama ' +
                'barındırılmıyor. Tarayıcı en yakın ağırlığa düşer ve `font-semibold` ' +
                'sessizce `font-bold` olarak çizilir.',
        ).toEqual([]);
    });

    // --- DS-FONT-NO-REMOTE-02 ---------------------------------------------

    it('hiçbir stil dosyası dış kaynaktan yazı tipi istemiyor', () => {
        expect(
            remoteReferences(),
            "DS-FONT-NO-REMOTE-02: üretimdeki CSP `font-src 'self' data:` diyor — dış " +
                'adres tarayıcı tarafından ENGELLENİR, yani bu satır çalışmaz. Ayrıca ' +
                'üçüncü taraf istek, ziyaretçinin IP adresini bize sormadan başkasına verir.',
        ).toEqual([]);
    });

    // --- DS-FONT-STACK-HONEST-03 ------------------------------------------

    it('yığında barındırılmayan hiçbir metin yazı tipi adı kalmıyor', () => {
        const hostedFamilies = new Set(fontFaces().map((face) => face.family));
        const noise: string[] = [];

        for (const token of ['--font-sans', '--aep-font-sans']) {
            for (const family of declaredStack(token)) {
                if (GENERIC_FAMILIES.has(family)) continue;
                if (EMOJI_FAMILIES.has(family)) continue;
                if (hostedFamilies.has(family)) continue;

                noise.push(`${token} → ${family}`);
            }
        }

        expect(
            noise,
            'DS-FONT-STACK-HONEST-03: bu aileler yığında duruyor ama barındırılmıyor. ' +
                'Ya barındırın ya çıkarın — okuyana bir şey vaat edip tarayıcıya hiçbir şey ' +
                'söylemeyen bir ad, ölçümü de okuru da yanıltır.',
        ).toEqual([]);
    });

    // --- DS-FONT-DISPLAY-04 -----------------------------------------------

    it('her `@font-face` bir `font-display` KARARI taşıyor', () => {
        const undecided = fontFaces()
            .filter((face) => face.display === '')
            .map((face) => `${face.file} → ${face.family}`);

        expect(
            undecided,
            'DS-FONT-DISPLAY-04: `font-display` yazılmazsa tarayıcı `auto` uygular ve bu, ' +
                'metnin görünmeden BEKLEYECEĞİ anlamına gelir. Bekleyen metin bir karardır; ' +
                'kararsızlıkla verilmemeli.',
        ).toEqual([]);
    });

    // --- DS-FONT-BUDGET-05 ------------------------------------------------

    it('barındırılan yazı tipi baytları bütçeyi aşmıyor', () => {
        const files = new Map<string, number>();

        for (const face of fontFaces()) {
            for (const source of face.sources) {
                if (/^(https?:)?\/\//.test(source) || source.startsWith('data:')) continue;

                const path = normalize(join(dirname(face.file), source.split('?')[0]));

                if (existsSync(path)) files.set(path, statSync(path).size);
            }
        }

        const totalKb = [...files.values()].reduce((sum, size) => sum + size, 0) / 1024;

        expect(
            totalKb,
            `DS-FONT-BUDGET-05: barındırılan yazı tipleri ${totalKb.toFixed(1)} KB — bütçe ` +
                `${budget.maxHostedKb} KB. Bütçeyi yükseltmeden önce alt kümeye bakın: ` +
                'kullanılmayan bir yazı sistemi, hiç açılmayacak bir kapıdır.',
        ).toBeLessThanOrEqual(budget.maxHostedKb);
    });

    it('ilk boyamada indirilen dosya küçük kalıyor', () => {
        const path = budget.firstPaintFile;

        expect(existsSync(path), `DS-FONT-BUDGET-05: ${path} yok.`).toBe(true);

        const kb = statSync(path).size / 1024;

        expect(
            kb,
            `DS-FONT-BUDGET-05: ilk boyama dosyası ${kb.toFixed(1)} KB — bütçe ` +
                `${budget.maxFirstPaintKb} KB. Bu dosya preload ediliyor, yani her ` +
                'ziyaretçi onu KESİNLİKLE indirir.',
        ).toBeLessThanOrEqual(budget.maxFirstPaintKb);
    });

    it('yalnız tek bir dosya preload ediliyor', () => {
        const partial = readFileSync(budget.preloadPartial, 'utf8');
        const preloaded = [...partial.matchAll(/rel=["']preload["']/g)];

        expect(
            preloaded.length,
            'DS-FONT-BUDGET-05: her şeyi preload etmek preload fikrini anlamsız kılar — ' +
                'tarayıcıya "hepsi en önemli" demek, "hiçbiri" demektir. Yalnız ilk ' +
                'boyamada gerçekten gereken dosya preload edilir.',
        ).toBe(1);

        expect(
            partial.includes(posix.basename(budget.firstPaintFile)),
            `DS-FONT-BUDGET-05: preload edilen dosya ${budget.firstPaintFile} olmalı.`,
        ).toBe(true);
    });

    // --- Kaynak kaydı ------------------------------------------------------

    it('barındırılan her yazı tipinin lisansı ve kaynağı kayıtlı', () => {
        expect(
            existsSync(budget.licenseFile),
            `DS-FONT-SELF-HOSTED-01: ${budget.licenseFile} yok. Bir yazı tipini yeniden ` +
                'dağıtıyoruz; lisans metni onunla birlikte taşınmak ZORUNDA.',
        ).toBe(true);

        const provenance = readFileSync(budget.provenanceFile, 'utf8');

        for (const field of ['@fontsource-variable/roboto', 'OFL-1.1']) {
            expect(
                provenance.includes(field),
                `DS-FONT-SELF-HOSTED-01: kaynak kaydında "${field}" geçmiyor. Nereden ` +
                    'geldiği yazılmayan bir ikili dosya, bir gün kimsenin güncelleyemediği ' +
                    'bir dosyadır.',
            ).toBe(true);
        }
    });
});
