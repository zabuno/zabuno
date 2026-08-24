import type { Meta, StoryObj } from '@storybook/react-vite';
import { StatValue } from './StatValue';

const meta: Meta<typeof StatValue> = {
    title: 'Micro/Data Display/StatValue',
    component: StatValue,
};

export default meta;
type Story = StoryObj<typeof StatValue>;

export const Plain: Story = { args: { value: '1,204' } };

export const TrendingUp: Story = { args: { value: '1,204', trend: 'up' } };

export const TrendingDown: Story = { args: { value: '86', trend: 'down' } };

export const Flat: Story = { args: { value: '512', trend: 'flat' } };

export const RightToLeft: Story = {
    args: { value: '١٬٢٠٤', trend: 'up' },
    parameters: { direction: 'rtl' },
};
