import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { BrandMark } from './BrandMark';

describe('BrandMark', () => {
    it('renders the name as text', () => {
        render(<BrandMark name="Zabuno" />);
        expect(screen.getByText('Zabuno')).toBeInTheDocument();
    });

    it('renders as a link when href is given', () => {
        render(<BrandMark name="Zabuno" href="/dashboard" />);
        expect(screen.getByRole('link', { name: 'Zabuno' })).toHaveAttribute('href', '/dashboard');
    });

    it('renders as non-interactive text when href is omitted', () => {
        render(<BrandMark name="Zabuno" />);
        expect(screen.queryByRole('link')).not.toBeInTheDocument();
    });

    it('keeps the name accessible but visually hidden when hideName is set', () => {
        render(<BrandMark name="Zabuno" hideName />);
        expect(screen.getByText('Zabuno')).toHaveClass('sr-only');
    });
});
