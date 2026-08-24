import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ThemeRoot } from './ThemeRoot';

/**
 * ZABUNO_THEME_KEYBOARD_FOCUS_RED_PROVEN
 *
 * Freezes the WCAG 2.4 roving-tabindex contract for the theme radiogroup:
 * arrow keys must move both the checked state AND DOM focus together, and
 * only the checked radio may sit in the tab order. ThemeRoot.tsx currently
 * calls choose() on ArrowRight/Left/Up/Down but never moves focus to the
 * newly-checked button, and has no Home/End handling at all — every
 * focus-observing assertion below is expected to fail RED against the
 * current implementation (sha256 d25d52e3ab1f5787d2ea17ed3de1b1321bfd81e057c0c2489afaec49ffc62fe2).
 */

function installMatchMediaMock(prefersDark: boolean) {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        configurable: true,
        value: vi.fn().mockImplementation((query: string) => ({
            matches: prefersDark,
            media: query,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: () => true,
        })),
    });
}

function resetDocumentThemeState() {
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    window.localStorage.clear();
}

describe('ThemeRoot — keyboard roving focus (ARIA radiogroup pattern)', () => {
    beforeEach(() => {
        resetDocumentThemeState();
    });

    afterEach(() => {
        resetDocumentThemeState();
        vi.restoreAllMocks();
    });

    it('moves focus to the next radio on ArrowRight, in sync with the checked state', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const light = screen.getByRole('radio', { name: 'Light' });

        system.focus();
        await user.keyboard('{ArrowRight}');

        expect(light).toHaveAttribute('aria-checked', 'true');
        expect(light).toHaveFocus();
    });

    it('moves focus to the next radio on ArrowDown, in sync with the checked state', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const light = screen.getByRole('radio', { name: 'Light' });

        system.focus();
        await user.keyboard('{ArrowDown}');

        expect(light).toHaveAttribute('aria-checked', 'true');
        expect(light).toHaveFocus();
    });

    it('wraps and moves focus to the previous radio on ArrowLeft', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const dark = screen.getByRole('radio', { name: 'Dark' });

        system.focus();
        await user.keyboard('{ArrowLeft}');

        expect(dark).toHaveAttribute('aria-checked', 'true');
        expect(dark).toHaveFocus();
    });

    it('wraps and moves focus to the previous radio on ArrowUp', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const dark = screen.getByRole('radio', { name: 'Dark' });

        system.focus();
        await user.keyboard('{ArrowUp}');

        expect(dark).toHaveAttribute('aria-checked', 'true');
        expect(dark).toHaveFocus();
    });

    it('selects and focuses the first radio on Home', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const dark = screen.getByRole('radio', { name: 'Dark' });

        dark.focus();
        await user.keyboard('{Home}');

        expect(system).toHaveAttribute('aria-checked', 'true');
        expect(system).toHaveFocus();
    });

    it('selects and focuses the last radio on End', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const dark = screen.getByRole('radio', { name: 'Dark' });

        system.focus();
        await user.keyboard('{End}');

        expect(dark).toHaveAttribute('aria-checked', 'true');
        expect(dark).toHaveFocus();
    });

    it('keeps only the checked radio in the tab order (roving tabindex)', async () => {
        installMatchMediaMock(false);
        const user = userEvent.setup();
        render(<ThemeRoot>{null}</ThemeRoot>);

        const system = screen.getByRole('radio', { name: 'System' });
        const light = screen.getByRole('radio', { name: 'Light' });
        const dark = screen.getByRole('radio', { name: 'Dark' });

        expect(system).toHaveAttribute('tabindex', '0');
        expect(light).toHaveAttribute('tabindex', '-1');
        expect(dark).toHaveAttribute('tabindex', '-1');

        system.focus();
        await user.keyboard('{ArrowRight}');

        expect(system).toHaveAttribute('tabindex', '-1');
        expect(light).toHaveAttribute('tabindex', '0');
        expect(dark).toHaveAttribute('tabindex', '-1');
    });
});
