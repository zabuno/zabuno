import type { Meta, StoryObj } from '@storybook/react-vite';

import { TextInput } from './TextInput';

const meta = {
    title: 'Micro/Forms/TextInput',
    component: TextInput,
    parameters: {
        docs: {
            description: {
                component:
                    'Tek satırlık metin alanı. Select ve Textarea ile aynı tabanı paylaşır; bir formdaki alanlar birbirine benzemek zorunda kalmaz, aynısı olur.',
            },
        },
    },
} satisfies Meta<typeof TextInput>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
    args: { 'aria-label': 'Restaurant name', placeholder: 'Zeytin Restoran' },
};
export const Invalid: Story = {
    args: { 'aria-label': 'Restaurant name', defaultValue: '', invalid: true },
};
export const Disabled: Story = {
    args: { 'aria-label': 'Restaurant name', defaultValue: 'Değiştirilemez', disabled: true },
};
