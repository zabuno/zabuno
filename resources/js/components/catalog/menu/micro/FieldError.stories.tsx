import type { Meta, StoryObj } from '@storybook/react-vite';
import { FieldError } from './FieldError';

const meta: Meta<typeof FieldError> = {
    title: 'Micro/Menu/FieldError',
    component: FieldError,
    args: {
        message: 'Fiyat sıfırdan büyük olmalı.',
    },
};

export default meta;
type Story = StoryObj<typeof FieldError>;

export const Default: Story = {};

/** Uzun mesaj sarmalanmalı; tek satıra sıkışıp taşmamalı. */
export const LongMessage: Story = {
    args: {
        message:
            'Bu para birimi seçilen markanın para biriminden farklı; menü kalemi markanın para biriminde fiyatlanmalı.',
    },
};
