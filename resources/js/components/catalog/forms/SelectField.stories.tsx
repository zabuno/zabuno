import type { Meta, StoryObj } from '@storybook/react-vite';
import { SelectField } from './SelectField';

/**
 * Composes: Micro/Forms/Label + Micro/Forms/Select + Flowbite's HelperText.
 * SelectField does not copy Label's or Select's markup or accessibility
 * behaviour — it only wires label/aria-describedby/aria-invalid around them.
 */
const meta: Meta<typeof SelectField> = {
    title: 'Compound/Forms/SelectField',
    component: SelectField,
    args: {
        label: 'Cuisine',
        children: (
            <>
                <option value="">Choose a cuisine…</option>
                <option value="turkish">Turkish</option>
                <option value="italian">Italian</option>
                <option value="japanese">Japanese</option>
            </>
        ),
    },
};

export default meta;
type Story = StoryObj<typeof SelectField>;

export const Default: Story = {};

export const Required: Story = {
    args: { required: true },
};

export const WithHelpText: Story = {
    args: { helpText: 'Shown to diners on the public menu.' },
};

export const WithError: Story = {
    args: { errorText: 'Please choose a cuisine.' },
};

export const Disabled: Story = {
    args: { disabled: true },
};
