import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MenuItem } from './MenuItem';

describe('MenuItem', () => {
    it('renders with role menuitem', () => {
        render(<MenuItem onSelect={() => {}}>Rename</MenuItem>);
        expect(screen.getByRole('menuitem', { name: 'Rename' })).toBeInTheDocument();
    });

    it('calls onSelect when activated', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        render(<MenuItem onSelect={onSelect}>Rename</MenuItem>);

        await user.click(screen.getByRole('menuitem', { name: 'Rename' }));

        expect(onSelect).toHaveBeenCalledTimes(1);
    });

    it('does not call onSelect when disabled', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        render(
            <MenuItem onSelect={onSelect} disabled>
                Rename
            </MenuItem>,
        );

        await user.click(screen.getByRole('menuitem', { name: 'Rename' }));

        expect(onSelect).not.toHaveBeenCalled();
    });

    it('is exposed as disabled to assistive technology', () => {
        render(
            <MenuItem onSelect={() => {}} disabled>
                Rename
            </MenuItem>,
        );
        expect(screen.getByRole('menuitem', { name: 'Rename' })).toBeDisabled();
    });
});
