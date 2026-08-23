import type { Meta, StoryObj } from '@storybook/react-vite';
import { Badge } from './Badge';

const meta: Meta<typeof Badge> = {
    title: 'Micro/Feedback/Badge',
    component: Badge,
};

export default meta;
type Story = StoryObj<typeof Badge>;

export const Info: Story = { args: { status: 'info', children: 'Info' } };
export const Success: Story = { args: { status: 'success', children: 'Success' } };
export const Warning: Story = { args: { status: 'warning', children: 'Warning' } };
export const Error: Story = { args: { status: 'error', children: 'Error' } };

export const RightToLeft: Story = {
    args: { status: 'info', children: 'معلومة' },
    parameters: { direction: 'rtl' },
};
