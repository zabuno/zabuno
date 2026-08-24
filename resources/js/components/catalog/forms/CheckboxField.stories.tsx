import type { Meta, StoryObj } from '@storybook/react-vite';
import { CheckboxField } from './CheckboxField';

/**
 * Composes: Micro/Forms/Checkbox + Micro/Forms/Label + Flowbite's HelperText.
 * CheckboxField does not copy Checkbox's or Label's markup or accessibility
 * behaviour — it only wires label/aria-describedby/aria-invalid around them.
 */
const meta: Meta<typeof CheckboxField> = {
    title: 'Compound/Forms/CheckboxField',
    component: CheckboxField,
    args: {
        label: 'Accept terms and conditions',
    },
};

export default meta;
type Story = StoryObj<typeof CheckboxField>;

export const Default: Story = {};

export const Checked: Story = {
    args: { defaultChecked: true },
};

export const WithHelpText: Story = {
    args: { helpText: 'Required to publish your menu.' },
};

export const WithError: Story = {
    args: { errorText: 'You must accept the terms.' },
};

export const Disabled: Story = {
    args: { disabled: true },
};
