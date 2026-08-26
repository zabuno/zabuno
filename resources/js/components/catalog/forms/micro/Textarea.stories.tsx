import type { Meta, StoryObj } from '@storybook/react-vite';
import { Textarea } from './Textarea';

const meta: Meta<typeof Textarea> = {
    title: 'Micro/Forms/Textarea',
    component: Textarea,
    args: {
        placeholder: 'Describe your restaurant…',
        rows: 4,
    },
};

export default meta;
type Story = StoryObj<typeof Textarea>;

export const Default: Story = {};

export const Invalid: Story = {
    args: { invalid: true, defaultValue: 'Too short.' },
};

export const Disabled: Story = {
    args: { disabled: true, defaultValue: 'Cannot edit.' },
};
