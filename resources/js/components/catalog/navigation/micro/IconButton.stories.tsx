import type { Meta, StoryObj } from '@storybook/react-vite';
import { IconButton } from './IconButton';

const menuIcon = (
    <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor">
        <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" strokeWidth="1.5" />
    </svg>
);

const meta: Meta<typeof IconButton> = {
    title: 'Micro/Navigation/IconButton',
    component: IconButton,
};

export default meta;
type Story = StoryObj<typeof IconButton>;

export const Default: Story = {
    args: { icon: menuIcon, label: 'Open navigation menu' },
};

export const Disabled: Story = {
    args: { icon: menuIcon, label: 'Open navigation menu', disabled: true },
};

export const RightToLeft: Story = {
    args: { icon: menuIcon, label: 'فتح قائمة التنقل' },
    parameters: { direction: 'rtl' },
};
