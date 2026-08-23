import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AdminShell } from './AdminShell';
import type { SidebarNavGroup } from '../compound/SidebarNav';

const groups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
];

function renderShell(overrides: Partial<Parameters<typeof AdminShell>[0]> = {}) {
    return render(
        <AdminShell
            brand={{ name: 'Zabuno', href: '#' }}
            navGroups={groups}
            activeNavKey="dashboard"
            mobileMenuOpen={false}
            onToggleMobileMenu={vi.fn()}
            onCloseMobileMenu={vi.fn()}
            {...overrides}
        >
            <p>Page content</p>
        </AdminShell>,
    );
}

describe('AdminShell', () => {
    it('renders a SkipLink targeting the main landmark', () => {
        renderShell();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );
        expect(screen.getByRole('main')).toHaveAttribute('id', 'main-content');
    });

    it('renders the brand and children content', () => {
        renderShell();
        expect(screen.getByText('Zabuno')).toBeInTheDocument();
        expect(screen.getByText('Page content')).toBeInTheDocument();
    });

    it('renders the sidebar nav items as links', () => {
        renderShell();
        expect(screen.getAllByRole('link', { name: 'Dashboard' }).length).toBeGreaterThan(0);
    });

    it('calls onToggleMobileMenu when the mobile menu button is clicked', async () => {
        const onToggleMobileMenu = vi.fn();
        renderShell({ onToggleMobileMenu });
        await userEvent.setup().click(screen.getByRole('button', { name: 'Open menu' }));
        expect(onToggleMobileMenu).toHaveBeenCalledTimes(1);
    });

    it('does not render the drawer-hosted sidebar content when mobileMenuOpen is false', () => {
        renderShell({ mobileMenuOpen: false });
        expect(screen.getAllByRole('link', { name: 'Dashboard' })).toHaveLength(1);
    });

    it('uses the admin-shell-layout/sidebar/main semantic classes for shell body, persistent sidebar, and main content', () => {
        const { container } = renderShell();
        expect(container.querySelector('.admin-shell-layout')).not.toBeNull();
        expect(container.querySelector('.admin-shell-sidebar')).not.toBeNull();
        expect(container.querySelector('.admin-shell-main')).not.toBeNull();
    });

    it('does not use fixed p-4 shell spacing on the persistent sidebar or main content', () => {
        const { container } = renderShell();
        const aside = container.querySelector('aside');
        const main = container.querySelector('main');
        expect(aside?.className ?? '').not.toMatch(/(?:^|\s)p-4(?:\s|$)/);
        expect(main?.className ?? '').not.toMatch(/(?:^|\s)p-4(?:\s|$)/);
    });

    it('CSS-hides the persistent sidebar at the 320px start, reachable via the drawer toggle', () => {
        const { container } = renderShell();
        const persistentAside = container.querySelector('.admin-shell-sidebar');
        expect(persistentAside).not.toBeNull();
        expect(persistentAside?.className ?? '').toMatch(/(?:^|\s)hidden(?:\s|$)/);
        expect(screen.getByRole('button', { name: 'Open menu' })).toBeInTheDocument();
    });

    it('SidebarNav group heading consumes --color-text-secondary and not literal gray classes', () => {
        const labeledGroups: SidebarNavGroup[] = [
            {
                key: 'main',
                label: 'Main',
                items: [{ key: 'dashboard', label: 'Dashboard', href: '#dashboard' }],
            },
        ];
        renderShell({ navGroups: labeledGroups, activeNavKey: 'dashboard' });
        const heading = screen.getAllByText('Main')[0];
        expect(heading.className).toMatch(/text-\[var\(--color-text-secondary\)\]/);
        expect(heading.className).not.toMatch(/(?:^|\s)text-gray-500(?:\s|$)/);
        expect(heading.className).not.toMatch(/dark:text-gray-400(?:\s|$)/);
    });

    it('renders exactly one footer/contentinfo landmark for the real brand name', () => {
        renderShell();
        const footers = screen.getAllByRole('contentinfo');
        expect(footers).toHaveLength(1);
        expect(footers[0]).toHaveTextContent('Zabuno');
    });

    it('renders the footer landmark after the sidebar/main layout region', () => {
        const { container } = renderShell();
        const layout = container.querySelector('.admin-shell-layout');
        const footer = screen.getByRole('contentinfo');
        expect(layout).not.toBeNull();
        expect(
            (layout!.compareDocumentPosition(footer) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
        ).toBe(true);
    });
});
