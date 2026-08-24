import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { VisuallyHidden } from './VisuallyHidden';

describe('VisuallyHidden', () => {
    it('renders its children in the accessibility tree', () => {
        render(<VisuallyHidden>Loading results</VisuallyHidden>);
        expect(screen.getByText('Loading results')).toBeInTheDocument();
    });

    it('applies the sr-only class so content is visually hidden', () => {
        render(<VisuallyHidden>Hidden label</VisuallyHidden>);
        expect(screen.getByText('Hidden label')).toHaveClass('sr-only');
    });

    it('renders as the requested element type', () => {
        render(<VisuallyHidden as="h2">Section heading</VisuallyHidden>);
        expect(screen.getByText('Section heading').tagName).toBe('H2');
    });
});
