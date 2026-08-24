import type { Meta, StoryObj } from '@storybook/react-vite';
import { SkipLink } from './SkipLink';

const meta: Meta<typeof SkipLink> = {
    title: 'Micro/Layout/SkipLink',
    component: SkipLink,
    parameters: {
        docs: {
            description: {
                component:
                    'Visually hidden until focused via keyboard Tab — try tabbing into the canvas to see it appear.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof SkipLink>;

export const Default: Story = {
    args: { targetId: 'main-content' },
};

export const CustomLabel: Story = {
    args: { targetId: 'main-content', children: 'İçeriğe geç' },
};

export const RightToLeft: Story = {
    args: { targetId: 'main-content', children: 'İçeriğe geç' },
    parameters: { direction: 'rtl' },
};
