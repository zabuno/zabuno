import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CloseButton } from './CloseButton';

// Tailwind color hex values relevant to the resting light-mode icon color,
// used for a local WCAG 2.2 AA (non-text UI component, 1.4.11) contrast
// calculation against the button's white background — no snapshot or
// brittle source-string check.
const HEX = {
    white: '#ffffff',
    gray400: '#9ca3af',
    gray500: '#6b7280',
    // Flowbite ConfirmDialog dark surface (dark:bg-gray-700), the dark-mode
    // background the icon's resting currentColor is composited against.
    gray700: '#374151',
} as const;

function srgbToLinear(channel: number): number {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
}

function relativeLuminance(hex: string): number {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return 0.2126 * srgbToLinear(r) + 0.7152 * srgbToLinear(g) + 0.0722 * srgbToLinear(b);
}

function contrastRatio(hexA: string, hexB: string): number {
    const lumA = relativeLuminance(hexA) + 0.05;
    const lumB = relativeLuminance(hexB) + 0.05;
    return lumA > lumB ? lumA / lumB : lumB / lumA;
}

describe('CloseButton', () => {
    it('exposes a default accessible name of "Close"', () => {
        render(<CloseButton onClick={() => {}} />);
        expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument();
    });

    it('accepts a custom accessible name', () => {
        render(<CloseButton onClick={() => {}} label="Dismiss notification" />);
        expect(screen.getByRole('button', { name: 'Dismiss notification' })).toBeInTheDocument();
    });

    it('calls onClick when activated', async () => {
        const user = userEvent.setup();
        const onClick = vi.fn();
        render(<CloseButton onClick={onClick} />);

        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('hides its icon from the accessibility tree', () => {
        render(<CloseButton onClick={() => {}} />);
        const button = screen.getByRole('button', { name: 'Close' });
        expect(button.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    });

    it('renders the resting light-mode icon at a WCAG 2.2 AA non-text contrast of at least 3:1 against white (1.4.11)', () => {
        render(<CloseButton onClick={() => {}} />);
        const button = screen.getByRole('button', { name: 'Close' });

        // gray-400 is the current production color and fails the 3:1
        // non-text-contrast minimum; the minimal production fix is gray-500,
        // which passes.
        const classTokens = button.className.split(/\s+/);
        expect(classTokens).not.toContain('text-gray-400');
        expect(contrastRatio(HEX.gray400, HEX.white)).toBeLessThan(3);
        expect(contrastRatio(HEX.gray500, HEX.white)).toBeGreaterThanOrEqual(3);
        expect(classTokens).toContain('text-gray-500');
    });

    it('renders the resting dark-mode icon at a WCAG 2.2 AA non-text contrast of at least 3:1 against the Flowbite ConfirmDialog dark surface (1.4.11)', () => {
        render(<CloseButton onClick={() => {}} />);
        const button = screen.getByRole('button', { name: 'Close' });

        // dark:text-gray-500 is the current production color and fails the
        // 3:1 non-text-contrast minimum against dark:bg-gray-700; the
        // minimal production fix is dark:text-gray-400, which passes.
        expect(button.className).not.toContain('dark:text-gray-500');
        expect(contrastRatio(HEX.gray500, HEX.gray700)).toBeLessThan(3);
        expect(contrastRatio(HEX.gray400, HEX.gray700)).toBeGreaterThanOrEqual(3);
        expect(button.className).toContain('dark:text-gray-400');
    });
});
