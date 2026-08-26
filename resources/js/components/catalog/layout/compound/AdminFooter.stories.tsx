import type { Meta, StoryObj } from '@storybook/react-vite';
import { AdminFooter } from './AdminFooter';

const meta: Meta<typeof AdminFooter> = {
    title: 'Compound/Layout/AdminFooter',
    component: AdminFooter,
    args: {
        productName: 'Zabuno',
        currentYear: 2026,
    },
};

export default meta;
type Story = StoryObj<typeof AdminFooter>;

export const Default: Story = {};

/** Uzun ürün adı, footer'ı taşırmadan sarmalanmalı. */
export const LongProductName: Story = {
    args: { productName: 'Zabuno Restoran Menü ve Çalışma Alanı Platformu' },
};
