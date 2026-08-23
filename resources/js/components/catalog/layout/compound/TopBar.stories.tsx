import type { Meta, StoryObj } from '@storybook/react-vite';
import { TopBar } from './TopBar';

const meta: Meta<typeof TopBar> = {
    title: 'Compound/Layout/TopBar',
    component: TopBar,
    parameters: {
        docs: {
            description: {
                component:
                    'Composes Micro/Navigation/IconButton (mobile menu toggle) and Micro/Layout/BrandMark.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof TopBar>;

export const Default: Story = {
    args: { brand: { name: 'Zabuno', href: '#' } },
};

export const WithMobileToggle: Story = {
    args: { brand: { name: 'Zabuno', href: '#' }, onToggleMenu: () => {}, menuOpen: false },
};

export const WithEndSlot: Story = {
    args: {
        brand: { name: 'Zabuno', href: '#' },
        onToggleMenu: () => {},
        end: <span className="text-sm text-gray-500 dark:text-gray-400">owner@example.com</span>,
    },
};

export const RightToLeft: Story = {
    args: { brand: { name: 'زابونو', href: '#' }, onToggleMenu: () => {} },
    parameters: { direction: 'rtl' },
};
