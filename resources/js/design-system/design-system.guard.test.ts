import { describe, expect, it } from 'vitest';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import debt from './raw-palette-debt.json';
import { RAW_PALETTE_PATTERN, layerOf, mayCompose, type Layer } from './semantic-map';

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

const COMPONENT_ROOT = 'resources/js/components';
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

const FILES = collect(COMPONENT_ROOT);
const LAYERED = FILES.filter((f) => f.layer !== null && f.layer !== 'surface');

describe('tasarım sistemi — zorlayıcı kontrol', () => {
    // --- DS-RATCHET-01 ---------------------------------------------------
    it('ham palet borcu taban çizgisini aşmaz (yalnız azalabilir)', () => {
        let violations = 0;
        const offending: string[] = [];

        for (const file of FILES) {
            const found = file.body.match(RAW_PALETTE_PATTERN);
            if (found) {
                violations += found.length;
                offending.push(file.path);
            }
        }

        expect(
            violations,
            `DS-RATCHET-01: ham palet kullanımı ${debt.maxViolations} taban çizgisinden ${violations}'e YÜKSELDİ. ` +
                'Semantic token kullanın (text-fg-secondary, border-border-danger, ring-focus …). ' +
                'Taban çizgisini yükseltmeyin — o, sistemi atlayan kodu kalıcılaştırır.',
        ).toBeLessThanOrEqual(debt.maxViolations);

        expect(
            offending.length,
            `DS-RATCHET-01: ihlal içeren dosya sayısı ${debt.maxFiles} taban çizgisini aştı.`,
        ).toBeLessThanOrEqual(debt.maxFiles);
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

        // Sistem renkleri (CanvasText vb.) tema değiştirmez; onları hariç tut.
        const needsDark = aliases.filter((raw) => {
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
