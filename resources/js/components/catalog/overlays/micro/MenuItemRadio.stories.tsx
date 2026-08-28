import type { Meta, StoryObj } from '@storybook/react-vite';
import { MenuItemRadio } from './MenuItemRadio';

const meta = {
    title: 'Micro/Overlays/MenuItemRadio',
    component: MenuItemRadio,
    // `role="menuitemradio"` geçerli olmak için bir `role="menu"` ebeveyni
    // ister; ebeveynsiz çizmek axe'in `aria-required-parent` ihlalidir ve
    // ekran okuyucular satırı öngörülemez biçimde okur.
    decorators: [
        (Story) => (
            <div
                role="menu"
                aria-label="Örnek menü"
                className="w-56 rounded-md border border-border py-1"
            >
                <Story />
            </div>
        ),
    ],
    parameters: {
        docs: {
            description: {
                component:
                    'A single-choice row inside a menu (`role="menuitemradio"`). Used for settings that live in a menu — the appearance preference in the account menu. Selection is marked on a non-colour channel so it survives forced-colors mode.',
            },
        },
    },
} satisfies Meta<typeof MenuItemRadio>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Checked: Story = {
    args: { children: 'Dark', checked: true, onSelect: () => {} },
};

export const Unchecked: Story = {
    args: { children: 'Light', checked: false, onSelect: () => {} },
};

/** Bir menüdeki tam grup: yalnız biri işaretli olur. */
export const InAGroup: Story = {
    args: { children: 'System', checked: false, onSelect: () => {} },
    render: () => (
        <>
            <MenuItemRadio checked onSelect={() => {}}>
                System
            </MenuItemRadio>
            <MenuItemRadio checked={false} onSelect={() => {}}>
                Light
            </MenuItemRadio>
            <MenuItemRadio checked={false} onSelect={() => {}}>
                Dark
            </MenuItemRadio>
        </>
    ),
};

export const Disabled: Story = {
    args: { children: 'Dark', checked: false, onSelect: () => {}, disabled: true },
};
