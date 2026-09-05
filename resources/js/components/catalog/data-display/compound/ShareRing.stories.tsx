import type { Meta, StoryObj } from '@storybook/react-vite';
import { ShareRing } from './ShareRing';

const meta: Meta<typeof ShareRing> = {
    title: 'Compound/Data Display/ShareRing',
    component: ShareRing,
    args: {
        description: 'Share by location',
        formatValue: (value: number) => `${value} scans`,
        formatPercent: (percent: number) => `${percent}%`,
    },
};

export default meta;
type Story = StoryObj<typeof ShareRing>;

export const ThreeLocations: Story = {
    args: {
        slices: [
            { id: 1, label: 'Kadıköy', value: 842, percent: 58.1 },
            { id: 2, label: 'Beşiktaş', value: 511, percent: 35.3 },
            { id: 3, label: 'Bostancı', value: 96, percent: 6.6 },
        ],
    },
};

/** Tek şubede pay %100'dür ve halka kapanır. */
export const SingleLocation: Story = {
    args: { slices: [{ id: 1, label: 'Kadıköy', value: 214, percent: 100 }] },
};

/** Üçten sonra tonlar başa döner: sıralamayı taşıyan efsanedir, renk değil. */
export const ManyLocations: Story = {
    args: {
        slices: [
            { id: 1, label: 'Kadıköy', value: 842, percent: 46.2 },
            { id: 2, label: 'Beşiktaş', value: 511, percent: 28.0 },
            { id: 3, label: 'Bostancı', value: 296, percent: 16.2 },
            { id: 4, label: 'Üsküdar', value: 174, percent: 9.6 },
        ],
    },
};

export const RightToLeft: Story = {
    args: {
        slices: [
            { id: 1, label: 'Kadıköy', value: 842, percent: 58.1 },
            { id: 2, label: 'Beşiktaş', value: 511, percent: 41.9 },
        ],
    },
    parameters: { direction: 'rtl' },
};
