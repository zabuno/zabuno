import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { AdminShell } from './AdminShell';
import type { SidebarNavGroup } from '../compound/SidebarNav';

const meta: Meta<typeof AdminShell> = {
    title: 'Macro/Layout/AdminShell',
    component: AdminShell,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Micro/Layout/SkipLink, Compound/Layout/TopBar, Compound/Layout/SidebarNav (persistent on desktop, drawer-hosted on mobile), and a `main` landmark. Mobile drawer state is externally controlled.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof AdminShell>;

const restaurantAdminGroups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
];

const superadminGroups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'tenants', label: 'Tenants', href: '#tenants' },
            { key: 'plans', label: 'Plans', href: '#plans' },
        ],
    },
];

function ControlledAdminShell(
    props: Omit<
        Parameters<typeof AdminShell>[0],
        'mobileMenuOpen' | 'onToggleMobileMenu' | 'onCloseMobileMenu'
    >,
) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    return (
        <AdminShell
            {...props}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            onCloseMobileMenu={() => setMobileMenuOpen(false)}
        />
    );
}

export const RestaurantAdmin: Story = {
    render: () => (
        <ControlledAdminShell
            brand={{ name: 'Zabuno', href: '#' }}
            navGroups={restaurantAdminGroups}
            activeNavKey="dashboard"
            navLabel="Restaurant admin"
        >
            <p className="text-body text-gray-500 dark:text-gray-400">Page content goes here.</p>
        </ControlledAdminShell>
    ),
};

export const Superadmin: Story = {
    render: () => (
        <ControlledAdminShell
            brand={{ name: 'Zabuno', href: '#' }}
            navGroups={superadminGroups}
            activeNavKey="tenants"
            navLabel="Superadmin"
        >
            <p className="text-body text-gray-500 dark:text-gray-400">Page content goes here.</p>
        </ControlledAdminShell>
    ),
};

export const MobileMenuOpen: Story = {
    args: {
        brand: { name: 'Zabuno', href: '#' },
        navGroups: restaurantAdminGroups,
        activeNavKey: 'dashboard',
        mobileMenuOpen: true,
        onToggleMobileMenu: () => {},
        onCloseMobileMenu: () => {},
        children: (
            <p className="text-body text-gray-500 dark:text-gray-400">Page content goes here.</p>
        ),
    },
    parameters: { viewport: { defaultViewport: 'mobile1' } },
};

export const RightToLeft: Story = {
    render: () => (
        <ControlledAdminShell
            brand={{ name: 'زابونو', href: '#' }}
            navGroups={[
                {
                    key: 'main',
                    items: [
                        { key: 'dashboard', label: 'لوحة التحكم', href: '#' },
                        { key: 'orders', label: 'الطلبات', href: '#orders' },
                    ],
                },
            ]}
            activeNavKey="dashboard"
        >
            <p className="text-body text-gray-500 dark:text-gray-400">محتوى الصفحة هنا.</p>
        </ControlledAdminShell>
    ),
    parameters: { direction: 'rtl' },
};
