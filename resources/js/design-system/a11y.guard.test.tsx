import { describe, expect, it } from 'vitest';
import { cleanup, render } from '@testing-library/react';
import { createElement } from 'react';
import axe from 'axe-core';

/**
 * Erişilebilirlik kapısı — Dalga 1 (`docs/37` §5).
 *
 * Külliyat axe'i her katmanın "bitti" tanımının parçası sayar (X2 ekseni) ve
 * `docs/06` WCAG 2.2 AA'yı şart koşar. Bu depoda `@storybook/addon-a11y`
 * kuruluydu — yani ihlaller Storybook'ta *görünüyordu* — fakat CI'da tek bir
 * ölçüm yoktu. Görülebilen ama ölçülmeyen bir kural, kural değildir.
 *
 * Bu kapı her katmanlı bileşenin her story'sini gerçek bağlamıyla render eder
 * ve axe çalıştırır. Ölçüm sıfır ihlalde açıldı; cırcır yok, çünkü borç yok.
 *
 * Kapsam sınırı: jsdom düzen hesaplamadığı için `color-contrast` burada
 * kapalıdır. O eksen ayrıca ve token seviyesinde ölçülür —
 * `design-system.guard.test.ts` içindeki `DS-CONTRAST-AA-01`.
 *
 * Requirement ID'leri: DS-A11Y-AXE-01, DS-A11Y-RENDERABLE-02.
 */

const storyModules = import.meta.glob(
    '../components/catalog/**/{micro,compound,macro}/*.stories.tsx',
);

type StoryMeta = {
    component?: unknown;
    args?: Record<string, unknown>;
    decorators?: unknown[];
};

type Story = { args?: Record<string, unknown>; render?: (args: unknown) => unknown };

type Finding = { story: string; rule: string; help: string };

type ScanResult = { stories: number; findings: Finding[]; unrenderable: string[] };

async function scanEveryStory(): Promise<ScanResult> {
    const findings: Finding[] = [];
    const unrenderable: string[] = [];
    let stories = 0;

    for (const path of Object.keys(storyModules)) {
        const mod = (await storyModules[path]()) as Record<string, unknown>;
        const meta = mod.default as StoryMeta;
        const file = path.split('/').pop() ?? path;

        if (typeof meta?.component !== 'function') {
            unrenderable.push(`${file}: meta.component yok`);
            continue;
        }

        for (const name of Object.keys(mod).filter((key) => key !== 'default')) {
            const story = mod[name] as Story;
            const label = `${file}#${name}`;
            stories++;

            try {
                const args = { ...(meta.args ?? {}), ...(story?.args ?? {}) };

                // CSF: story kendi `render`'ını verebilir; vermezse component args ile render edilir.
                let element = (
                    typeof story?.render === 'function'
                        ? story.render(args)
                        : createElement(meta.component as never, args as never)
                ) as never;

                // Decorator'lar story'nin GERÇEK bağlamını kurar (örn. bir
                // menuitem'ı menü kabına sarmak). Uygulanmazsa tarama, üründe
                // hiç var olmayan bir DOM'u ölçer ve yanlış alarm üretir.
                for (const decorate of [...(meta.decorators ?? [])].reverse()) {
                    const current = element;
                    element = (decorate as (s: () => unknown) => never)(() => current);
                }

                const { container } = render(element);
                const results = await axe.run(container, {
                    rules: { 'color-contrast': { enabled: false } },
                });

                for (const violation of results.violations) {
                    findings.push({
                        story: label,
                        rule: violation.id,
                        help: `${violation.help} (${violation.nodes.length} düğüm)`,
                    });
                }

                cleanup();
            } catch (error) {
                unrenderable.push(`${label}: ${(error as Error).message.slice(0, 80)}`);
            }
        }
    }

    return { stories, findings, unrenderable };
}

describe('erişilebilirlik kapısı', () => {
    it("her katmanlı bileşenin her story'si axe ihlali üretmez", async () => {
        const { stories, findings } = await scanEveryStory();

        // Tarama gerçekten koştu mu? Boş bir glob sessizce "hepsi geçti" derdi.
        expect(
            stories,
            'DS-A11Y-AXE-01: hiç story taranmadı — glob bozulmuş olabilir.',
        ).toBeGreaterThan(0);

        expect(
            findings.map((f) => `${f.story} :: ${f.rule} :: ${f.help}`),
            'DS-A11Y-AXE-01: erişilebilirlik ihlali. Erişilebilirlik, öncelik ' +
                'sırasında estetiğin çok üstündedir (docs/37 §1); bu liste boş kalmalıdır:',
        ).toEqual([]);
    }, 60_000);

    it('her story render edilebilir', async () => {
        const { unrenderable } = await scanEveryStory();

        expect(
            unrenderable,
            'DS-A11Y-RENDERABLE-02: render edilemeyen story, taranamayan story ' +
                'demektir — kapı sessizce kör kalır:',
        ).toEqual([]);
    }, 60_000);
});
