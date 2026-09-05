import type { Meta, StoryObj } from '@storybook/react-vite';
import { MenuScreenActions } from './MenuScreenActions';

const meta = {
    title: 'Compound/Menu/MenuScreenActions',
    component: MenuScreenActions,
    parameters: {
        docs: {
            description: {
                component:
                    'The top strip of the Menus screen. On the left, the menu pills from the canonical panel source — one per menu at this location, each carrying its real name and its real service window; the selected pill is also the screen heading, and the menu the guest is seeing right now says so in words, not only in colour. On the right, four actions in source order: photo import, CSV, preview & publish, add product — left to right, from “a whole menu at once” to “one product”. Photo import and CSV used to live inside a collapsed disclosure, so an owner who did not know the shortcut existed typed sixty products by hand.',
            },
        },
    },
    args: {
        label: 'Menu actions',
        menusLabel: 'Menus at this location',
        menus: [
            {
                id: 1,
                name: 'Ana menü',
                hint: '11:00–07:00',
                isSelected: true,
                isServingNow: false,
            },
            {
                id: 2,
                name: 'Kahvaltı',
                hint: '07:00–11:00',
                isSelected: false,
                isServingNow: true,
            },
            { id: 3, name: 'Ramazan', hint: 'closed', isSelected: false, isServingNow: false },
        ],
        onSelectMenu: () => {},
        addMenuLabel: 'New menu',
        onAddMenu: () => {},
        editMenuLabel: 'Edit menu',
        onEditMenu: () => {},
        servingNowLabel: 'open now',
        photoImport: {
            kind: 'available',
            label: 'Import from a photo (AI)',
            onClick: () => {},
        },
        csvLabel: 'CSV',
        onCsv: () => {},
        previewAndPublishLabel: 'Preview & publish',
        onPreviewAndPublish: () => {},
        addProductLabel: 'Add product',
        onAddProduct: () => {},
    },
} satisfies Meta<typeof MenuScreenActions>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/**
 * AI kullanılamıyorsa düğme HİÇ çizilmez ama yerinde boşluk da bırakılmaz:
 * sebep yazılır, çünkü üç sebep üç ayrı çözüme gider.
 */
export const AiUnavailable: Story = {
    args: {
        photoImport: {
            kind: 'blocked',
            reason: 'This month’s AI budget is used up. Everything else keeps working.',
        },
    },
};

/**
 * Tek menülü şube — bugünkü davranış. Çoklu menü bir İMKÂNDIR, bir
 * zorunluluk değil: hiçbir şey kurmamış bir sahip tek hap görür ve o hap
 * tüm günü kaplar.
 */
export const SingleMenu: Story = {
    args: {
        menus: [
            {
                id: 1,
                name: 'Ana menü',
                hint: 'all day',
                isSelected: true,
                isServingNow: true,
            },
        ],
    },
};
