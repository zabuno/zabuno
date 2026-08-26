import type { Meta, StoryObj } from '@storybook/react-vite';
import { Checkbox } from './Checkbox';

const meta: Meta<typeof Checkbox> = {
    title: 'Micro/Forms/Checkbox',
    component: Checkbox,
    args: {
        'aria-label': 'Accept terms',
    },
};

export default meta;
type Story = StoryObj<typeof Checkbox>;

export const Default: Story = {};

export const Checked: Story = {
    args: { defaultChecked: true },
};

export const Invalid: Story = {
    args: { invalid: true },
};

export const Disabled: Story = {
    args: { disabled: true },
};
