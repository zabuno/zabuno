import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Spinner } from './Spinner';

describe('Spinner', () => {
    it('exposes a status role for assistive tech', () => {
        render(<Spinner />);
        expect(screen.getByRole('status')).toBeInTheDocument();
    });

    it('announces the default label via a visually-hidden text node', () => {
        render(<Spinner />);
        expect(screen.getByText('Loading…')).toBeInTheDocument();
    });

    it('announces a custom label when provided', () => {
        render(<Spinner label="Saving changes…" />);
        expect(screen.getByText('Saving changes…')).toBeInTheDocument();
    });
});
