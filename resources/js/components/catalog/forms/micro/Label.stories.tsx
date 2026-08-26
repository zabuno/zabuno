import type { Meta, StoryObj } from '@storybook/react-vite';
import { Label } from './Label';

const meta: Meta<typeof Label> = {
    title: 'Micro/Forms/Label',
    component: Label,
    args: {
        htmlFor: 'demo-field',
        children: 'Restaurant name',
    },
};

export default meta;
type Story = StoryObj<typeof Label>;

export const Default: Story = {};

export const Required: Story = {
    args: { required: true },
};

export const Disabled: Story = {
    args: { disabled: true },
};
