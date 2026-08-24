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
 * ZABUNO_STORYBOOK_THEME_PORTAL_RED
 *
 * Freezes the contract that the Storybook toolbar "theme" global must
 * propagate to document.documentElement (dark class, data-theme,
 * style.colorScheme), not just to a wrapper <div> inside the decorator
 * tree. Portalled content (e.g. Flowbite dialogs rendered via
 * createPortal into document.body) renders outside that wrapper, so a
 * wrapper-only `.dark` class cannot theme it — only the document root
 * can. Switching themes across re-renders must update the root, and
 * unmount must restore whatever the root looked like before the story
 * decorator ran, so state does not leak between stories.
 */
describe('withTheme — document root propagation for portalled content (S1-WP01A)', () => {
    let preExistingMarker: HTMLElement;

    beforeEach(() => {
        // Simulate whatever pre-existing root state Storybook's own chrome
        // may have set before a story's decorator runs.
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

    it('sets dark class, data-theme=dark and colorScheme=dark on documentElement when theme global is dark', () => {
        render(<>{withTheme(Story, themeContext('dark'))}</>);

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('removes dark and resets data-theme/colorScheme on documentElement when switching back to light', () => {
        const { rerender } = render(<>{withTheme(Story, themeContext('dark'))}</>);
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        rerender(<>{withTheme(Story, themeContext('light'))}</>);

        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.getAttribute('data-theme')).toBe('light');
        expect(document.documentElement.style.colorScheme).toBe('light');
    });

    it('restores pre-existing document root state on unmount instead of leaking dark between stories', () => {
        const hadDarkBefore = document.documentElement.classList.contains('dark');
        const priorDataTheme = document.documentElement.getAttribute('data-theme');
        const priorColorScheme = document.documentElement.style.colorScheme;

        const { unmount } = render(<>{withTheme(Story, themeContext('dark'))}</>);
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        unmount();

        expect(document.documentElement.classList.contains('dark')).toBe(hadDarkBefore);
        expect(document.documentElement.getAttribute('data-theme')).toBe(priorDataTheme);
        expect(document.documentElement.style.colorScheme).toBe(priorColorScheme);
    });

    it('lets a portal rendered outside the decorator wrapper inherit the dark root (not just a wrapper .dark class)', () => {
        const { container } = render(<>{withTheme(Story, themeContext('dark'))}</>);

        // The in-tree child sits under the decorator's own wrapper element.
        const wrapper = container.querySelector('[data-theme]');
        expect(wrapper).not.toBeNull();

        // The portal child is NOT a descendant of that wrapper — it was
        // appended directly to document.body — so only a document-root-level
        // dark signal (not a wrapper-only class) can theme it.
        const portalChild = document.body.querySelector('[data-testid="portal-child"]');
        expect(portalChild).not.toBeNull();
        expect(wrapper?.contains(portalChild)).toBe(false);

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });
});
