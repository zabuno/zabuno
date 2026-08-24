import type { Meta, StoryObj } from '@storybook/react-vite';
import { Spinner } from './Spinner';

const meta: Meta<typeof Spinner> = {
    title: 'Micro/Feedback/Spinner',
    component: Spinner,
};

export default meta;
type Story = StoryObj<typeof Spinner>;

export const Default: Story = {};

export const CustomLabel: Story = { args: { label: 'Saving changes…' } };

export const Small: Story = { args: { size: 'sm' } };
export const Large: Story = { args: { size: 'lg' } };

export const ReducedMotion: Story = {
    args: { label: 'Loading…' },
    parameters: {
        chromatic: { disableSnapshot: true },
        docs: {
            description: {
                story: 'The spinner glyph animates via CSS; under prefers-reduced-motion the animation is left to the global stylesheet to dampen, while the aria-live label still announces progress for screen reader users regardless of motion preference.',
            },
        },
    },
};
