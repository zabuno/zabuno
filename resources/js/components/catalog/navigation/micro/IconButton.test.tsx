import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { IconButton } from './IconButton';

const icon = <svg data-testid="icon" />;

describe('IconButton', () => {
    it('exposes the label as the accessible name', () => {
        render(<IconButton icon={icon} label="Open navigation menu" />);
        expect(screen.getByRole('button', { name: 'Open navigation menu' })).toBeInTheDocument();
    });

    it('hides the icon from assistive tech', () => {
        render(<IconButton icon={icon} label="Open navigation menu" />);
        expect(screen.getByTestId('icon').parentElement).toHaveAttribute('aria-hidden', 'true');
    });

    it('calls onClick when activated', async () => {
        const onClick = vi.fn();
        render(<IconButton icon={icon} label="Open navigation menu" onClick={onClick} />);
        await userEvent.click(screen.getByRole('button', { name: 'Open navigation menu' }));
        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('is disabled when disabled is set', () => {
        render(<IconButton icon={icon} label="Open navigation menu" disabled />);
        expect(screen.getByRole('button', { name: 'Open navigation menu' })).toBeDisabled();
    });
});
