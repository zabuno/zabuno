import type { Meta, StoryObj } from '@storybook/react-vite';
import { HeatGrid } from './HeatGrid';

const meta: Meta<typeof HeatGrid> = {
    title: 'Compound/Data Display/HeatGrid',
    component: HeatGrid,
    args: {
        description: 'Busiest hours',
        columnLabel: 'Day',
        hourLabel: (hour: number) => `${String(hour).padStart(2, '0')}:00`,
        withheldLabel: 'withheld',
    },
};

export default meta;
type Story = StoryObj<typeof HeatGrid>;

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function day(label: string, peak: number, height: number): { label: string; values: number[] } {
    return {
        label,
        values: Array.from({ length: 24 }, (_, hour) =>
            Math.max(0, Math.round(height * Math.exp(-((hour - peak) ** 2) / 6))),
        ),
    };
}

export const Week: Story = {
    args: { rows: DAYS.map((label, index) => day(label, 13, 10 + index * 8)) },
};

/** Menü yayında ama kimse taramamış — 0/0 ölçeği çökertmemeli. */
export const AllQuiet: Story = {
    args: {
        rows: DAYS.map((label) => ({ label, values: Array.from({ length: 24 }, () => 0) })),
    },
};

/**
 * Tek ziyaretçiye dayanan hücreler yayımlanmaz; gizlenmiş hücre sıfırdan
 * AYRI çizilir.
 */
export const WithWithheldCells: Story = {
    args: {
        rows: DAYS.map((label, index) => {
            const base = day(label, 13, 10 + index * 8);

            return {
                label,
                values: base.values.map((value, hour) => (hour < 5 ? null : value)),
            };
        }),
    },
};

export const RightToLeft: Story = {
    args: { rows: DAYS.map((label, index) => day(label, 13, 10 + index * 8)) },
    globals: { direction: 'rtl' },
};

/**
 * DAR SÜTUNDA (`docs/117` M8): ızgara hücre tabanının altına küçülmez,
 * KENDİ kabında kayar; belge kaymaz. Kaydırılabilirlik üzerine gelmeye
 * değil kenardaki solmaya bağlıdır — parmak da görür. Gün adları yerinde
 * kalır.
 */
export const InNarrowColumn: Story = {
    args: { rows: DAYS.map((label, index) => day(label, 13, 10 + index * 8)) },
    decorators: [
        (Story) => (
            <div className="max-w-[12rem]">
                <Story />
            </div>
        ),
    ],
};
