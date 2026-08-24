import type { Meta, StoryObj } from '@storybook/react-vite';
import { ProgressBar } from './ProgressBar';

const meta: Meta<typeof ProgressBar> = {
    title: 'Micro/Feedback/ProgressBar',
    component: ProgressBar,
    args: {
        label: 'Uploading…',
    },
};

export default meta;
type Story = StoryObj<typeof ProgressBar>;

export const Empty: Story = { args: { value: 0 } };
export const HalfWay: Story = { args: { value: 50 } };
export const Complete: Story = { args: { value: 100 } };

export const OutOfRangeIsClamped: Story = { args: { value: 140 } };

export const Small: Story = { args: { value: 65, size: 'sm' } };
export const ExtraLarge: Story = { args: { value: 65, size: 'xl' } };
