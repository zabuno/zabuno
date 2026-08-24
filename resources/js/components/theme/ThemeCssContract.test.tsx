import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

// Read the authored CSS source directly off disk (not through a Vite/Tailwind
// transform pipeline) so the contract asserts on the actual source rules.
const appCssPath = resolve(process.cwd(), 'resources/css/app.css');
const appCss = readFileSync(appCssPath, 'utf8');

/**
 * ZABUNO_THEME_TOKEN_PARITY_RED_PROVEN
 *
 * Regression contract: semantic theme tokens must follow the user's
 * *explicit* application theme (light vs `.dark` on <html>, set by
 * ThemeRoot.tsx), never the OS `prefers-color-scheme` media query on its
 * own. A bare `@media (prefers-color-scheme: dark) { :root { ... } }` rule
 * overrides a token regardless of whether the user picked Light or Dark
 * explicitly, producing split-brain theme state (e.g. OS in dark mode +
 * user picks "Light" -> border token still resolves dark).
 */
describe('theme CSS token contract — explicit theme beats OS preference', () => {
    // A naive /\*...\*\// strip is fooled by `@source '...*.blade.php'`
    // glob strings, whose embedded "/*" isn't a real comment start — so we
    // walk the source respecting quoted strings before stripping comments.
    function stripComments(css: string): string {
        let out = '';
        let quote: string | null = null;
        for (let i = 0; i < css.length; i++) {
            const ch = css[i];
            if (quote) {
                out += ch;
                if (ch === quote) quote = null;
                continue;
            }
            if (ch === "'" || ch === '"') {
                quote = ch;
                out += ch;
                continue;
            }
            if (ch === '/' && css[i + 1] === '*') {
                const end = css.indexOf('*/', i + 2);
                i = end === -1 ? css.length : end + 1;
                continue;
            }
            out += ch;
        }
        return out;
    }

    // Walks the whole stylesheet tracking brace nesting depth so we can
    // tell a top-level `:root { ... }` (depth 0 when opened) apart from one
    // nested inside @media/@supports (depth > 0 when opened).
    function topLevelRootBlocks(css: string): string[] {
        const blocks: string[] = [];
        let depth = 0;
        for (let i = 0; i < css.length; i++) {
            const ch = css[i];
            if (ch === '{') {
                if (depth === 0) {
                    const precedingSelector = css.slice(0, i).match(/([^{}]*)$/)?.[1] ?? '';
                    const trimmedSelector = precedingSelector.trim();
                    if (trimmedSelector === ':root') {
                        let innerDepth = 1;
                        let j = i + 1;
                        while (j < css.length && innerDepth > 0) {
                            if (css[j] === '{') innerDepth++;
                            if (css[j] === '}') innerDepth--;
                            j++;
                        }
                        blocks.push(css.slice(i + 1, j - 1));
                    }
                }
                depth++;
            } else if (ch === '}') {
                depth--;
            }
        }
        return blocks;
    }

    // Finds selector blocks scoped by an explicit `.dark` class
    // (e.g. `.dark`, `:root.dark`, `.dark :root`) anywhere in the source.
    function darkClassScopedBlocks(css: string): string[] {
        const blocks: string[] = [];
        const re = /(^|\n)\s*([^{}\n]*\.dark[^{}\n]*)\{/g;
        let match: RegExpExecArray | null;
        while ((match = re.exec(css))) {
            const openIndex = css.indexOf('{', match.index);
            let depth = 1;
            let i = openIndex + 1;
            while (i < css.length && depth > 0) {
                if (css[i] === '{') depth++;
                if (css[i] === '}') depth--;
                i++;
            }
            blocks.push(css.slice(openIndex + 1, i - 1));
        }
        return blocks;
    }

    // Finds bodies of any `@media (prefers-color-scheme: dark) { ... }`
    // block, returning the raw (still-nested) inner source.
    function prefersColorSchemeDarkBlocks(css: string): string[] {
        const blocks: string[] = [];
        const re = /@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)\s*\{/g;
        let match: RegExpExecArray | null;
        while ((match = re.exec(css))) {
            const openIndex = css.indexOf('{', match.index);
            let depth = 1;
            let i = openIndex + 1;
            while (i < css.length && depth > 0) {
                if (css[i] === '{') depth++;
                if (css[i] === '}') depth--;
                i++;
            }
            blocks.push(css.slice(openIndex + 1, i - 1));
        }
        return blocks;
    }

    const css = stripComments(appCss);

    it('defines the light --border token in a base :root rule outside any media query', () => {
        const roots = topLevelRootBlocks(css);
        const hasLightBorder = roots.some((block) =>
            /--border\s*:\s*oklch\(0\.9\s+0\s+0\)/.test(block),
        );
        expect(hasLightBorder).toBe(true);
    });

    it('defines the dark --border token under an explicit .dark class selector', () => {
        const darkScoped = darkClassScopedBlocks(css);
        const hasDarkBorderUnderDarkClass = darkScoped.some((block) =>
            /--border\s*:\s*oklch\(0\.32\s+0\s+0\)/.test(block),
        );
        expect(hasDarkBorderUnderDarkClass).toBe(true);
    });

    it('never lets a bare prefers-color-scheme media query override --border independently of .dark', () => {
        const mediaBlocks = prefersColorSchemeDarkBlocks(css);
        for (const block of mediaBlocks) {
            // Any selector inside the media block that sets --border must
            // itself be scoped by `.dark` — otherwise OS preference alone
            // can override a token the user explicitly set via ThemeRoot.
            const selectorBodies = block.match(/[^{}]+\{[^{}]*--border[^{}]*\}/g) || [];
            for (const selectorBody of selectorBodies) {
                const selector = selectorBody.split('{')[0];
                expect(selector.includes('.dark')).toBe(true);
            }
        }
    });
});
