import type { Meta, StoryObj } from '@storybook/react-vite';
import { ResponsiveDataTable, type DataTableColumn } from './ResponsiveDataTable';

type MenuItemRow = { id: string; name: string; price: string };

const rows: MenuItemRow[] = [
    { id: '1', name: 'Margherita Pizza', price: '$12.00' },
    { id: '2', name: 'Caesar Salad', price: '$8.50' },
    { id: '3', name: 'Iced Latte', price: '$4.25' },
];

const columns: DataTableColumn<MenuItemRow>[] = [
    { key: 'name', header: 'Name', render: (row) => row.name },
    { key: 'price', header: 'Price', align: 'end', render: (row) => row.price },
];

const meta: Meta<typeof ResponsiveDataTable<MenuItemRow>> = {
    title: 'Compound/Data Display/ResponsiveDataTable',
    component: ResponsiveDataTable<MenuItemRow>,
};

export default meta;
type Story = StoryObj<typeof ResponsiveDataTable<MenuItemRow>>;

export const Default: Story = {
    args: { caption: 'Menu items', columns, rows, getRowKey: (row) => row.id },
};

export const Loading: Story = {
    args: { caption: 'Menu items', columns, rows: [], getRowKey: (row) => row.id, loading: true },
};

export const Empty: Story = {
    args: {
        caption: 'Menu items',
        columns,
        rows: [],
        getRowKey: (row) => row.id,
        emptyMessage: 'No menu items yet.',
    },
};

export const RightToLeft: Story = {
    args: {
        caption: 'عناصر القائمة',
        columns: [
            { key: 'name', header: 'الاسم', render: (row: MenuItemRow) => row.name },
            {
                key: 'price',
                header: 'السعر',
                align: 'end',
                render: (row: MenuItemRow) => row.price,
            },
        ],
        rows,
        getRowKey: (row) => row.id,
    },
    parameters: { direction: 'rtl' },
};
