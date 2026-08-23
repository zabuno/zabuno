import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TopBar } from '../catalog/layout/compound/TopBar';
import { AdminShell } from '../catalog/layout/macro/AdminShell';
import { DashboardOverview } from '../catalog/layout/macro/DashboardOverview';
import { BrandEditForm, type BrandProfile } from './BrandEditForm';

const BREAKPOINT_TOKEN = /(?:^|\s)(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: width });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: height });
    window.dispatchEvent(new Event('resize'));
}

function allClassAttributes(container: HTMLElement): string[] {
    return Array.from(container.querySelectorAll('[class]')).map(
        (el) => el.getAttribute('class') ?? '',
    );
}

const brand = { name: 'Zabuno' };

const navGroups = [
    { key: 'main', label: 'Main', items: [{ key: 'home', label: 'Home', href: '/home' }] },
];

const table = {
    caption: 'Recent orders',
    columns: [{ key: 'id', header: 'Order', render: (row: { id: string }) => row.id }],
    rows: [{ id: '#1' }],
    getRowKey: (row: { id: string }) => row.id,
};

const stats = [
    { key: 'orders', label: 'Orders today', value: '12' },
    { key: 'revenue', label: 'Revenue', value: '$400' },
    { key: 'guests', label: 'Guests', value: '30' },
];

const brandProfile: BrandProfile = {
    id: 1,
    workspace_id: 1,
    name: 'Zabuno Bistro',
    slug: 'zabuno-bistro',
    locale: 'en',
    timezone: 'UTC',
    currency: 'USD',
    description: null,
    contact_email: null,
    contact_phone: null,
};

beforeEach(() => {
    setViewport(320, 480);
});

describe('Fluid Adaptive Shell — intrinsic layout contract', () => {
    it('TopBar, AdminShell, DashboardOverview, BrandEditForm render with no breakpoint tokens', () => {
        const { container: topBarContainer } = render(
            <TopBar brand={brand} onToggleMenu={() => {}} center={<span>Search</span>} end={<span>Profile</span>} />,
        );
        const { container: shellContainer } = render(
            <AdminShell
                brand={brand}
                navGroups={navGroups}
                mobileMenuOpen={false}
                onToggleMobileMenu={() => {}}
                onCloseMobileMenu={() => {}}
            >
                <p>Content</p>
            </AdminShell>,
        );
        const { container: dashboardContainer } = render(
            <DashboardOverview header={{ title: 'Dashboard' }} stats={stats} table={table} />,
        );
        const { container: brandFormContainer } = render(
            <BrandEditForm workspaceId={1} brand={brandProfile} onSaved={() => {}} />,
        );

        for (const container of [
            topBarContainer,
            shellContainer,
            dashboardContainer,
            brandFormContainer,
        ]) {
            for (const classAttribute of allClassAttributes(container)) {
                expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
            }
        }
    });

    it('TopBar keeps toggle, center, and end in the DOM and wraps intrinsically without hidden slots', () => {
        const { container } = render(
            <TopBar
                brand={brand}
                onToggleMenu={() => {}}
                center={<span>Search</span>}
                end={<span>Profile</span>}
            />,
        );

        expect(screen.getByRole('button', { name: 'Open menu' })).toBeInTheDocument();
        expect(screen.getByText('Search')).toBeInTheDocument();
        expect(screen.getByText('Profile')).toBeInTheDocument();

        const header = container.querySelector('header');
        expect(header).not.toBeNull();
        expect(header?.className ?? '').toMatch(/flex-wrap|\[.*wrap.*\]/);

        const centerWrapper = screen.getByText('Search').parentElement;
        const toggle = screen.getByRole('button', { name: 'Open menu' });
        expect(centerWrapper?.className ?? '').not.toMatch(/(?:^|\s)hidden(?:\s|$)/);
        expect(toggle.className ?? '').not.toMatch(/(?:^|\s)hidden(?:\s|$)/);
    });

    it('AdminShell CSS-hides the persistent sidebar at the 320px start and keeps nav reachable via the drawer toggle', () => {
        const { container } = render(
            <AdminShell
                brand={brand}
                navGroups={navGroups}
                mobileMenuOpen={false}
                onToggleMobileMenu={() => {}}
                onCloseMobileMenu={() => {}}
            >
                <p>Content</p>
            </AdminShell>,
        );

        expect(container.querySelector('main')).not.toBeNull();

        // 320 CSS px is the starting proof, not a breakpoint (docs/06 §12) — the
        // persistent aside is CSS-hidden at the start; nav stays reachable
        // through the drawer toggle, not through the persistent aside.
        const persistentAside = container.querySelector('.admin-shell-sidebar');
        expect(persistentAside).not.toBeNull();
        expect(persistentAside?.className ?? '').toMatch(/(?:^|\s)hidden(?:\s|$)/);
        expect(screen.getByRole('button', { name: 'Open menu' })).toBeInTheDocument();

        const body = container.querySelector('.admin-shell-layout');
        expect(body).not.toBeNull();
        expect(body?.className ?? '').toMatch(/flex-wrap/);

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });

    it('DashboardOverview stats container uses an intrinsic auto-fit/minmax grid contract', () => {
        const { container } = render(
            <DashboardOverview header={{ title: 'Dashboard' }} stats={stats} table={table} />,
        );

        const statsHeading = screen.getByText('Orders today');
        const statsContainer = statsHeading.closest('[class*="grid"]');
        expect(statsContainer).not.toBeNull();
        expect(statsContainer?.className ?? '').toMatch(/auto-fit|minmax/);
        expect(statsContainer?.className ?? '').not.toMatch(/grid-cols-\d/);

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });

    it('BrandEditForm read-only profile dl uses an intrinsic auto-fit/minmax grid contract', () => {
        const { container } = render(
            <BrandEditForm workspaceId={1} brand={brandProfile} onSaved={() => {}} />,
        );

        const dl = container.querySelector('dl');
        expect(dl).not.toBeNull();
        expect(dl?.className ?? '').toMatch(/auto-fit|minmax/);
        expect(dl?.className ?? '').not.toMatch(/grid-cols-\d/);

        for (const classAttribute of allClassAttributes(container)) {
            expect(classAttribute).not.toMatch(BREAKPOINT_TOKEN);
        }
    });
});
