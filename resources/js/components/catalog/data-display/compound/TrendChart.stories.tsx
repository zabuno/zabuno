import type { Meta, StoryObj } from '@storybook/react-vite';
import { TrendChart } from './TrendChart';

const meta: Meta<typeof TrendChart> = {
    title: 'Compound/Data Display/TrendChart',
    component: TrendChart,
    args: {
        primaryLabel: 'Scans',
        secondaryLabel: 'Menu opens',
        columnLabel: 'Day',
        description: 'Scans and menu opens, last 7 days',
    },
};

export default meta;
type Story = StoryObj<typeof TrendChart>;

const week = [
    { label: 'Mon', primary: 18, secondary: 14 },
    { label: 'Tue', primary: 0, secondary: 0 },
    { label: 'Wed', primary: 26, secondary: 21 },
    { label: 'Thu', primary: 31, secondary: 22 },
    { label: 'Fri', primary: 44, secondary: 39 },
    { label: 'Sat', primary: 61, secondary: 52 },
    { label: 'Sun', primary: 37, secondary: 30 },
];

export const Week: Story = { args: { points: week } };

/** Yeni açılmış bir restoranın ilk günü tek noktadır. */
export const SinglePoint: Story = {
    args: { points: [{ label: 'Today', primary: 4, secondary: 3 }] },
};

/** Ölçüm var, sonuç sıfır — "hiç ölçülmedi" ile aynı şey değildir. */
export const AllZero: Story = {
    args: { points: week.map((point) => ({ ...point, primary: 0, secondary: 0 })) },
};

/** Otuz günde etiketler seyrekleşir; çubuklar seyrekleşmez. */
export const Month: Story = {
    args: {
        points: Array.from({ length: 30 }, (_, index) => ({
            label: String(index + 1),
            primary: Math.round(20 + 18 * Math.sin(index / 3)),
            secondary: Math.round(16 + 14 * Math.sin(index / 3)),
        })),
        description: 'Scans and menu opens, last 30 days',
    },
};

export const RightToLeft: Story = {
    args: { points: week },
    parameters: { direction: 'rtl' },
};
