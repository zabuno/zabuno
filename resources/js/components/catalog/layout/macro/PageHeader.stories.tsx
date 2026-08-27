import type { Meta, StoryObj } from '@storybook/react-vite';
import { PageHeader } from './PageHeader';

const meta: Meta<typeof PageHeader> = {
    title: 'Macro/Layout/PageHeader',
    component: PageHeader,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Compound/Navigation/Breadcrumbs above a title/description/actions row.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof PageHeader>;

export const Default: Story = { args: { title: 'Orders' } };

export const WithBreadcrumbsAndActions: Story = {
    args: {
        title: 'Order #42',
        breadcrumbs: [
            { key: 'home', label: 'Home', href: '#' },
            { key: 'orders', label: 'Orders', href: '#orders' },
            { key: 'order-42', label: 'Order #42' },
        ],
        description: 'Placed 2 minutes ago.',
        actions: (
            <button
                type="button"
                className="rounded-md bg-blue-600 px-3 py-2 text-body font-medium text-white"
            >
                Mark ready
            </button>
        ),
    },
};

export const RightToLeft: Story = {
    args: {
        title: 'الطلبات',
        breadcrumbs: [
            { key: 'home', label: 'الرئيسية', href: '#' },
            { key: 'orders', label: 'الطلبات' },
        ],
    },
    parameters: { direction: 'rtl' },
};
