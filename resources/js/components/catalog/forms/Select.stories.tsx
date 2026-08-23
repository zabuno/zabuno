import type { Meta, StoryObj } from '@storybook/react-vite';
import { Select } from './Select';

const meta: Meta<typeof Select> = {
    title: 'Micro/Forms/Select',
    component: Select,
};

export default meta;
type Story = StoryObj<typeof Select>;

const options = (
    <>
        <option value="">Choose a cuisine…</option>
        <option value="turkish">Turkish</option>
        <option value="italian">Italian</option>
        <option value="japanese">Japanese</option>
    </>
);

export const Default: Story = {
    args: { children: options },
};

export const Invalid: Story = {
    args: { invalid: true, children: options },
};

export const Disabled: Story = {
    args: { disabled: true, children: options },
};
