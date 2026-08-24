import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { cleanup, render } from '@testing-library/react';
import { createPortal } from 'react-dom';
import type { Decorator } from '@storybook/react-vite';
import { withTheme } from './decorators';

type DecoratorContext = Parameters<Decorator>[1];

function themeContext(theme: string): DecoratorContext {
    return { globals: { theme } } as unknown as DecoratorContext;
}

/**
 * ZABUNO_STORYBOOK_HIGH_CONTRAST_PARITY_RED_PROVEN
 *
 * Freezes the contract that Storybook's "high-contrast" theme global must
 * apply BOTH `dark` and `high-contrast` classes to document.documentElement
 * (not just `data-theme="high-contrast"`), because Tailwind's `dark:`
 * variant only activates on the `.dark` class (see app.css's
 * `@custom-variant dark (&:where(.dark, .dark *))`) while native form
 * controls/scrollbars key off `colorScheme`. Without the `dark` class,
 * Tailwind dark-mode utility styles and native control chrome split-brain
 * against each other under high-contrast. The decorator's own wrapper
 * <div> must expose the same two classes for non-portalled descendants,
 * and app.css must carry an explicit `.high-contrast` token override that
 * uses the `CanvasText` system color for `--border` plus a
 * `:focus-visible` outline using `Highlight`/`CanvasText`, without
 * disabling `forced-colors` (i.e. no `forced-color-adjust: none` escape
 * hatch smuggled in to fake the contrast).
 */
describe('withTheme — high-contrast parity with Tailwind dark + native controls (S1-WP01A)', () => {
    let preExistingMarker: HTMLElement;

    beforeEach(() => {
        preExistingMarker = document.createElement('meta');
        preExistingMarker.setAttribute('data-pre-existing', 'true');
        document.head.appendChild(preExistingMarker);
    });

    afterEach(() => {
        cleanup();
        preExistingMarker.remove();
    });

    function PortalProbe() {
        return createPortal(<div data-testid="portal-child">portalled dialog</div>, document.body);
    }

    function Story() {
        return (
            <>
                <div data-testid="in-tree-child">in-tree content</div>
                <PortalProbe />
            </>
        );
    }

    it('sets both dark and high-contrast classes, data-theme=high-contrast and colorScheme=dark on documentElement', () => {
        render(<>{withTheme(Story, themeContext('high-contrast'))}</>);

        const root = document.documentElement;
        expect(root.classList.contains('dark')).toBe(true);
        expect(root.classList.contains('high-contrast')).toBe(true);
        expect(root.getAttribute('data-theme')).toBe('high-contrast');
        expect(root.style.colorScheme).toBe('dark');
    });

    it('lets a portal rendered outside the decorator wrapper inherit both root classes (not just a wrapper class)', () => {
        const { container } = render(<>{withTheme(Story, themeContext('high-contrast'))}</>);

        const wrapper = container.querySelector('[data-theme]');
        expect(wrapper).not.toBeNull();

        const portalChild = document.body.querySelector('[data-testid="portal-child"]');
        expect(portalChild).not.toBeNull();
        expect(wrapper?.contains(portalChild)).toBe(false);

        const root = document.documentElement;
        expect(root.classList.contains('dark')).toBe(true);
        expect(root.classList.contains('high-contrast')).toBe(true);
    });

    it('exposes dark and high-contrast classes on the decorator wrapper itself for non-portalled descendants', () => {
        const { container } = render(<>{withTheme(Story, themeContext('high-contrast'))}</>);

        const wrapper = container.querySelector('[data-theme="high-contrast"]');
        expect(wrapper).not.toBeNull();
        expect(wrapper?.classList.contains('dark')).toBe(true);
        expect(wrapper?.classList.contains('high-contrast')).toBe(true);
    });

    it('removes both dark and high-contrast classes and resets data-theme/colorScheme to light when switching away', () => {
        const { rerender } = render(<>{withTheme(Story, themeContext('high-contrast'))}</>);
        expect(document.documentElement.classList.contains('high-contrast')).toBe(true);

        rerender(<>{withTheme(Story, themeContext('light'))}</>);

        const root = document.documentElement;
        expect(root.classList.contains('dark')).toBe(false);
        expect(root.classList.contains('high-contrast')).toBe(false);
        expect(root.getAttribute('data-theme')).toBe('light');
        expect(root.style.colorScheme).toBe('light');
    });

    it('restores pre-existing document root state (including absence of high-contrast) on unmount', () => {
        const hadDarkBefore = document.documentElement.classList.contains('dark');
        const hadHighContrastBefore = document.documentElement.classList.contains('high-contrast');
        const priorDataTheme = document.documentElement.getAttribute('data-theme');
        const priorColorScheme = document.documentElement.style.colorScheme;

        const { unmount } = render(<>{withTheme(Story, themeContext('high-contrast'))}</>);
        expect(document.documentElement.classList.contains('high-contrast')).toBe(true);

        unmount();

        expect(document.documentElement.classList.contains('dark')).toBe(hadDarkBefore);
        expect(document.documentElement.classList.contains('high-contrast')).toBe(
            hadHighContrastBefore,
        );
        expect(document.documentElement.getAttribute('data-theme')).toBe(priorDataTheme);
        expect(document.documentElement.style.colorScheme).toBe(priorColorScheme);
    });
});

/**
 * CSS-source contract half of the same gap: app.css must carry an explicit
 * `.high-contrast` root-token override (CanvasText-backed --border) and a
 * high-contrast-aware `:focus-visible` outline, without disabling
 * `forced-colors` support.
 */
describe('app.css — .high-contrast token override contract (S1-WP01A)', () => {
    const appCssPath = resolve(process.cwd(), 'resources/css/app.css');
    const appCss = readFileSync(appCssPath, 'utf8');

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

    function blocksForSelectorContaining(css: string, needle: string): string[] {
        const blocks: string[] = [];
        let depth = 0;
        for (let i = 0; i < css.length; i++) {
            const ch = css[i];
            if (ch === '{') {
                if (depth === 0) {
                    const precedingSelector = css.slice(0, i).match(/([^{}]*)$/)?.[1] ?? '';
                    if (precedingSelector.includes(needle)) {
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

    const css = stripComments(appCss);

    it('defines a .high-contrast rule overriding --border with the CanvasText system color', () => {
        const highContrastBlocks = blocksForSelectorContaining(css, '.high-contrast');
        const hasCanvasTextBorder = highContrastBlocks.some((block) =>
            /--border\s*:\s*CanvasText/i.test(block),
        );
        expect(hasCanvasTextBorder).toBe(true);
    });

    it('defines a high-contrast-aware :focus-visible outline using Highlight or CanvasText', () => {
        const highContrastFocusBlocks = [
            ...blocksForSelectorContaining(css, '.high-contrast').filter((block) =>
                /:focus-visible/.test(block),
            ),
            ...blocksForSelectorContaining(css, ':focus-visible'),
        ];

        const hasSystemColorOutline = highContrastFocusBlocks.some((block) =>
            /outline\s*:[^;]*\b(Highlight|CanvasText)\b/i.test(block),
        );
        expect(hasSystemColorOutline).toBe(true);
    });

    it('never disables forced-colors support (no forced-color-adjust: none escape hatch)', () => {
        expect(/forced-color-adjust\s*:\s*none/i.test(css)).toBe(false);
    });
});
