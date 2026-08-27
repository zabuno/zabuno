import type { Meta, StoryObj } from '@storybook/react-vite';
import { BrandMark } from './BrandMark';

const meta: Meta<typeof BrandMark> = {
    title: 'Micro/Layout/BrandMark',
    component: BrandMark,
};

export default meta;
type Story = StoryObj<typeof BrandMark>;

export const Default: Story = { args: { name: 'Zabuno' } };

export const WithMark: Story = {
    args: {
        name: 'Zabuno',
        mark: <span aria-hidden="true" className="inline-block h-6 w-6 rounded-sm bg-blue-600" />,
        href: '#',
    },
};

export const HiddenName: Story = {
    args: {
        name: 'Zabuno',
        mark: <span aria-hidden="true" className="inline-block h-6 w-6 rounded-sm bg-blue-600" />,
        hideName: true,
    },
};

export const RightToLeft: Story = {
    args: { name: 'زابونو' },
    parameters: { direction: 'rtl' },
};
