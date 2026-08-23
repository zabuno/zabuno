import type { Meta, StoryObj } from '@storybook/react-vite';
import { Breadcrumbs } from './Breadcrumbs';

const meta: Meta<typeof Breadcrumbs> = {
    title: 'Compound/Navigation/Breadcrumbs',
    component: Breadcrumbs,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Micro/Navigation/NavLink for every crumb except the last, which renders as plain current-page text per the WAI-ARIA breadcrumb pattern.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof Breadcrumbs>;

export const Default: Story = {
    args: {
        items: [
            { key: 'home', label: 'Home', href: '#' },
            { key: 'orders', label: 'Orders', href: '#orders' },
            { key: 'order-42', label: 'Order #42' },
        ],
    },
};

export const TwoLevels: Story = {
    args: {
        items: [
            { key: 'home', label: 'Home', href: '#' },
            { key: 'settings', label: 'Settings' },
        ],
    },
};

export const RightToLeft: Story = {
    args: {
        items: [
            { key: 'home', label: 'الرئيسية', href: '#' },
            { key: 'orders', label: 'الطلبات', href: '#orders' },
            { key: 'order-42', label: 'طلب #42' },
        ],
    },
    parameters: { direction: 'rtl' },
};
