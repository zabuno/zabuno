import type { Meta, StoryObj } from '@storybook/react-vite';
import { SidebarMenu } from './SidebarMenu';

/**
 * Kenar çubuğunun kendi menüsü: panel tetikleyiciyle aynı genişlikte ve ona
 * yapışık açılır. Hikâyeler dar bir kutuda çizilir, çünkü ölçülen şey tam da
 * genişlik eşitliği.
 */
const meta: Meta<typeof SidebarMenu> = {
    title: 'Compound/Overlays/SidebarMenu',
    component: SidebarMenu,
    decorators: [
        /*
            Yukarı açılan menü, tetikleyicinin ÜSTÜNDE yer ister. Kutuyu
            yüksek tutup içeriği aşağı yaslamak, hikâyede panelin kırpılmasını
            önler — kırpılmış bir panel, bileşen bozukmuş gibi görünürdü.
        */
        (Story) => (
            <div className="flex h-[22rem] w-[17rem] flex-col justify-end p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
    args: {
        label: 'Workspace',
        triggerContent: (
            <span className="flex min-w-0 flex-col">
                <span className="truncate text-body font-semibold text-fg">Zeytin Kebap</span>
                <span className="text-caption uppercase tracking-[0.08em] text-fg-muted">
                    Restaurant admin
                </span>
            </span>
        ),
        items: [
            { key: '1', label: 'Zeytin Kebap', selected: true, onSelect: () => {} },
            { key: '2', label: 'Zeytin Kadıköy', selected: false, onSelect: () => {} },
            { key: '3', label: 'Zeytin Bostancı', selected: false, onSelect: () => {} },
        ],
    },
};

export default meta;
type Story = StoryObj<typeof SidebarMenu>;

/** Kenar çubuğunun tepesi: aşağı açılır. */
export const OpensDown: Story = {
    args: { placement: 'down' },
    decorators: [
        (Story) => (
            <div className="w-[17rem] p-[var(--space-6)]">
                <Story />
            </div>
        ),
    ],
};

/** Kenar çubuğunun dibi: yukarı açılır, ok da yukarıyı gösterir. */
export const OpensUp: Story = {
    args: {
        placement: 'up',
        label: 'Account',
        triggerContent: (
            <span className="flex items-center gap-[var(--space-2)]">
                <span
                    aria-hidden="true"
                    className="flex h-[1.75rem] w-[1.75rem] shrink-0 items-center justify-center rounded-pill bg-[var(--color-surface-active)] text-meta font-semibold text-fg"
                >
                    A
                </span>
                <span className="truncate text-meta text-fg-secondary">admin@zabuno.com</span>
            </span>
        ),
        items: [
            { key: 'profile', label: 'Profile', onSelect: () => {} },
            { key: 'settings', label: 'Settings', onSelect: () => {} },
            { key: 'logout', label: 'Log out', onSelect: () => {} },
        ],
    },
};

/** Tek seçimlik ayar (tema) menünün içinde durabilir. */
export const WithSetting: Story = {
    args: {
        placement: 'up',
        radioGroup: {
            label: 'Theme',
            value: 'system',
            options: [
                { key: 'system', label: 'System' },
                { key: 'light', label: 'Light' },
                { key: 'dark', label: 'Dark' },
            ],
            onSelect: () => {},
        },
        items: [{ key: 'logout', label: 'Log out', onSelect: () => {} }],
    },
};
