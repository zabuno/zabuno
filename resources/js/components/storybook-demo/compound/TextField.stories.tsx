import type { Meta, StoryObj } from '@storybook/react-vite';
import { TextField } from './TextField';

/**
 * Composes: Micro/Input (text entry) + a native <label> + optional help/error
 * text. TextField does not copy Input's markup or accessibility behaviour —
 * it only wires label/aria-describedby/aria-invalid around the Micro/Input.
 */
const meta: Meta<typeof TextField> = {
    title: 'Compound/Form/TextField',
    component: TextField,
    args: {
        label: 'Restaurant name',
        placeholder: 'e.g. Zabuno Kebap',
    },
};

export default meta;
type Story = StoryObj<typeof TextField>;

export const Default: Story = {};

export const WithHelpText: Story = {
    args: { helpText: 'Shown to diners on the public menu.' },
};

export const WithError: Story = {
    args: { errorText: 'Restaurant name is required.', defaultValue: '' },
};
