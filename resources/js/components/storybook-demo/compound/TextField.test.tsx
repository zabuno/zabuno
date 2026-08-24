import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TextField } from './TextField';

describe('TextField', () => {
    it('associates the label with the input', () => {
        render(<TextField label="Restaurant name" />);
        expect(screen.getByLabelText('Restaurant name')).toBeInTheDocument();
    });

    it('links error text to the input via aria-describedby and marks it invalid', () => {
        render(<TextField label="Restaurant name" errorText="Required." />);
        const input = screen.getByLabelText('Restaurant name');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(screen.getByRole('alert')).toHaveTextContent('Required.');
        expect(input.getAttribute('aria-describedby')).toContain(screen.getByRole('alert').id);
    });

    it('links help text to the input via aria-describedby', () => {
        render(<TextField label="Restaurant name" helpText="Shown publicly." />);
        const input = screen.getByLabelText('Restaurant name');
        const help = screen.getByText('Shown publicly.');
        expect(input.getAttribute('aria-describedby')).toContain(help.id);
    });
});
