import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ActionMenu } from './ActionMenu';

const items = [
    { key: 'edit', label: 'Edit', onSelect: vi.fn() },
    { key: 'delete', label: 'Delete', onSelect: vi.fn(), destructive: true },
];

describe('ActionMenu', () => {
    it('shows a trigger button with an accessible name and no items until opened', () => {
        render(<ActionMenu label="Row actions" items={items} />);
        expect(screen.getByRole('button', { name: 'Row actions' })).toBeInTheDocument();
        expect(screen.queryByRole('menuitem')).not.toBeInTheDocument();
    });

    it('reveals its menu items when the trigger is activated', async () => {
        const user = userEvent.setup();
        render(<ActionMenu label="Row actions" items={items} />);

        await user.click(screen.getByRole('button', { name: 'Row actions' }));

        expect(await screen.findByRole('menuitem', { name: 'Edit' })).toBeInTheDocument();
        expect(screen.getByRole('menuitem', { name: 'Delete' })).toBeInTheDocument();
    });

    it('calls the matching onSelect when an item is activated', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        render(
            <ActionMenu
                label="Row actions"
                items={[
                    { key: 'edit', label: 'Edit', onSelect },
                    { key: 'delete', label: 'Delete', onSelect: vi.fn(), destructive: true },
                ]}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Row actions' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Edit' }));

        expect(onSelect).toHaveBeenCalledTimes(1);
    });

    it('does not call onSelect for a disabled item', async () => {
        const user = userEvent.setup();
        const onSelect = vi.fn();
        render(
            <ActionMenu
                label="Row actions"
                items={[{ key: 'publish', label: 'Publish', onSelect, disabled: true }]}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Row actions' }));
        await user.click(await screen.findByRole('menuitem', { name: 'Publish' }));

        expect(onSelect).not.toHaveBeenCalled();
    });

    it('closes on Escape and returns focus to the trigger', async () => {
        const user = userEvent.setup();
        render(<ActionMenu label="Row actions" items={items} />);
        const trigger = screen.getByRole('button', { name: 'Row actions' });

        await user.click(trigger);
        expect(await screen.findByRole('menuitem', { name: 'Edit' })).toBeInTheDocument();

        await user.keyboard('{Escape}');

        await waitFor(() => expect(screen.queryByRole('menuitem')).not.toBeInTheDocument());
        expect(trigger).toHaveFocus();
    });
});
