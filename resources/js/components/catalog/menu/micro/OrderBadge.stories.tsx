import type { Meta, StoryObj } from '@storybook/react-vite';
import { OrderBadge } from './OrderBadge';

const meta: Meta<typeof OrderBadge> = {
    title: 'Micro/Menu/OrderBadge',
    component: OrderBadge,
    args: {
        position: 1,
        label: 'Sıra',
    },
};

export default meta;
type Story = StoryObj<typeof OrderBadge>;

export const Default: Story = {};

/** İki basamaklı sıra numarası rozeti bozmamalı. */
export const DoubleDigit: Story = {
    args: { position: 12 },
};
