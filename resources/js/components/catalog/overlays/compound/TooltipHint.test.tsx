import { describe, expect, it } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TooltipHint } from './TooltipHint';

describe('TooltipHint', () => {
    it('does not show the hint before the trigger is hovered or focused', () => {
        render(
            <TooltipHint content="Duplicate this menu item">
                <button type="button">Duplicate</button>
            </TooltipHint>,
        );
        expect(screen.getByTestId('flowbite-tooltip')).toHaveClass('invisible');
    });

    it('shows the hint content when the trigger receives focus', async () => {
        const user = userEvent.setup();
        render(
            <TooltipHint content="Duplicate this menu item">
                <button type="button">Duplicate</button>
            </TooltipHint>,
        );

        await user.tab();

        expect(screen.getByRole('button', { name: 'Duplicate' })).toHaveFocus();
        await waitFor(() =>
            expect(screen.getByTestId('flowbite-tooltip')).not.toHaveClass('invisible'),
        );
        expect(screen.getByText('Duplicate this menu item')).toBeInTheDocument();
    });

    it('hides the hint again once focus leaves the trigger', async () => {
        const user = userEvent.setup();
        render(
            <>
                <TooltipHint content="Duplicate this menu item">
                    <button type="button">Duplicate</button>
                </TooltipHint>
                <button type="button">Somewhere else</button>
            </>,
        );

        await user.tab();
        await waitFor(() =>
            expect(screen.getByTestId('flowbite-tooltip')).not.toHaveClass('invisible'),
        );

        await user.tab();

        await waitFor(() =>
            expect(screen.getByTestId('flowbite-tooltip')).toHaveClass('invisible'),
        );
    });
});
