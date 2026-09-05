import type { Meta, StoryObj } from '@storybook/react-vite';
import { CategoryRail } from './CategoryRail';

const meta = {
    title: 'Compound/Menu/CategoryRail',
    component: CategoryRail,
    parameters: {
        docs: {
            description: {
                component:
                    'The category rail from the canonical panel source. Categories used to be stacked cards, so reaching the desserts meant scrolling past every kebab — and a category’s position on screen depended on how many products the categories above it had. The rail is fixed: name plus product count on the left, the selected category’s products on the right. On narrow screens it becomes a horizontally scrolling strip.',
            },
        },
    },
    args: {
        categories: [
            { id: 5, name: 'Kebaplar', count: 12 },
            { id: 6, name: 'Pideler', count: 8 },
            { id: 7, name: 'Tatlılar', count: 0 },
            { id: 8, name: 'İçecekler', count: 7 },
        ],
        activeCategoryId: 5,
        onSelect: () => {},
        onAddCategory: () => {},
        listLabel: 'Menu categories',
        addLabel: 'Category',
        countLabel: (count: number) => `${count} products`,
    },
} satisfies Meta<typeof CategoryRail>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/**
 * Sürükleme tutamacı yalnız `onReorder` verildiğinde çizilir: görünüp de
 * çalışmayan bir tutamaç, kullanıcıya olmayan bir söz vermektir.
 */
export const Reorderable: Story = {
    args: {
        onReorder: () => {},
        reorderHandleLabel: (name: string) => `Drag ${name} to reorder`,
    },
};

/** Boş bir ray hata değildir: yeni bir menüde yalnız ekleme düğmesi durur. */
export const Empty: Story = {
    args: { categories: [], activeCategoryId: null },
};
