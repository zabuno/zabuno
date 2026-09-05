import type { Meta, StoryObj } from '@storybook/react-vite';
import { PageHeader } from './PageHeader';
import { Button } from '../../forms/micro/Button';

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
        /*
            Aynı gerekçe `EmptyState.stories` ile: hikâye ürünün düğmesini
            kullanır. Elle kurulmuş düğme 107×40 ölçülüyordu (`docs/117` M3)
            ve ham palet basıyordu.
        */
        actions: <Button>Mark ready</Button>,
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
