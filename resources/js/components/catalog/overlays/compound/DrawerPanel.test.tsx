import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DrawerPanel } from './DrawerPanel';

describe('DrawerPanel', () => {
    it('renders nothing when closed', () => {
        render(
            <DrawerPanel open={false} onClose={() => {}} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('renders a labelled dialog with its title when open', () => {
        render(
            <DrawerPanel open onClose={() => {}} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );
        expect(screen.getByRole('dialog')).toHaveAccessibleName('Filter menu items');
    });

    it('renders its children', () => {
        render(
            <DrawerPanel open onClose={() => {}} title="Filter menu items">
                <p>Filter controls</p>
            </DrawerPanel>,
        );
        expect(screen.getByText('Filter controls')).toBeInTheDocument();
    });

    it('calls onClose when the close control is activated', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <DrawerPanel open onClose={onClose} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );

        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('calls onClose on Escape', async () => {
        const user = userEvent.setup();
        const onClose = vi.fn();
        render(
            <DrawerPanel open onClose={onClose} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );

        await user.keyboard('{Escape}');

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('moves focus into the panel when opened', async () => {
        render(
            <DrawerPanel open onClose={() => {}} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );
        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());
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
                    <DrawerPanel
                        open={open}
                        onClose={() => setOpen(false)}
                        title="Filter menu items"
                    >
                        Filters
                    </DrawerPanel>
                </>
            );
        }

        render(<Harness />);
        const trigger = screen.getByRole('button', { name: 'Open' });
        await user.click(trigger);
        expect(screen.getByRole('dialog')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Close' }));

        await waitFor(() => expect(trigger).toHaveFocus());
    });

    it('traps Tab focus inside the panel, wrapping last to first', async () => {
        const user = userEvent.setup();

        render(
            <>
                <button type="button">Outside trigger</button>
                <DrawerPanel open onClose={() => {}} title="Filter menu items">
                    <button type="button">Apply filters</button>
                </DrawerPanel>
            </>,
        );

        const closeButton = screen.getByRole('button', { name: 'Close' });
        const applyButton = screen.getByRole('button', { name: 'Apply filters' });
        const outsideTrigger = screen.getByRole('button', { name: 'Outside trigger' });

        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());

        closeButton.focus();
        await user.tab();
        expect(applyButton).toHaveFocus();

        await user.tab();
        expect(closeButton).toHaveFocus();
        expect(outsideTrigger).not.toHaveFocus();
    });

    it('traps Shift+Tab focus inside the panel, wrapping first to last', async () => {
        const user = userEvent.setup();

        render(
            <>
                <button type="button">Outside trigger</button>
                <DrawerPanel open onClose={() => {}} title="Filter menu items">
                    <button type="button">Apply filters</button>
                </DrawerPanel>
            </>,
        );

        const closeButton = screen.getByRole('button', { name: 'Close' });
        const applyButton = screen.getByRole('button', { name: 'Apply filters' });
        const outsideTrigger = screen.getByRole('button', { name: 'Outside trigger' });

        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());

        closeButton.focus();
        await user.tab({ shift: true });
        expect(applyButton).toHaveFocus();
        expect(outsideTrigger).not.toHaveFocus();
    });

    it('excludes a CSS-hidden descendant from the Tab focus boundary', async () => {
        const user = userEvent.setup();

        render(
            <>
                <button type="button">Outside trigger</button>
                <DrawerPanel open onClose={() => {}} title="Filter menu items">
                    <button type="button">Apply filters</button>
                    <button type="button" style={{ display: 'none' }}>
                        Hidden filters
                    </button>
                </DrawerPanel>
            </>,
        );

        const closeButton = screen.getByRole('button', { name: 'Close' });
        const applyButton = screen.getByRole('button', { name: 'Apply filters' });
        const hiddenButton = screen.getByText('Hidden filters', { selector: 'button' });
        const outsideTrigger = screen.getByRole('button', { name: 'Outside trigger' });

        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());

        applyButton.focus();
        await user.tab();

        expect(closeButton).toHaveFocus();
        expect(hiddenButton).not.toHaveFocus();
        expect(outsideTrigger).not.toHaveFocus();
    });

    it('excludes a focusable descendant of a display:none ancestor from the Tab focus boundary', async () => {
        const user = userEvent.setup();

        render(
            <>
                <button type="button">Outside trigger</button>
                <DrawerPanel open onClose={() => {}} title="Filter menu items">
                    <button type="button">Apply filters</button>
                    <div style={{ display: 'none' }}>
                        <button type="button">Ancestor hidden filters</button>
                    </div>
                </DrawerPanel>
            </>,
        );

        const closeButton = screen.getByRole('button', { name: 'Close' });
        const applyButton = screen.getByRole('button', { name: 'Apply filters' });
        const ancestorHiddenButton = screen.getByRole('button', {
            name: 'Ancestor hidden filters',
            hidden: true,
        });
        const outsideTrigger = screen.getByRole('button', { name: 'Outside trigger' });

        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());

        applyButton.focus();
        await user.tab();

        expect(closeButton).toHaveFocus();
        expect(ancestorHiddenButton).not.toHaveFocus();
        expect(outsideTrigger).not.toHaveFocus();
    });

    it('never exposes a dangling aria-describedby reference on the dialog', () => {
        render(
            <DrawerPanel open onClose={() => {}} title="Filter menu items">
                Filters
            </DrawerPanel>,
        );

        const dialog = screen.getByRole('dialog');
        const describedBy = dialog.getAttribute('aria-describedby');

        if (describedBy === null) {
            return;
        }

        for (const id of describedBy.split(/\s+/).filter(Boolean)) {
            expect(document.getElementById(id)).not.toBeNull();
        }
    });

    it('excludes a focusable descendant of an aria-hidden=true ancestor from the Tab focus boundary', async () => {
        const user = userEvent.setup();

        render(
            <>
                <button type="button">Outside trigger</button>
                <DrawerPanel open onClose={() => {}} title="Filter menu items">
                    <button type="button">Apply filters</button>
                    <div aria-hidden="true">
                        <button type="button">Aria hidden ancestor filters</button>
                    </div>
                </DrawerPanel>
            </>,
        );

        const closeButton = screen.getByRole('button', { name: 'Close' });
        const applyButton = screen.getByRole('button', { name: 'Apply filters' });
        const ariaHiddenAncestorButton = screen.getByText('Aria hidden ancestor filters', {
            selector: 'button',
        });
        const outsideTrigger = screen.getByRole('button', { name: 'Outside trigger' });

        await waitFor(() => expect(screen.getByRole('dialog')).toHaveFocus());

        applyButton.focus();
        await user.tab();

        expect(closeButton).toHaveFocus();
        expect(ariaHiddenAncestorButton).not.toHaveFocus();
        expect(outsideTrigger).not.toHaveFocus();
    });
});
