import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Checkbox } from './Checkbox';

describe('Checkbox', () => {
    it('renders as a checkbox input', () => {
        render(<Checkbox aria-label="Accept terms" />);
        expect(screen.getByRole('checkbox', { name: 'Accept terms' })).toBeInTheDocument();
    });

    it('marks itself invalid via aria-invalid when invalid', () => {
        render(<Checkbox aria-label="Accept terms" invalid />);
        expect(screen.getByRole('checkbox', { name: 'Accept terms' })).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });

    it('marks itself disabled via aria-disabled and the native attribute', () => {
        render(<Checkbox aria-label="Accept terms" disabled />);
        const checkbox = screen.getByRole('checkbox', { name: 'Accept terms' });
        expect(checkbox).toBeDisabled();
        expect(checkbox).toHaveAttribute('aria-disabled', 'true');
    });
});
