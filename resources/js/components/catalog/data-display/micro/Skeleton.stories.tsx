import type { Meta, StoryObj } from '@storybook/react-vite';
import { Skeleton } from './Skeleton';

const meta: Meta<typeof Skeleton> = {
    title: 'Micro/Data Display/Skeleton',
    component: Skeleton,
};

export default meta;
type Story = StoryObj<typeof Skeleton>;

export const Text: Story = { args: { shape: 'text' } };

export const Circle: Story = { args: { shape: 'circle', width: '48px', height: '48px' } };

export const Rect: Story = { args: { shape: 'rect', width: '160px', height: '96px' } };

export const RightToLeft: Story = {
    args: { shape: 'text' },
    parameters: { direction: 'rtl' },
};
