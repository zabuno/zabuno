import type { Meta, StoryObj } from '@storybook/react-vite';
import { ActionMenu } from './ActionMenu';

const meta: Meta<typeof ActionMenu> = {
    title: 'Compound/Overlays/ActionMenu',
    component: ActionMenu,
    parameters: {
        docs: {
            description: {
                component: 'Composes Micro/Overlays/MenuItem with Flowbite Dropdown.',
            },
        },
    },
};

export default meta;
type Story = StoryObj<typeof ActionMenu>;

export const Default: Story = {
    args: {
        label: 'Row actions',
        items: [
            { key: 'edit', label: 'Edit', onSelect: () => {} },
            { key: 'duplicate', label: 'Duplicate', onSelect: () => {} },
            { key: 'delete', label: 'Delete', onSelect: () => {}, destructive: true },
        ],
    },
};

export const WithDisabledItem: Story = {
    args: {
        label: 'Row actions',
        items: [
            { key: 'edit', label: 'Edit', onSelect: () => {} },
            { key: 'publish', label: 'Publish', onSelect: () => {}, disabled: true },
        ],
    },
};

export const RightToLeft: Story = {
    args: {
        label: 'إجراءات الصف',
        items: [
            { key: 'edit', label: 'تعديل', onSelect: () => {} },
            { key: 'delete', label: 'حذف', onSelect: () => {}, destructive: true },
        ],
    },
    parameters: { direction: 'rtl' },
};
