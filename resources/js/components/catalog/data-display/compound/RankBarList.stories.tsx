import type { Meta, StoryObj } from '@storybook/react-vite';
import { RankBarList } from './RankBarList';

const meta: Meta<typeof RankBarList> = {
    title: 'Compound/Data Display/RankBarList',
    component: RankBarList,
    args: { valueLabel: 'scans' },
};

export default meta;
type Story = StoryObj<typeof RankBarList>;

const tables = [
    { id: 3, label: 'Table 3', value: 31 },
    { id: 8, label: 'Table 8', value: 22 },
    { id: 11, label: 'Table 11', value: 17 },
    { id: 6, label: 'Table 6', value: 14 },
    { id: 1, label: 'Table 1', value: 12 },
    { id: 9, label: 'Table 9', value: 3 },
];

export const TopFive: Story = { args: { rows: tables, limit: 5 } };

export const Unlimited: Story = { args: { rows: tables } };

/** Basılmış ama hiç okutulmamış karekodlar: 0/0 ölçeği çökertmemeli. */
export const AllZero: Story = {
    args: { rows: tables.map((row) => ({ ...row, value: 0 })), limit: 5 },
};

export const LongLabels: Story = {
    args: {
        rows: [
            { id: 1, label: 'Bahçe · uzun masa · pencere kenarı', value: 44 },
            { id: 2, label: 'Teras · köşe', value: 21 },
        ],
    },
};

export const RightToLeft: Story = {
    args: { rows: tables, limit: 5 },
    parameters: { direction: 'rtl' },
};
