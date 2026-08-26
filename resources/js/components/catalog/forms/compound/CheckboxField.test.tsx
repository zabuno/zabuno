import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CheckboxField } from './CheckboxField';

describe('CheckboxField', () => {
    it('associates the label with the checkbox', () => {
        render(<CheckboxField label="Accept terms" />);
        expect(screen.getByLabelText('Accept terms')).toBeInTheDocument();
    });

    it('links error text to the checkbox via aria-describedby and marks it invalid', () => {
        render(<CheckboxField label="Accept terms" errorText="Required." />);
        const checkbox = screen.getByLabelText('Accept terms');
        expect(checkbox).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('alert')).toHaveTextContent('Required.');
        expect(checkbox.getAttribute('aria-describedby')).toContain(screen.getByRole('alert').id);
    });

    it('links help text to the checkbox via aria-describedby', () => {
        render(<CheckboxField label="Accept terms" helpText="Shown publicly." />);
        const checkbox = screen.getByLabelText('Accept terms');
        const help = screen.getByText('Shown publicly.');
        expect(checkbox.getAttribute('aria-describedby')).toContain(help.id);
    });
});
