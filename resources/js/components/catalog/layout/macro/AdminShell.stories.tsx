import { useState } from 'react';
import type { Meta, StoryObj } from '@storybook/react-vite';
import { AdminShell } from './AdminShell';
import { SidebarNav, type SidebarNavGroup } from '../compound/SidebarNav';
import { DrawerPanel } from '../../overlays/compound/DrawerPanel';

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

/*
    Kabuk artık gezinti verisini BİLMEZ; kalıcı ray ve çekmece ona yuva olarak
    verilir. Sebebi mimari: tenant tarafında bu iki parça CİHAZA ÖZGÜ ayrı
    modüllerde durur ve telefon masaüstü rayının kodunu hiç indirmez
    (docs/54). Hikâye de aynı sözleşmeyi kullanır.
*/
function sidebarSlot(groups: SidebarNavGroup[], activeKey?: string, label?: string) {
    return (
        <aside className="flex shrink-0 grow-0 basis-[17rem] flex-col border-e border-[var(--color-border)] p-4">
            <SidebarNav groups={groups} activeKey={activeKey} label={label} />
        </aside>
    );
}

function ControlledAdminShell({
    navGroups,
    activeNavKey,
    navLabel,
    ...props
}: Omit<
    Parameters<typeof AdminShell>[0],
    'mobileMenuOpen' | 'onToggleMobileMenu' | 'persistentSidebar' | 'navigationDrawer'
> & { navGroups: SidebarNavGroup[]; activeNavKey?: string; navLabel?: string }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    return (
        <AdminShell
            {...props}
            mobileMenuOpen={mobileMenuOpen}
            onToggleMobileMenu={() => setMobileMenuOpen((open) => !open)}
            persistentSidebar={sidebarSlot(navGroups, activeNavKey, navLabel)}
            navigationDrawer={
                <DrawerPanel
                    open={mobileMenuOpen}
                    onClose={() => setMobileMenuOpen(false)}
                    title={navLabel ?? 'Menu'}
                >
                    <SidebarNav
                        groups={navGroups}
                        activeKey={activeNavKey}
                        label={navLabel}
                        asLandmark={false}
                    />
                </DrawerPanel>
            }
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
        persistentSidebar: sidebarSlot(restaurantAdminGroups, 'dashboard'),
        navigationDrawer: (
            <DrawerPanel open onClose={() => {}} title="Restaurant admin">
                <SidebarNav
                    groups={restaurantAdminGroups}
                    activeKey="dashboard"
                    asLandmark={false}
                />
            </DrawerPanel>
        ),
        mobileMenuOpen: true,
        onToggleMobileMenu: () => {},
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
