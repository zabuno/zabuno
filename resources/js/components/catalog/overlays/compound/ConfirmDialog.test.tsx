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

    describe('when confirmLoading is true (busy lock)', () => {
        it('marks the confirm action as busy and disabled', () => {
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={() => {}}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            const confirmButton = screen.getByRole('button', { name: 'Confirm' });
            expect(confirmButton).toHaveAttribute('aria-busy', 'true');
            expect(confirmButton).toBeDisabled();
        });

        it('presents the corner close control as natively disabled and not focusable/actionable', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            const closeButton = screen.getByRole('button', { name: 'Close' });
            expect(closeButton).toBeDisabled();

            closeButton.focus();
            expect(closeButton).not.toHaveFocus();

            await user.click(closeButton);
            expect(onClose).not.toHaveBeenCalled();
        });

        it('presents the footer Cancel control as natively disabled and not focusable/actionable', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            const cancelButton = screen.getByRole('button', { name: 'Cancel' });
            expect(cancelButton).toBeDisabled();

            cancelButton.focus();
            expect(cancelButton).not.toHaveFocus();

            await user.click(cancelButton);
            expect(onClose).not.toHaveBeenCalled();
        });

        it('leaves the corner Close and footer Cancel controls enabled when not busy', () => {
            render(
                <ConfirmDialog
                    open
                    onClose={() => {}}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            expect(screen.getByRole('button', { name: 'Close' })).not.toBeDisabled();
            expect(screen.getByRole('button', { name: 'Cancel' })).not.toBeDisabled();
        });

        it('does not call onClose or hide the dialog when Cancel is activated', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            await user.click(screen.getByRole('button', { name: 'Cancel' }));

            expect(onClose).not.toHaveBeenCalled();
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('does not call onClose or hide the dialog when the corner close control is activated', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            await user.click(screen.getByRole('button', { name: 'Close' }));

            expect(onClose).not.toHaveBeenCalled();
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('does not call onClose or hide the dialog on Escape', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            await user.keyboard('{Escape}');

            await new Promise((resolve) => setTimeout(resolve, 0));
            expect(onClose).not.toHaveBeenCalled();
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });

        it('does not call onClose or hide the dialog on backdrop dismissal', async () => {
            const user = userEvent.setup();
            const onClose = vi.fn();
            render(
                <ConfirmDialog
                    open
                    confirmLoading
                    onClose={onClose}
                    onConfirm={() => {}}
                    title="Publish changes?"
                />,
            );

            await user.click(screen.getByTestId('modal-overlay'));

            expect(onClose).not.toHaveBeenCalled();
            expect(screen.getByRole('dialog')).toBeInTheDocument();
        });
    });
});
