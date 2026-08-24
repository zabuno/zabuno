import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NavLink } from './NavLink';

describe('NavLink', () => {
    it('renders an anchor when href is given', () => {
        render(<NavLink href="/orders">Orders</NavLink>);
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute('href', '/orders');
    });

    it('renders a button when href is omitted', () => {
        render(<NavLink onSelect={() => {}}>Open menu</NavLink>);
        expect(screen.getByRole('button', { name: 'Open menu' })).toBeInTheDocument();
    });

    it('marks the current item with aria-current="page"', () => {
        render(
            <NavLink href="/orders" current>
                Orders
            </NavLink>,
        );
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute(
            'aria-current',
            'page',
        );
    });

    it('does not set aria-current when not current', () => {
        render(<NavLink href="/orders">Orders</NavLink>);
        expect(screen.getByRole('link', { name: 'Orders' })).not.toHaveAttribute('aria-current');
    });

    it('calls onSelect when activated via keyboard', async () => {
        const onSelect = vi.fn();
        render(
            <NavLink href="/orders" onSelect={onSelect}>
                Orders
            </NavLink>,
        );
        const link = screen.getByRole('link', { name: 'Orders' });
        link.focus();
        await userEvent.keyboard('{Enter}');
        expect(onSelect).toHaveBeenCalledTimes(1);
    });

    it('disables interaction and removes it from tab order when disabled', () => {
        render(
            <NavLink href="/billing" disabled>
                Billing
            </NavLink>,
        );
        const link = screen.getByText('Billing').closest('a')!;
        expect(link).toHaveAttribute('aria-disabled', 'true');
        expect(link).toHaveAttribute('tabindex', '-1');
        expect(link).not.toHaveAttribute('href');
    });
});
