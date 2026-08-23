import type { Meta, StoryObj } from '@storybook/react-vite';
import { SidebarNav, type SidebarNavGroup } from './SidebarNav';

const meta: Meta<typeof SidebarNav> = {
    title: 'Compound/Layout/SidebarNav',
    component: SidebarNav,
    parameters: {
        docs: {
            description: {
                component: 'Composes Micro/Navigation/NavLink for every item in every group.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof SidebarNav>;

const restaurantAdminGroups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
    {
        key: 'catalog',
        label: 'Menu',
        items: [
            { key: 'items', label: 'Items', href: '#items' },
            { key: 'categories', label: 'Categories', href: '#categories' },
            { key: 'qr', label: 'QR codes', href: '#qr', disabled: true },
        ],
    },
];

const superadminGroups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'tenants', label: 'Tenants', href: '#tenants' },
            { key: 'plans', label: 'Plans', href: '#plans' },
            { key: 'audit', label: 'Audit log', href: '#audit' },
        ],
    },
];

export const RestaurantAdmin: Story = {
    args: { groups: restaurantAdminGroups, activeKey: 'dashboard', label: 'Restaurant admin' },
};

export const Superadmin: Story = {
    args: { groups: superadminGroups, activeKey: 'tenants', label: 'Superadmin' },
};

export const RightToLeft: Story = {
    args: {
        groups: [
            {
                key: 'main',
                items: [
                    { key: 'dashboard', label: 'لوحة التحكم', href: '#' },
                    { key: 'orders', label: 'الطلبات', href: '#orders' },
                ],
            },
        ],
        activeKey: 'dashboard',
    },
    parameters: { direction: 'rtl' },
};
