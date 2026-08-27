import type { Meta, StoryObj } from '@storybook/react-vite';
import { TextField } from './TextField';

/**
 * Composes: Micro/Forms/Label + Micro/Forms/TextInput + Flowbite's
 * HelperText. TextField does not copy either micro's markup or accessibility
 * behaviour — it only wires label/aria-describedby/aria-invalid around them.
 *
 * The sibling of SelectField, which existed on its own for a while. Ten forms
 * hand-rolled this pairing in the meantime, and none of them connected the
 * error message to the input, so a screen reader announced the field and the
 * error as unrelated things.
 */
const meta: Meta<typeof TextField> = {
    title: 'Compound/Forms/TextField',
    component: TextField,
    args: {
        label: 'Brand name',
        placeholder: 'Zeytin Restaurants',
    },
};

export default meta;
type Story = StoryObj<typeof TextField>;

export const Default: Story = {};

export const Required: Story = {
    args: { required: true },
};

export const WithHelpText: Story = {
    args: { helpText: 'You can change this later.' },
};

export const WithError: Story = {
    args: { errorText: 'Enter a name for your brand.' },
};

export const Disabled: Story = {
    args: { disabled: true },
};
