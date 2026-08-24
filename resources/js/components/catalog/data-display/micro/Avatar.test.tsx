import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Avatar } from './Avatar';

describe('Avatar', () => {
    it('renders an accessible image with the given name as alt text', () => {
        render(<Avatar name="Ada Lovelace" src="https://placehold.co/64x64" />);
        expect(screen.getByRole('img', { name: 'Ada Lovelace' })).toBeInTheDocument();
    });

    it('falls back to initials from a multi-word name when no image is given', () => {
        render(<Avatar name="Ada Lovelace" />);
        expect(screen.getByText('AL')).toBeInTheDocument();
    });

    it('falls back to the first two letters of a single-word name', () => {
        render(<Avatar name="Cher" />);
        expect(screen.getByText('CH')).toBeInTheDocument();
    });
});
