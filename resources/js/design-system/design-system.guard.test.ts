import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { themeScope } from './cssSources';
import { RAW_PALETTE_PATTERN, findCycle, layerOf, mayCompose, type Layer } from './semantic-map';
import {
    WCAG_AA_NORMAL_TEXT,
    compositeOver,
    contrastRatio,
    oklchToLinearRgb,
    readCustomProperties,
    resolveColor,
    resolveColorWithAlpha,
} from './contrast';

/**
 * Tasarım sisteminin zorlayıcı kontrolü.
 *
 * docs/35 sözleşmesi doğru yazılmıştı ama hiçbir şey onu uygulatmıyordu:
 * referans micro'nun kendisi bile ham palet kullanıyordu ve 137 bileşen
 * dosyasının 90'ı semantic katmanı atlıyordu. Belge tek başına bir sistemi
 * ayakta tutmuyor — bu dosya sözleşmeyi build'i kıran kurallara çevirir.
 *
 * Requirement ID'leri: DS-RATCHET-01, DS-LAYER-DIRECTION-01,
 * DS-TOKEN-INTEGRITY-01, DS-NO-RAW-HEX-01, DS-STORY-COVERAGE-01.
 */

/**
 * Kural artık `resources/js` kökünün TAMAMINI tarar.
 *
 * Önceden yalnız `components/` taranıyordu ve bu bir delikti: `auth.tsx`
 * gibi kök seviyedeki dosyalar hiçbir kurala tabi değildi ve on bir ham
 * palet sınıfı taşıyordu. Bir tasarım sistemi, kapsamadığı dosyada
 * geçerli değildir.
 *
 * Aynı delik uzantı tarafında da vardı: yalnız `.tsx` taranıyordu. Sınıf
 * listesini en yoğun taşıyan dosya JSX İÇERMEZ, yani `.ts`tir — Flowbite
 * tema bağlaması (`design-system/flowbite-theme.ts`). Yasak MUTLAK olduğuna
 * göre kapsamı da mutlak olmalı: `.tsx` tarayan bir kural, sınıfların en
 * yoğun toplandığı yere tam olarak kördü.
 *
 * KATMAN kuralları ise `.tsx` ile sınırlı kalır. Bir katman kuralı bir
 * BİLEŞEN hakkında konuşur; stil sabiti dışa aktaran bir `.ts` modülünün
 * story'si olamaz. Bu ayrım olmadan `DS-STORY-COVERAGE-01` sessizce
 * "geçerdi": aradığı story yolunu üretemeyip dosyanın KENDİSİNİ okur ve
 * kendi kendini story sanardı — yani kural, kapsamadığı yerde geçtiğini
 * sanan bir kurala dönüşürdü.
 */
const COMPONENT_ROOT = 'resources/js';
const CSS_PATH = 'resources/css/app.css';

type SourceFile = { path: string; body: string; layer: Layer | null };

function collect(dir: string, out: SourceFile[] = []): SourceFile[] {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            collect(path, out);
        } else if (
            /\.tsx?$/.test(entry.name) &&
            !/\.(test|stories)\.tsx?$/.test(entry.name) &&
            !entry.name.endsWith('.d.ts')
        ) {
            out.push({ path, body: readFileSync(path, 'utf8'), layer: layerOf(path) });
        }
    }
    return out;
}

function collectStories(dir: string, out: SourceFile[] = []): SourceFile[] {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            collectStories(path, out);
        } else if (entry.name.endsWith('.stories.tsx')) {
            out.push({ path, body: readFileSync(path, 'utf8'), layer: layerOf(path) });
        }
    }
    return out;
}

const FILES = collect(COMPONENT_ROOT);
const LAYERED = FILES.filter(
    (f) => f.path.endsWith('.tsx') && f.layer !== null && f.layer !== 'surface',
);

describe('tasarım sistemi — zorlayıcı kontrol', () => {
    // --- DS-RAW-PALETTE-BANNED-01 ----------------------------------------
    // Bu kural bir cırcır (ratchet) olarak başladı: 895 ihlal vardı ve sayı
    // yalnız azalabiliyordu. 2026-08-26'da sıfıra indi, ve borç dosyasının
    // kendi notu ne yapılacağını söylüyordu: "Borç sıfırlandığında bu dosya
    // ve ilgili test kaldırılır, yerine MUTLAK YASAK gelir."
    //
    // Artık tek bir ham palet sınıfı bile kabul edilmez. Eşik yok, taban
    // çizgisi yok, "sonra düzeltiriz" yok — çünkü bir istisna verildiği anda
    // sayaç yeniden yürümeye başlar.
    it('hiçbir dosya ham Tailwind paleti kullanmaz', () => {
        const offending: string[] = [];

        for (const file of FILES) {
            const found = file.body.match(RAW_PALETTE_PATTERN);
            if (found) {
                offending.push(`${file.path}: ${[...new Set(found)].join(' ')}`);
            }
        }

        expect(
            offending,
            'DS-RAW-PALETTE-BANNED-01: ham palet sınıfı tasarım sistemini atlar ve tema ' +
                'değiştiğinde sessizce yanlış görünür. Semantic token kullanın ' +
                '(text-fg-secondary, border-border-danger, bg-surface-hover, outline-focus …):\n' +
                offending.join('\n'),
        ).toEqual([]);
    });

    // --- DS-LAYER-DIRECTION-01 -------------------------------------------
    it('kompozisyon yalnız aşağı doğru akar: micro compound/macro import etmez', () => {
        const breaches: string[] = [];

        for (const file of LAYERED) {
            const imports = [...file.body.matchAll(/from\s+'([^']+)'/g)].map((m) => m[1]);

            for (const specifier of imports) {
                if (!specifier.startsWith('.')) continue;

                const target = layerOf(join(file.path, '..', specifier));
                if (target === null) continue;

                if (!mayCompose(file.layer as Layer, target)) {
                    breaches.push(`${file.path} (${file.layer}) -> ${specifier} (${target})`);
                }
            }
        }

        expect(
            breaches,
            'DS-LAYER-DIRECTION-01: bir katman kendinden üstteki (veya aynı) katmanı compose ediyor. ' +
                'Bu, master component fikrini bozar ve döngüsel bağımlılık kapısını açar:\n' +
                breaches.join('\n'),
        ).toEqual([]);
    });

    // --- DS-NO-CYCLE-03 ---------------------------------------------------
    // Yatay yasağın gerçekte koruduğu şey budur: bir döngü, "hangisi master"
    // sorusunu cevapsız bırakır ve yükleme sırasına bağlı, teşhisi zor
    // hatalar üretir.
    it('katmanlı bileşenler arasında import döngüsü yoktur', () => {
        const graph = new Map<string, string[]>();

        for (const file of LAYERED) {
            const targets: string[] = [];

            for (const [, specifier] of file.body.matchAll(/from\s+'(\.[^']+)'/g)) {
                const resolved = join(file.path, '..', specifier);
                const match = LAYERED.find(
                    (candidate) => candidate.path.replace(/\.tsx$/, '') === resolved,
                );
                if (match) targets.push(match.path);
            }

            graph.set(file.path, targets);
        }

        const cycle = findCycle(graph);

        expect(
            cycle,
            'DS-NO-CYCLE-03: bileşenler arası import döngüsü:\n' + (cycle ?? []).join('\n  -> '),
        ).toBeNull();
    });

    // --- DS-TOKEN-INTEGRITY-01 -------------------------------------------
    it('her yayınlanan semantic token ham bir değere bağlıdır ve karanlık teması vardır', () => {
        const css = readFileSync(CSS_PATH, 'utf8');
        const theme = css.match(/@theme\s*\{([\s\S]*?)\n\}/)?.[1] ?? '';
        const darkBlock = css.match(/\.dark\s*\{([\s\S]*?)\n\}/)?.[1] ?? '';
        const rootBlock = css.match(/:root\s*\{([\s\S]*?)\n\}/)?.[1] ?? '';

        const aliases = [...theme.matchAll(/--color-[a-z0-9-]+:\s*var\((--[a-z0-9-]+)\)/g)].map(
            (m) => m[1],
        );

        expect(
            aliases.length,
            'DS-TOKEN-INTEGRITY-01: @theme hiç semantic token yayınlamıyor.',
        ).toBeGreaterThan(0);

        const undefinedRaw = aliases.filter((raw) => !rootBlock.includes(`${raw}:`));
        expect(
            undefinedRaw,
            `DS-TOKEN-INTEGRITY-01: @theme'de yayınlanan token :root'ta tanımsız: ${undefinedRaw.join(', ')}`,
        ).toEqual([]);

        // Tema-DEĞİŞMEZ token'lar. Marka rengi kasıtlı olarak sabittir: marka
        // kimliği aydınlık ile karanlık arasında kayarsa marka olmaktan çıkar.
        // Ayrıca `docs/06` §11 `#ffb900`'ü TEK primitive olarak dondurur, yani
        // `.dark` içinde tekrar tanımlanması sözleşme ihlalidir — bu liste,
        // kuralın o sözleşmeyi zorlamak yerine ona uymasını sağlar.
        const THEME_INVARIANT = ['--color-brand-500'];

        // Sistem renkleri (CanvasText vb.) tema değiştirmez; onları hariç tut.
        const needsDark = aliases.filter((raw) => {
            if (THEME_INVARIANT.includes(raw)) return false;
            const value = rootBlock.match(new RegExp(`${raw}:\\s*([^;]+);`))?.[1] ?? '';
            return /oklch|#|rgb|color-mix/.test(value);
        });

        const missingDark = needsDark.filter((raw) => !darkBlock.includes(`${raw}:`));
        expect(
            missingDark,
            `DS-TOKEN-INTEGRITY-01: karanlık temada karşılığı olmayan token: ${missingDark.join(', ')}. ` +
                'Yarım tanımlı token, karanlık temada okunmaz metin üretir.',
        ).toEqual([]);
    });

    // --- DS-CONTRAST-AA-01 ------------------------------------------------
    // Token değerleri elle seçilir ve göz onları doğrulayamaz. `--fg-subtle`
    // açık temada 2.88:1 olarak yayınlandı — AA'nın, hatta büyük-metin
    // eşiğinin bile altında — ve hiçbir şey fark etmedi. Bu test o sessizliği
    // kapatır.
    it("her metin token'ı kendi temasının zemininde WCAG AA karşılar", () => {
        const css = readFileSync(CSS_PATH, 'utf8');

        // Hesabın kendisi doğrulanmalı: siyah/beyaz tam 21:1 vermelidir.
        // Bu satır olmadan bozuk bir formül sessizce "hepsi geçti" der.
        expect(
            contrastRatio(oklchToLinearRgb(0, 0, 0), oklchToLinearRgb(1, 0, 0)),
            'DS-CONTRAST-AA-01: kontrast formülü kalibre değil.',
        ).toBeCloseTo(21, 1);

        /*
            HARMANLAMA DA KALİBRE EDİLMELİ (FF-125).

            Tarayıcı saydamlığı GAMMA KODLU sRGB'de harmanlar. Lineer ışıkta
            harmanlayan bir ölçüm, ekranda gayet okunur olan bir metni
            "okunmuyor" diye reddeder: %66 opak ink beyaz üstünde lineer
            harmanlanınca 2.68:1, gerçekte 6.6:1 verir.

            %50 siyah beyaz üstünde tam olarak 0.5 kodlanmış değer verir;
            oradan hesaplanan oran 3.98:1'dir. Bu satır olmadan yanlış uzayda
            harmanlayan bir formül sessizce "hepsi geçti" ya da "hepsi kaldı"
            der.
        */
        expect(
            contrastRatio(
                compositeOver(
                    { rgb: oklchToLinearRgb(0, 0, 0), alpha: 0.5 },
                    oklchToLinearRgb(1, 0, 0),
                ),
                oklchToLinearRgb(1, 0, 0),
            ),
            'DS-CONTRAST-AA-01: saydam harmanlama yanlış uzayda yapılıyor.',
        ).toBeCloseTo(3.98, 1);

        const failures: string[] = [];

        for (const [theme, selector] of [
            ['açık', ':root'],
            ['karanlık', '.dark'],
        ] as const) {
            /*
                Jetonlar İKİ katmanda (FF-131): ham değerler AEP paketinde,
                takma adlar `app.css`'te. Tek katman okuyan bir ölçüm
                `var(--aep-*)` metnini renk sanıp çözemez ve "ölçülemedi"
                sessizce "geçti"ye dönüşürdü.
            */
            const scope = themeScope(selector, readCustomProperties);
            const background = resolveColor(scope['--surface'] ?? '', scope);

            expect(
                background,
                `DS-CONTRAST-AA-01: ${theme} temada --surface çözülemedi.`,
            ).not.toBeNull();

            for (const [token, value] of Object.entries(scope)) {
                if (!token.startsWith('--fg')) continue;

                /*
                    SAYDAM METİN ZEMİNE YERLEŞTİRİLEREK ölçülür (FF-125).

                    AEP merdiveni ikincil metni `rgb(8 6 22 / 66%)` yazıyor.
                    Saydamlığı düşürüp ölçmek 19:1 verir; beyaz zeminin üstünde
                    gerçek değer ~5:1'dir. Daha kötüsü: çözemediği bir değeri bu
                    döngü sessizce ATLIYORDU, yani jeton yazımı değişince kapı
                    ölçmeyi bırakır ve hiç kimse fark etmezdi.
                */
                const resolved = resolveColorWithAlpha(value, scope);
                if (resolved === null || background === null) continue;

                const foreground = compositeOver(resolved, background);

                const ratio = contrastRatio(foreground, background);
                if (ratio < WCAG_AA_NORMAL_TEXT) {
                    failures.push(`${theme}: ${token} = ${ratio.toFixed(2)}:1`);
                }
            }
        }

        expect(
            failures,
            "DS-CONTRAST-AA-01: metin token'ı zemininde 4.5:1 altında kaldı — " +
                'bu, okunmayan metin demektir:\n' +
                failures.join('\n'),
        ).toEqual([]);
    });

    // --- DS-STORY-TAXONOMY-04 ---------------------------------------------
    // docs/35 §7: story kökü icat edilmez. Yeni bir kök, Storybook'u bir
    // gezinti ağacından bir çöplüğe çevirir ve katman modelini görünmez kılar.
    it('story kökleri docs/35 §7 taksonomisinin dışına çıkmaz', () => {
        const allowed = ['Micro', 'Compound', 'Macro', 'Surface'];
        const offenders: string[] = [];

        for (const dir of ['resources/js/components']) {
            for (const file of collectStories(dir)) {
                const meta = /const meta[\s\S]*?title:\s*'([^']+)'/.exec(file.body);
                if (!meta) continue;

                const root = meta[1].split('/')[0];
                if (!allowed.includes(root)) {
                    offenders.push(`${file.path}: '${root}/'`);
                }
            }
        }

        expect(
            offenders,
            `DS-STORY-TAXONOMY-04: izinli kökler yalnız ${allowed.join(', ')}. ` +
                'İcat edilen kök:\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    // --- DS-DENSITY-CONTRACT-05 -------------------------------------------
    // Külliyatın yoğunluk kararı iki maddeyle donuk: satır yüksekliği
    // height + padding ile değişir ASLA font-size ile değil, ve dokunma
    // hedefi hiçbir modda küçülmez. Birincisi ihlal edilirse kompakt mod
    // okunmaz metin üretir; ikincisi ihlal edilirse dokunulamaz kontrol.
    it('yoğunluk modları tipografiye dokunmaz ve dokunma hedefini küçültmez', () => {
        const css = readFileSync(CSS_PATH, 'utf8');
        const root = readCustomProperties(css, ':root');
        const comfortable = readCustomProperties(css, ":root[data-density='comfortable']");
        const compact = readCustomProperties(css, ":root[data-density='compact']");

        const px = (value: string | undefined): number => parseFloat(value ?? 'NaN');

        expect(
            [
                px(comfortable['--density-row-height']),
                px(root['--density-row-height']),
                px(compact['--density-row-height']),
            ],
            'DS-DENSITY-CONTRACT-05: üç yoğunluk modu da satır yüksekliği tanımlamalı.',
        ).not.toContain(NaN);

        expect(
            px(comfortable['--density-row-height']),
            "DS-DENSITY-CONTRACT-05: comfortable, standard'dan yüksek olmalı.",
        ).toBeGreaterThan(px(root['--density-row-height']));

        expect(
            px(compact['--density-row-height']),
            "DS-DENSITY-CONTRACT-05: compact, standard'dan alçak olmalı.",
        ).toBeLessThan(px(root['--density-row-height']));

        // Dokunma hedefi: modlar onu yeniden tanımlamamalı; tanımlarsa
        // taban değerin altına inemez.
        for (const [name, scope] of [
            ['comfortable', comfortable],
            ['compact', compact],
        ] as const) {
            const override = scope['--density-hit-area-min'];
            if (override === undefined) continue;

            expect(
                px(override),
                `DS-DENSITY-CONTRACT-05: ${name} modu dokunma hedefini küçültüyor.`,
            ).toBeGreaterThanOrEqual(px(root['--density-hit-area-min']));
        }

        // Tipografi yoğunluğun konusu değildir.
        for (const [name, scope] of [
            ['comfortable', comfortable],
            ['compact', compact],
        ] as const) {
            const typography = Object.keys(scope).filter((token) =>
                /font|line-height|letter-spacing/.test(token),
            );

            expect(
                typography,
                `DS-DENSITY-CONTRACT-05: ${name} modu tipografi token'ı tanımlıyor. ` +
                    'Yoğunluk height ve padding ile çözülür; font-size ile değil.',
            ).toEqual([]);
        }
    });

    // --- DS-LOGICAL-DIRECTION-06 ------------------------------------------
    // Külliyat RTL-native bir sistem şart koşar. Fiziksel yön sınıfı Arapça
    // gibi sağdan-sola dillerde arayüzü SESSİZCE bozar: hata vermez, yalnız
    // yanlış tarafa hizalar.
    //
    // Bu da bir cırcır olarak başladı ve 2026-08-26'da sıfıra indi; artık
    // mutlak yasaktır. Mantıksal karşılıkları kullanın: ms-/me-/ps-/pe-/
    // text-start/text-end.
    it('hiçbir dosya fiziksel yön sınıfı kullanmaz', () => {
        const physical = /\b(ml|mr|pl|pr)-[0-9a-z]+|text-(left|right)\b/g;
        const offenders: string[] = [];

        for (const file of FILES) {
            const found = file.body.match(physical);
            if (found) {
                offenders.push(`${file.path}: ${[...new Set(found)].join(' ')}`);
            }
        }

        expect(
            offenders,
            'DS-LOGICAL-DIRECTION-06: fiziksel yön sınıfı sağdan-sola dillerde arayüzü ' +
                'sessizce yanlış tarafa hizalar. Mantıksal karşılığını kullanın ' +
                '(ms-/me-/ps-/pe-/text-start/text-end):\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    // --- DS-NO-UPPERCASE-12 -----------------------------------------------
    // Hiçbir etiket CSS ile BÜYÜK HARFE çevrilmez.
    //
    // Bu bir zevk kuralı değil, bir TÜRKÇE kuralıdır. `text-transform:
    // uppercase` küçük i'yi Türkçede İ'ye çevirmek zorundadır ve bunu
    // yalnız öğenin dili doğru bildirilmişse yapar. Zabuno'nun panelinde
    // dil kullanıcıya göre değişir; "işletme" etiketi bir tarayıcıda
    // "İŞLETME", diğerinde "ISLETME" okunur — ürünün kendi dili
    // rastgeleleşir. Aynı şey ı/I çiftinde ters yönde olur.
    //
    // İkinci sebep okunurluk: büyük harf sözcüğün siluetini düzleştirir ve
    // tarama hızını düşürür.
    //
    // AEP karşılığı: hiyerarşi büyük harfle değil AĞIRLIK ve RENKLE kurulur
    // (`font-semibold` + `--fg-muted`). Harf aralığı (`tracking`) de büyük
    // harfin telafisiydi; onunla birlikte gider.
    it('hiçbir etiket CSS ile büyük harfe çevrilmez', () => {
        const offenders: string[] = [];

        for (const file of FILES) {
            if (/\buppercase\b/.test(file.body)) {
                offenders.push(file.path);
            }
        }

        expect(
            offenders,
            'DS-NO-UPPERCASE-12: CSS ile büyük harfe çevirme, Türkçede i/İ ve ı/I ' +
                'eşlemesini tarayıcının dil tahminine bırakır. Hiyerarşiyi ağırlık ve ' +
                'renkle kurun (font-semibold + text-fg-muted):\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    // --- DS-TEXT-ROLE-EXISTS-13 -------------------------------------------
    // Yazılan her rol adlı yazı sınıfının CSS'te bir karşılığı OLMALIDIR.
    //
    // 2026-09-04'te `text-caption` 24 yerde yazılıydı ve `app.css` içinde
    // `--text-caption` diye bir jeton YOKTU. Tailwind var olmayan bir jeton
    // için sınıf üretmez; derlenmiş CSS'te tek bir `text-caption` kuralı
    // bulunmuyordu. Yani yirmi dört yer boyut seçtiğini SANIYORDU ve
    // aslında ebeveyninin boyutunu miras alıyordu — hata vermeden, gözle de
    // fark edilmeden.
    //
    // Bir yazı tipi ölçeğinin değeri, dışında kalınamamasındadır. Uydurulmuş
    // bir rol adı ölçeği delmez, ölçeği GÖRÜNMEZ kılar.
    it('yazılan her rol adlı yazı sınıfının CSS karşılığı vardır', () => {
        const css = readFileSync(CSS_PATH, 'utf8');
        const defined = new Set(
            [...css.matchAll(/--text-([a-z0-9-]+):/g)]
                .map((match) => match[1])
                .filter((name) => !name.includes('--')),
        );

        // Bilinen yazı-DIŞI `text-*` aileleri: renk, hizalama, sarma ve
        // dekorasyon aynı önekle başlar ama yazı ölçeğine ait değildir.
        const notASize =
            /^(fg|action|surface|canvas|brand|white|black|start|end|center|justify|left|right|wrap|nowrap|balance|pretty|ellipsis|clip|inherit|current|transparent|top|bottom|middle)/;

        const used = new Map<string, Set<string>>();

        for (const file of FILES) {
            // Eşleşme bir tireden SONRA gelemez: `--color-text-muted` bir
            // değişken adıdır, `text-muted` sınıfı değil. Bu ayrım olmadan
            // kural kendi uydurduğu ihlalleri raporlardı.
            for (const [, role] of file.body.matchAll(/(?<![\w-])text-([a-z][a-z-]*[a-z])\b/g)) {
                if (defined.has(role) || notASize.test(role)) continue;

                if (!used.has(role)) used.set(role, new Set());
                used.get(role)!.add(file.path);
            }
        }

        const ghosts = [...used.keys()];

        expect(
            ghosts,
            'DS-TEXT-ROLE-EXISTS-13: CSS karşılığı olmayan rol adlı yazı sınıfı. ' +
                'Tailwind bu sınıf için hiçbir kural üretmez; öğe ebeveyninin boyutunu ' +
                'sessizce miras alır. Ya jetonu `app.css` içinde tanımlayın ya da var ' +
                `olan bir rolü kullanın (${[...defined].join(', ')}):\n` +
                ghosts.map((role) => `text-${role}: ${[...used.get(role)!].join(', ')}`).join('\n'),
        ).toEqual([]);

        /*
            AYNI SESSİZ HATANIN İKİNCİ BİÇİMİ: `text-[var(--olmayan-jeton)]`.

            `ProviderCredentialsPage` üç yerde `--color-text-muted` yazıyordu;
            o değişken `app.css` içinde HİÇ tanımlı değil. Tailwind sınıfı
            üretir, tarayıcı değişkeni çözemez, metin rengi ebeveyninden
            gelir. Rol adı uydurmakla değişken adı uydurmak aynı kapıya
            çıkar; kural ikisini birlikte kapatır.
        */
        const declared = new Set([...css.matchAll(/(--[a-z0-9-]+):/g)].map((match) => match[1]));
        const danglingVars: string[] = [];

        for (const file of FILES) {
            for (const [, variable] of file.body.matchAll(/\[var\((--[a-z0-9-]+)[),]/g)) {
                if (!declared.has(variable)) danglingVars.push(`${file.path}: var(${variable})`);
            }
        }

        expect(
            [...new Set(danglingVars)],
            'DS-TEXT-ROLE-EXISTS-13: `app.css` içinde tanımlı olmayan bir CSS ' +
                'değişkenine yapılan başvuru. Tarayıcı değeri çözemez ve özellik ' +
                'sessizce miras alınır:\n' +
                [...new Set(danglingVars)].join('\n'),
        ).toEqual([]);
    });

    // --- DS-MOTION-CONTRACT-08 --------------------------------------------
    // İki şart: bileşen ham süre bilmez (borç şu an sıfır, bu yüzden cırcır
    // değil yasak), ve her süre token'ının azaltılmış-hareket karşılığı
    // vardır. Vestibüler rahatsızlığı olan bir kullanıcı için bu bir tercih
    // değil, kullanılabilirlik şartıdır.
    it('bileşen ham süre bilmez ve her süre azaltılmış harekette yanıtlanır', () => {
        const hardcoded: string[] = [];

        for (const file of FILES) {
            // `duration-[var(--duration-*)]` token'ı TÜKETMEKTİR, ihlal değil —
            // ilk hâlinde bu kural onu da yasaklıyordu, yani doğru kullanımı
            // cezalandırıyordu. Yasak ham değerlere: `150ms`, `duration-150`,
            // ve `var()` içermeyen arbitrary süreler.
            const found = file.body.match(
                /\b\d+ms\b|\bduration-\d+\b|duration-\[(?!var\()|cubic-bezier\(/g,
            );
            if (found) hardcoded.push(`${file.path}: ${found.join(', ')}`);
        }

        expect(
            hardcoded,
            'DS-MOTION-CONTRACT-08: bileşene gömülü süre/easing. ' +
                "Motion token'ını kullanın (--duration-*, --easing-standard):\n" +
                hardcoded.join('\n'),
        ).toEqual([]);

        const css = readFileSync(CSS_PATH, 'utf8');
        const durations = Object.keys(readCustomProperties(css, ':root')).filter((token) =>
            token.startsWith('--duration-'),
        );

        expect(
            durations.length,
            "DS-MOTION-CONTRACT-08: hiç süre token'ı tanımlı değil.",
        ).toBeGreaterThan(0);

        // Blok satır başındaki `}` ile kapanır; iç `:root { … }` girintilidir.
        const reduced = /@media \(prefers-reduced-motion: reduce\)\s*\{([\s\S]*?)\n\}/.exec(css);

        expect(reduced, 'DS-MOTION-CONTRACT-08: azaltılmış-hareket bloğu yok.').not.toBeNull();

        const unanswered = durations.filter((token) => !(reduced?.[1] ?? '').includes(`${token}:`));

        expect(
            unanswered,
            'DS-MOTION-CONTRACT-08: azaltılmış harekette karşılığı olmayan süre: ' +
                unanswered.join(', '),
        ).toEqual([]);
    });

    // --- DS-DENSITY-CONSUMED-09 -------------------------------------------
    // Tanımlanmış ama tüketilmeyen bir token, sistem değil süstür. Yoğunluk
    // modları CSS'te var olup hiçbir bileşen okumazsa anahtarı çevirmek
    // hiçbir şeyi değiştirmez — tam olarak bu durumdaydık.
    it("yoğunluk token'ları en az bir gerçek bileşen tarafından tüketilir", () => {
        const consumers = FILES.filter((file) => /var\(--density-/.test(file.body));

        expect(
            consumers.map((file) => file.path),
            'DS-DENSITY-CONSUMED-09: hiçbir bileşen --density-* okumuyor. ' +
                'Yoğunluk modları tanımlı ama bağlı değil; anahtar hiçbir şeyi değiştirmez.',
        ).not.toEqual([]);

        // Yoğunluk satır yüksekliği VE iç boşlukla çözülür; yalnız biri
        // bağlanırsa mod değişimi yarım kalır.
        const body = consumers.map((file) => file.body).join('\n');

        for (const token of ['--density-row-height', '--density-padding-inline']) {
            expect(
                body.includes(token),
                `DS-DENSITY-CONSUMED-09: ${token} hiçbir bileşende tüketilmiyor.`,
            ).toBe(true);
        }
    });

    // --- DS-NO-RAW-HEX-01 -------------------------------------------------
    it('katmanlı bileşenler ham hex rengi gömmez', () => {
        const offenders = LAYERED.filter((file) => /#[0-9a-fA-F]{3,8}\b/.test(file.body)).map(
            (f) => f.path,
        );

        expect(
            offenders,
            'DS-NO-RAW-HEX-01: ham hex, temayı ve yüksek kontrast modunu tamamen atlar:\n' +
                offenders.join('\n'),
        ).toEqual([]);
    });

    // --- DS-STORY-COVERAGE-01 ---------------------------------------------
    it('her katmanlı bileşenin bir story dosyası vardır', () => {
        const missing = LAYERED.filter((file) => {
            const story = file.path.replace(/\.tsx$/, '.stories.tsx');
            try {
                readFileSync(story);
                return false;
            } catch {
                return true;
            }
        }).map((f) => f.path);

        expect(
            missing,
            'DS-STORY-COVERAGE-01: story olmadan bileşen izole olarak görülemez ve ' +
                'kompozisyon bozulduğunda kimse fark etmez:\n' +
                missing.join('\n'),
        ).toEqual([]);
    });
});
