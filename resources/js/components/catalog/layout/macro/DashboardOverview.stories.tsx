import type { Meta, StoryObj } from '@storybook/react-vite';
import { DashboardOverview } from './DashboardOverview';

type OrderRow = { id: string; customer: string; total: string };

const meta: Meta<typeof DashboardOverview<OrderRow>> = {
    title: 'Macro/Layout/DashboardOverview',
    component: DashboardOverview,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Compound/Layout/PageHeader, a grid of Compound/Data Display/StatCard, and Compound/Data Display/ResponsiveDataTable.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof DashboardOverview<OrderRow>>;

const rows: OrderRow[] = [
    { id: '#41', customer: 'Ada Lovelace', total: '$24.00' },
    { id: '#42', customer: 'Grace Hopper', total: '$18.50' },
];

const table = {
    caption: 'Recent orders',
    columns: [
        { key: 'id', header: 'Order' as const, render: (row: OrderRow) => row.id },
        { key: 'customer', header: 'Customer' as const, render: (row: OrderRow) => row.customer },
        {
            key: 'total',
            header: 'Total' as const,
            align: 'end' as const,
            render: (row: OrderRow) => row.total,
        },
    ],
    rows,
    getRowKey: (row: OrderRow) => row.id,
};

export const Default: Story = {
    args: {
        header: { title: 'Dashboard' },
        stats: [
            { key: 'orders', label: 'Orders today', value: '1,204', trend: 'up' },
            { key: 'revenue', label: 'Revenue', value: '$4,820', trend: 'down' },
        ],
        table,
    },
};

export const Loading: Story = {
    args: {
        header: { title: 'Dashboard' },
        stats: [{ key: 'orders', label: 'Orders today', value: '1,204', loading: true }],
        table: { ...table, loading: true },
    },
};

export const Empty: Story = {
    args: {
        header: { title: 'Dashboard' },
        stats: [],
        table: { ...table, rows: [] },
    },
};

export const RightToLeft: Story = {
    args: {
        header: { title: 'لوحة التحكم' },
        stats: [{ key: 'orders', label: 'الطلبات اليوم', value: '١٬٢٠٤', trend: 'up' }],
        table,
    },
    parameters: { direction: 'rtl' },
};
