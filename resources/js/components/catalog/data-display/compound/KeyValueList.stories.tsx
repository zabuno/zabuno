import type { Meta, StoryObj } from '@storybook/react-vite';
import { KeyValueList } from './KeyValueList';

const meta: Meta<typeof KeyValueList> = {
    title: 'Compound/Data Display/KeyValueList',
    component: KeyValueList,
};

export default meta;
type Story = StoryObj<typeof KeyValueList>;

export const Default: Story = {
    args: {
        entries: [
            { key: 'name', label: 'Name', value: 'Ada Lovelace' },
            { key: 'role', label: 'Role', value: 'Owner' },
            { key: 'status', label: 'Status', value: 'Active' },
        ],
    },
};

export const SingleEntry: Story = {
    args: { entries: [{ key: 'name', label: 'Name', value: 'Ada Lovelace' }] },
};

export const RightToLeft: Story = {
    args: {
        entries: [
            { key: 'name', label: 'الاسم', value: 'ليلى أحمد' },
            { key: 'role', label: 'الدور', value: 'مالك' },
        ],
    },
    parameters: { direction: 'rtl' },
};
