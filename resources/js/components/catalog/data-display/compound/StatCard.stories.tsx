import type { Meta, StoryObj } from '@storybook/react-vite';
import { StatCard } from './StatCard';

const meta: Meta<typeof StatCard> = {
    title: 'Compound/Data Display/StatCard',
    component: StatCard,
};

export default meta;
type Story = StoryObj<typeof StatCard>;

export const Default: Story = { args: { label: 'Orders today', value: '1,204', trend: 'up' } };

export const Loading: Story = { args: { label: 'Orders today', value: '1,204', loading: true } };

export const WithIcon: Story = {
    args: {
        label: 'Revenue',
        value: '$4,820',
        trend: 'down',
        icon: <span aria-hidden="true">$</span>,
    },
};

export const RightToLeft: Story = {
    args: { label: 'الطلبات اليوم', value: '١٬٢٠٤', trend: 'up' },
    parameters: { direction: 'rtl' },
};
