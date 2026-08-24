import type { Meta, StoryObj } from '@storybook/react-vite';
import { MenuItem } from './MenuItem';

const meta: Meta<typeof MenuItem> = {
    title: 'Micro/Overlays/MenuItem',
    component: MenuItem,
};

export default meta;
type Story = StoryObj<typeof MenuItem>;

export const Default: Story = {
    args: { children: 'Rename', onSelect: () => {} },
};

export const Disabled: Story = {
    args: { children: 'Rename', onSelect: () => {}, disabled: true },
};

export const Destructive: Story = {
    args: { children: 'Delete', onSelect: () => {}, destructive: true },
};

export const RightToLeft: Story = {
    args: { children: 'إعادة تسمية', onSelect: () => {} },
    parameters: { direction: 'rtl' },
};
