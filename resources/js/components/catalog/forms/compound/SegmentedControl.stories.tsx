import type { Meta, StoryObj } from '@storybook/react-vite';

import { SegmentedControl } from './SegmentedControl';

const meta = {
    title: 'Compound/Forms/SegmentedControl',
    component: SegmentedControl,
    parameters: {
        docs: {
            description: {
                component:
                    'Görünüşü buton dizisi, anlamı tek seçim. `radiogroup`/`radio` rolleriyle ekran okuyucu “3 seçenekten 2.” bilgisini alır; seçim renkle değil `aria-checked` ile anlatılır.',
            },
        },
    },
} satisfies Meta<typeof SegmentedControl>;

export default meta;
type Story = StoryObj<typeof meta>;

const OPTIONS = [
    { value: 'classic', label: 'Classic' },
    { value: 'bold', label: 'Bold' },
    { value: 'rounded-sm', label: 'Rounded' },
    { value: 'branded', label: 'Branded' },
];

// Story durum TUTMAZ: kapı story'leri doğrudan render eder ve hook çağıran
// bir story orada "Invalid hook call" ile düşer — yani taranamaz hâle gelir.
export const Default: Story = {
    args: { label: 'QR theme', value: 'bold', options: OPTIONS, onChange: () => {} },
};

export const Disabled: Story = {
    args: {
        label: 'QR theme',
        value: 'classic',
        options: OPTIONS,
        onChange: () => {},
        disabled: true,
    },
};
