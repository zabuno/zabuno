import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Button } from './Button';

describe('Button', () => {
    it('renders its children', () => {
        render(<Button>Save changes</Button>);
        expect(screen.getByRole('button', { name: 'Save changes' })).toBeInTheDocument();
    });

    it('is disabled and aria-busy while loading, showing loadingText instead of children', () => {
        render(
            <Button loading loadingText="Saving…">
                Save changes
            </Button>,
        );
        const button = screen.getByRole('button', { name: 'Saving…' });
        expect(button).toBeDisabled();
        expect(button).toHaveAttribute('aria-busy', 'true');
        expect(screen.queryByText('Save changes')).not.toBeInTheDocument();
    });

    it('can be disabled independently of loading', () => {
        render(<Button disabled>Save changes</Button>);
        expect(screen.getByRole('button', { name: 'Save changes' })).toBeDisabled();
    });
});
