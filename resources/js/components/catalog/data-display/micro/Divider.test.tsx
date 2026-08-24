import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';
import { Divider } from './Divider';

describe('Divider', () => {
    it('renders a separator role by default', () => {
        const { getByRole } = render(<Divider />);
        expect(getByRole('separator')).toBeInTheDocument();
    });

    it('forwards the vertical orientation', () => {
        const { getByRole } = render(<Divider orientation="vertical" />);
        expect(getByRole('separator')).toHaveAttribute('aria-orientation', 'vertical');
    });
});
