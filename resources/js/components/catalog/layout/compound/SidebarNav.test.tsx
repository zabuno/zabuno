import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SidebarNav, type SidebarNavGroup } from './SidebarNav';

const groups: SidebarNavGroup[] = [
    {
        key: 'main',
        label: 'Menu',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
];

describe('SidebarNav', () => {
    it('exposes a Primary navigation landmark by default', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByRole('navigation', { name: 'Primary' })).toBeInTheDocument();
    });

    it('accepts a custom landmark label', () => {
        render(<SidebarNav groups={groups} label="Superadmin" />);
        expect(screen.getByRole('navigation', { name: 'Superadmin' })).toBeInTheDocument();
    });

    it('renders every item as a NavLink', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute(
            'href',
            '#dashboard',
        );
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute('href', '#orders');
    });

    it('marks the matching item as current via activeKey', () => {
        render(<SidebarNav groups={groups} activeKey="orders" />);
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute('aria-current');
    });

    it('renders an optional group heading', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByText('Menu')).toBeInTheDocument();
    });
});
