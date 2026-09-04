import type { Meta, StoryObj } from '@storybook/react-vite';
import { InlineRename } from './InlineRename';

/**
 * Adı yerinde düzeltmek. `window.prompt` yerine geçer: düzeltme, adın
 * durduğu yerde olur ve komşu satırlar ekranda kalır.
 */
const meta = {
    title: 'Micro/Menu/InlineRename',
    component: InlineRename,
    args: {
        value: 'Mercimek Çorbası',
        label: 'Rename Mercimek Çorbası',
        emptyMessage: 'A name cannot be empty. Nothing was changed.',
        saveLabel: 'Save',
        cancelLabel: 'Cancel',
        onSubmit: (): string | null => null,
    },
    decorators: [
        (Story) => (
            <div className="max-w-[28rem] p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof InlineRename>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Kapalı hâl: ad, üzerine gelince tıklanabilir olduğunu söyler. */
export const Idle: Story = {};

/** Sunucu reddederse mesaj alanın ALTINDA kalır; düzenleme kapanmaz. */
export const ServerRefuses: Story = {
    args: {
        onSubmit: (): string | null => 'That name is already taken.',
    },
};

/** Satır bağlamında: ad solda, fiyat sağda. */
export const InARow: Story = {
    render: (args) => (
        <div className="flex items-center gap-[var(--space-3)] border-b border-border py-[var(--space-2)]">
            <span className="flex min-w-0 flex-1">
                <InlineRename {...args} />
            </span>
            <span className="shrink-0 text-body tabular-nums text-fg-secondary">45.00 TRY</span>
        </div>
    ),
};
