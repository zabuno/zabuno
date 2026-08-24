import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatValue } from './StatValue';

// Tailwind color hex values relevant to the resting light-mode "up" trend
// foreground, used for a local WCAG 2.2 AA contrast calculation (no snapshot
// or brittle source-string check).
const HEX = {
    white: '#ffffff',
    green600: '#16a34a',
    green700: '#15803d',
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

describe('StatValue', () => {
    it('renders the value', () => {
        render(<StatValue value="1,204" />);
        expect(screen.getByText('1,204')).toBeInTheDocument();
    });

    it('does not render a trend indicator when trend is omitted', () => {
        render(<StatValue value="1,204" />);
        expect(screen.queryByText('(trending up)')).not.toBeInTheDocument();
    });

    it.each([
        ['up', '(trending up)'],
        ['down', '(trending down)'],
        ['flat', '(flat)'],
    ] as const)('announces the %s trend to assistive tech', (trend, label) => {
        render(<StatValue value="1,204" trend={trend} />);
        expect(screen.getByText(label)).toBeInTheDocument();
    });

    it('renders the resting light-mode "up" trend foreground at a WCAG 2.2 AA contrast of at least 4.5:1 against white (1.4.3)', () => {
        render(<StatValue value="1,204" trend="up" />);
        const trendNode = screen.getByText('(trending up)').parentElement;
        expect(trendNode).not.toBeNull();

        // green-600 is the current production color and fails AA; the
        // minimal production fix is green-700, which passes.
        expect(trendNode?.className).not.toContain('text-green-600');
        expect(contrastRatio(HEX.green600, HEX.white)).toBeLessThan(4.5);
        expect(contrastRatio(HEX.green700, HEX.white)).toBeGreaterThanOrEqual(4.5);
        expect(trendNode?.className).toContain('text-green-700');
    });
});
