import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { Skeleton } from './Skeleton';

describe('Skeleton', () => {
    it('is hidden from assistive tech', () => {
        const { container } = render(<Skeleton />);
        expect(container.firstChild).toHaveAttribute('aria-hidden', 'true');
    });

    it('applies the requested width and height', () => {
        const { container } = render(<Skeleton width="48px" height="48px" />);
        expect(container.firstChild).toHaveStyle({ width: '48px', height: '48px' });
    });

    it.each([['text'], ['circle'], ['rect']] as const)(
        'renders without throwing for the %s shape',
        (shape) => {
            const { container } = render(<Skeleton shape={shape} />);
            expect(container.firstChild).toBeInTheDocument();
        },
    );
});
