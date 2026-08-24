import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SkipLink } from './SkipLink';

describe('SkipLink', () => {
    it('links to the given target id', () => {
        render(<SkipLink targetId="main-content" />);
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );
    });

    it('accepts custom link text', () => {
        render(<SkipLink targetId="main-content">İçeriğe geç</SkipLink>);
        expect(screen.getByRole('link', { name: 'İçeriğe geç' })).toBeInTheDocument();
    });

    it('is visually hidden by default via sr-only', () => {
        render(<SkipLink targetId="main-content" />);
        expect(screen.getByRole('link')).toHaveClass('sr-only');
    });
});
