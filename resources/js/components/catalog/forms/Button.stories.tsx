import type { Meta, StoryObj } from '@storybook/react-vite';
import { Button } from './Button';

const meta: Meta<typeof Button> = {
    title: 'Micro/Forms/Button',
    component: Button,
    args: {
        children: 'Save changes',
    },
};

export default meta;
type Story = StoryObj<typeof Button>;

export const Default: Story = {};

export const Loading: Story = {
    args: { loading: true, loadingText: 'Saving…' },
};

export const Disabled: Story = {
    args: { disabled: true },
};

export const Outline: Story = {
    args: { outline: true },
};
