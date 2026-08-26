import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Textarea } from './Textarea';

describe('Textarea', () => {
    it('renders as a plain textarea', () => {
        render(<Textarea placeholder="description" />);
        expect(screen.getByPlaceholderText('description')).toBeInTheDocument();
    });

    it('marks itself invalid via aria-invalid when invalid', () => {
        render(<Textarea invalid placeholder="description" />);
        expect(screen.getByPlaceholderText('description')).toHaveAttribute('aria-invalid', 'true');
    });

    it('marks itself disabled via aria-disabled and the native attribute', () => {
        render(<Textarea disabled placeholder="description" />);
        const textarea = screen.getByPlaceholderText('description');
        expect(textarea).toBeDisabled();
        expect(textarea).toHaveAttribute('aria-disabled', 'true');
    });
});
