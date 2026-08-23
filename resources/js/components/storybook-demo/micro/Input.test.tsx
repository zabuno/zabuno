import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Input } from './Input';

describe('Input', () => {
    it('renders as a plain text input', () => {
        render(<Input placeholder="name" />);
        expect(screen.getByPlaceholderText('name')).toBeInTheDocument();
    });

    it('marks itself invalid via aria-invalid when invalid', () => {
        render(<Input invalid placeholder="name" />);
        expect(screen.getByPlaceholderText('name')).toHaveAttribute('aria-invalid', 'true');
    });

    it('marks itself disabled via aria-disabled and the native attribute', () => {
        render(<Input disabled placeholder="name" />);
        const input = screen.getByPlaceholderText('name');
        expect(input).toBeDisabled();
        expect(input).toHaveAttribute('aria-disabled', 'true');
    });
});
