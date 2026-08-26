import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SelectField } from './SelectField';

describe('SelectField', () => {
    it('associates the label with the select', () => {
        render(
            <SelectField label="Cuisine">
                <option value="turkish">Turkish</option>
            </SelectField>,
        );
        expect(screen.getByLabelText('Cuisine')).toBeInTheDocument();
    });

    it('marks the select as required via aria-required when required', () => {
        render(
            <SelectField label="Cuisine" required>
                <option value="turkish">Turkish</option>
            </SelectField>,
        );
        expect(screen.getByLabelText(/Cuisine/)).toHaveAttribute('aria-required', 'true');
    });

    it('links error text to the select via aria-describedby and marks it invalid', () => {
        render(
            <SelectField label="Cuisine" errorText="Required.">
                <option value="turkish">Turkish</option>
            </SelectField>,
        );
        const select = screen.getByLabelText('Cuisine');
        expect(select).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('alert')).toHaveTextContent('Required.');
        expect(select.getAttribute('aria-describedby')).toContain(screen.getByRole('alert').id);
    });

    it('links help text to the select via aria-describedby', () => {
        render(
            <SelectField label="Cuisine" helpText="Shown publicly.">
                <option value="turkish">Turkish</option>
            </SelectField>,
        );
        const select = screen.getByLabelText('Cuisine');
        const help = screen.getByText('Shown publicly.');
        expect(select.getAttribute('aria-describedby')).toContain(help.id);
    });
});
