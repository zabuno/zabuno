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

/*
    Restoran panelinin GERÇEKTEN sevk edilen gezintisi.

    Fikstür önceden uydurma maddeler taşıyordu ("Orders", "QR codes") ve
    ürün ile ilgisi yoktu. Storybook'un işi sevk edilen şeyi belgelemektir;
    uydurulmuş bir fikstür, hem tasarım tartışmasını yanlış ekran üzerinden
    yürütür hem de gerçek gezinti bozulduğunda hiçbir şey fark etmez.

    Gruplar dokuz maddeyi bir SIRAYA çevirir: önce restoranı tanımla, sonra
    menüyü kurup yayınla, sonra işi yönet. Dashboard gruplanmaz — bir adım
    değil, giriş noktasıdır.
*/
const restaurantAdminGroups: SidebarNavGroup[] = [
    {
        key: 'overview',
        items: [{ key: 'dashboard', label: 'Dashboard', href: '#dashboard' }],
    },
    {
        key: 'restaurant',
        label: 'Your restaurant',
        items: [
            { key: 'brand', label: 'Brand', href: '#brand' },
            { key: 'locations', label: 'Locations', href: '#locations' },
        ],
    },
    {
        key: 'menu',
        label: 'Your menu',
        items: [
            { key: 'menu', label: 'Menu', href: '#menu' },
            { key: 'media', label: 'Media', href: '#media' },
            { key: 'publication', label: 'Publication', href: '#publication' },
        ],
    },
    {
        key: 'business',
        label: 'Your business',
        items: [
            { key: 'analytics', label: 'Analytics', href: '#analytics' },
            { key: 'team', label: 'Team', href: '#team' },
            { key: 'billing', label: 'Billing', href: '#billing' },
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
