import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { readFileSync } from 'node:fs';
import { CloseButton } from './CloseButton';
import {
    WCAG_AA_LARGE_TEXT,
    contrastRatio,
    readCustomProperties,
    resolveColor,
} from '../../../../design-system/contrast';

/**
 * Kontrast artık sabit bir Tailwind hex'ine değil, bileşenin GERÇEKTEN
 * okuduğu token'a karşı ölçülür. Eski hâli `text-gray-500` sınıfının
 * varlığını arıyordu; o sınıf tasarım sisteminden kaçan bir ham renkti ve
 * test onu koruyordu. Ölçülmesi gereken şey sınıfın adı değil, kullanıcının
 * gördüğü kontrasttır (WCAG 2.2 §1.4.11, metin dışı bileşen ≥ 3:1).
 */
const CSS = readFileSync('resources/css/app.css', 'utf8');
const LIGHT = readCustomProperties(CSS, ':root');
const DARK = { ...LIGHT, ...readCustomProperties(CSS, '.dark') };

function tokenContrast(scope: Record<string, string>, fg: string, bg: string): number {
    const foreground = resolveColor(scope[fg], scope);
    const background = resolveColor(scope[bg], scope);

    if (foreground === null || background === null) {
        throw new Error(`Token çözülemedi: ${fg} / ${bg}`);
    }

    return contrastRatio(foreground, background);
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

    it('renders the resting icon at a WCAG 2.2 AA non-text contrast of at least 3:1 in both themes (1.4.11)', () => {
        render(<CloseButton onClick={() => {}} />);
        const button = screen.getByRole('button', { name: 'Close' });

        // Bileşen ham renk taşımaz; token okur.
        expect(button.className).toContain('text-fg-muted');
        expect(button.className).not.toMatch(/text-gray-\d/);

        expect(tokenContrast(LIGHT, '--fg-muted', '--surface')).toBeGreaterThanOrEqual(
            WCAG_AA_LARGE_TEXT,
        );
        expect(tokenContrast(DARK, '--fg-muted', '--surface')).toBeGreaterThanOrEqual(
            WCAG_AA_LARGE_TEXT,
        );
    });

    it('is not disabled by default', () => {
        render(<CloseButton onClick={() => {}} />);
        expect(screen.getByRole('button', { name: 'Close' })).not.toBeDisabled();
    });

    it('forwards native disabled semantics when disabled is true', () => {
        render(<CloseButton onClick={() => {}} disabled />);
        expect(screen.getByRole('button', { name: 'Close' })).toBeDisabled();
    });

    it('suppresses activation when disabled', async () => {
        const user = userEvent.setup();
        const onClick = vi.fn();
        render(<CloseButton onClick={onClick} disabled />);

        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClick).not.toHaveBeenCalled();
    });

    it('carries no dark-mode override because the token already answers for both themes', () => {
        render(<CloseButton onClick={() => {}} />);
        const button = screen.getByRole('button', { name: 'Close' });

        // `dark:` varyantı, aynı kararın iki yerde yazılması demektir ve
        // biri güncellenip diğeri unutulduğunda karanlık tema sessizce
        // bozulur. Token kökü zaten temaya göre çözülür.
        expect(button.className).not.toMatch(/dark:text-/);
    });
});
