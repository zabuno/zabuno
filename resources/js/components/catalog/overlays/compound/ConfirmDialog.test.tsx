import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ConfirmDialog } from './ConfirmDialog';

describe('ConfirmDialog', () => {
    it('renders nothing when closed', () => {
        render(
            <ConfirmDialog
                open={false}
                onClose={() => {}}
                onConfirm={() => {}}
                title="Publish changes?"
            />,
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('renders a labelled dialog with its title when open', () => {
        render(
            <ConfirmDialog open onClose={() => {}} onConfirm={() => {}} title="Publish changes?" />,
        );
        const dialog = screen.getByRole('dialog');
        expect(dialog).toHaveAccessibleName('Publish changes?');
    });

    it('calls onConfirm when the confirm action is activated', async () => {
        const user = userEvent.setup();
        const onConfirm = vi.fn();
        render(
            <ConfirmDialog
                open
                onClose={() => {}}
                onConfirm={onConfirm}
                title="Publish changes?"
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Confirm' }));

        expect(onConfirm).toHaveBeenCalledTimes(1);
    });

    it('calls onClose when the cancel action is activated', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <ConfirmDialog open onClose={onClose} onConfirm={() => {}} title="Publish changes?" />,
        );

        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose when the corner close control is activated', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <ConfirmDialog open onClose={onClose} onConfirm={() => {}} title="Publish changes?" />,
        );

        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose on Escape', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <ConfirmDialog open onClose={onClose} onConfirm={() => {}} title="Publish changes?" />,
        );

        await user.keyboard('{Escape}');

        await waitFor(() => expect(onClose).toHaveBeenCalled());
    });

    it('uses the default confirm/cancel labels', () => {
        render(
            <ConfirmDialog open onClose={() => {}} onConfirm={() => {}} title="Publish changes?" />,
        );
        expect(screen.getByRole('button', { name: 'Confirm' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
    });

    it('accepts custom confirm/cancel labels for destructive actions', () => {
        render(
            <ConfirmDialog
                open
                onClose={() => {}}
                onConfirm={() => {}}
                title="Delete this menu item?"
                destructive
                confirmLabel="Delete"
                cancelLabel="Keep it"
            />,
        );
        expect(screen.getByRole('button', { name: 'Delete' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Keep it' })).toBeInTheDocument();
    });

    it('returns focus to the previously focused element after closing', async () => {
        const user = userEvent.setup();

        function Harness() {
            const [open, setOpen] = useState(false);
            return (
                <>
                    <button type="button" onClick={() => setOpen(true)}>
                        Open
                    </button>
                    <ConfirmDialog
                        open={open}
                        onClose={() => setOpen(false)}
                        onConfirm={() => setOpen(false)}
                        title="Publish changes?"
                    />
                </>
            );
        }

        render(<Harness />);
        const trigger = screen.getByRole('button', { name: 'Open' });
        await user.click(trigger);
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        await waitFor(() => expect(trigger).toHaveFocus());
    });
});
