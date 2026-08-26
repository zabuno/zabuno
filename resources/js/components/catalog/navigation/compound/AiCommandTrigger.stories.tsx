import type { Meta, StoryObj } from '@storybook/react-vite';
import { AiCommandTrigger } from './AiCommandTrigger';

const meta: Meta<typeof AiCommandTrigger> = {
    title: 'Compound/Navigation/AiCommandTrigger',
    component: AiCommandTrigger,
    args: {
        label: 'AI komutu',
        onClick: () => {},
    },
};

export default meta;
type Story = StoryObj<typeof AiCommandTrigger>;

export const Default: Story = {};

/**
 * AI yüzeyi her zaman görünür ama hiçbir zaman otorite değildir (docs/14):
 * tetikleyici bir öneri kapısıdır, kritik yolculuk AI kapalıyken de yürür.
 */
export const LongLabel: Story = {
    args: { label: 'Menüde toplu fiyat güncellemesi öner' },
};
