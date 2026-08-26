import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import directionDebt from './direction-debt.json';
import { RAW_PALETTE_PATTERN, findCycle, layerOf, mayCompose, type Layer } from './semantic-map';
import {
    WCAG_AA_NORMAL_TEXT,
    contrastRatio,
    oklchToLinearRgb,
    readCustomProperties,
    resolveColor,
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
 */
const COMPONENT_ROOT = 'resources/js';
const CSS_PATH = 'resources/css/app.css';

type SourceFile = { path: string; body: string; layer: Layer | null };

function collect(dir: string, out: SourceFile[] = []): SourceFile[] {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        const path = join(dir, entry.name);
        if (entry.isDirectory()) {
            collect(path, out);
        } else if (entry.name.endsWith('.tsx') && !/\.(test|stories)\.tsx$/.test(entry.name)) {
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
const LAYERED = FILES.filter((f) => f.layer !== null && f.layer !== 'surface');

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

        const root = readCustomProperties(css, ':root');
        const failures: string[] = [];

        for (const [theme, selector] of [
            ['açık', ':root'],
            ['karanlık', '.dark'],
        ] as const) {
            const scope = { ...root, ...readCustomProperties(css, selector) };
            const background = resolveColor(scope['--surface'] ?? '', scope);

            expect(
                background,
                `DS-CONTRAST-AA-01: ${theme} temada --surface çözülemedi.`,
            ).not.toBeNull();

            for (const [token, value] of Object.entries(scope)) {
                if (!token.startsWith('--fg')) continue;

                const foreground = resolveColor(value, scope);
                if (foreground === null || background === null) continue;

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
        const comfortable = readCustomProperties(css, '.density-comfortable');
        const compact = readCustomProperties(css, '.density-compact');

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
    // yanlış tarafa hizalar. Borç düşebilir, yükselemez.
    it('fiziksel yön sınıfı borcu taban çizgisini aşmaz', () => {
        const physical = /\b(ml|mr|pl|pr)-[0-9a-z]+|text-(left|right)\b/g;
        const offenders: string[] = [];
        let count = 0;

        for (const file of FILES) {
            const found = file.body.match(physical);
            if (found) {
                count += found.length;
                offenders.push(`${file.path} (${found.length})`);
            }
        }

        expect(
            count,
            `DS-LOGICAL-DIRECTION-06: fiziksel yön kullanımı ${directionDebt.maxPhysicalDirectionClasses} ` +
                `taban çizgisinden ${count}'e yükseldi. Logical karşılığını kullanın ` +
                `(${directionDebt.$replaceWith}):\n` +
                offenders.join('\n'),
        ).toBeLessThanOrEqual(directionDebt.maxPhysicalDirectionClasses);
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
