import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { EmptyState } from './EmptyState';

describe('EmptyState', () => {
    it('renders the title and description', () => {
        render(<EmptyState title="No items" description="Add your first item." />);
        expect(screen.getByText('No items')).toBeInTheDocument();
        expect(screen.getByText('Add your first item.')).toBeInTheDocument();
    });

    it('renders the provided action', () => {
        render(<EmptyState title="No items" action={<button type="button">Add item</button>} />);
        expect(screen.getByRole('button', { name: 'Add item' })).toBeInTheDocument();
    });

    it('composes the Spinner micro when loading, instead of the empty glyph', () => {
        render(<EmptyState title="Checking" loading />);
        expect(screen.getByRole('status')).toBeInTheDocument();
    });

    it('does not render a status role when not loading', () => {
        render(<EmptyState title="No items" />);
        expect(screen.queryByRole('status')).not.toBeInTheDocument();
    });
});
