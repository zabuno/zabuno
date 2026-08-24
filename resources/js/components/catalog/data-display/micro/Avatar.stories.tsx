import type { Meta, StoryObj } from '@storybook/react-vite';
import { Avatar } from './Avatar';

const meta: Meta<typeof Avatar> = {
    title: 'Micro/Data Display/Avatar',
    component: Avatar,
};

export default meta;
type Story = StoryObj<typeof Avatar>;

export const Initials: Story = { args: { name: 'Ada Lovelace' } };

export const SingleName: Story = { args: { name: 'Cher' } };

export const Small: Story = { args: { name: 'Grace Hopper', size: 'sm' } };

export const Large: Story = { args: { name: 'Grace Hopper', size: 'lg' } };

export const WithImage: Story = {
    args: { name: 'Ada Lovelace', src: 'https://placehold.co/64x64' },
};

export const RightToLeft: Story = {
    args: { name: 'ليلى أحمد' },
    parameters: { direction: 'rtl' },
};
