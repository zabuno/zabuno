import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Select } from './Select';

describe('Select', () => {
    it('renders its options', () => {
        render(
            <Select aria-label="Cuisine">
                <option value="turkish">Turkish</option>
            </Select>,
        );
        expect(screen.getByRole('combobox', { name: 'Cuisine' })).toBeInTheDocument();
    });

    it('marks itself invalid via aria-invalid when invalid', () => {
        render(
            <Select aria-label="Cuisine" invalid>
                <option value="turkish">Turkish</option>
            </Select>,
        );
        expect(screen.getByRole('combobox', { name: 'Cuisine' })).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    });

    it('marks itself disabled via aria-disabled and the native attribute', () => {
        render(
            <Select aria-label="Cuisine" disabled>
                <option value="turkish">Turkish</option>
            </Select>,
        );
        const select = screen.getByRole('combobox', { name: 'Cuisine' });
        expect(select).toBeDisabled();
        expect(select).toHaveAttribute('aria-disabled', 'true');
    });
});
