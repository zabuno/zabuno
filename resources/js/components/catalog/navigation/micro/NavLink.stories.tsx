import type { Meta, StoryObj } from '@storybook/react-vite';
import { NavLink } from './NavLink';

const meta: Meta<typeof NavLink> = {
    title: 'Micro/Navigation/NavLink',
    component: NavLink,
};

export default meta;
type Story = StoryObj<typeof NavLink>;

export const AsLink: Story = {
    args: { href: '#dashboard', children: 'Dashboard' },
};

export const AsButton: Story = {
    args: { children: 'Open menu', onSelect: () => {} },
};

export const CurrentPage: Story = {
    args: { href: '#orders', children: 'Orders', current: true },
};

export const WithIcon: Story = {
    args: {
        href: '#settings',
        children: 'Settings',
        icon: (
            <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor">
                <circle cx="10" cy="10" r="6" />
            </svg>
        ),
    },
};

export const Disabled: Story = {
    args: { href: '#billing', children: 'Billing', disabled: true },
};

export const RightToLeft: Story = {
    args: { href: '#dashboard', children: 'لوحة التحكم', current: true },
    parameters: { direction: 'rtl' },
};
