import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ResponsiveDataTable, type DataTableColumn } from './ResponsiveDataTable';

type MenuItemRow = { id: string; name: string; price: string };

const columns: DataTableColumn<MenuItemRow>[] = [
    { key: 'name', header: 'Name', render: (row) => row.name },
    { key: 'price', header: 'Price', align: 'end', render: (row) => row.price },
];

const rows: MenuItemRow[] = [
    { id: '1', name: 'Margherita Pizza', price: '$12.00' },
    { id: '2', name: 'Caesar Salad', price: '$8.50' },
];

describe('ResponsiveDataTable', () => {
    it('renders the caption for assistive tech', () => {
        render(
            <ResponsiveDataTable
                caption="Menu items"
                columns={columns}
                rows={rows}
                getRowKey={(row) => row.id}
            />,
        );
        expect(screen.getByText('Menu items')).toBeInTheDocument();
    });

    it('renders each column header and row value', () => {
        render(
            <ResponsiveDataTable
                caption="Menu items"
                columns={columns}
                rows={rows}
                getRowKey={(row) => row.id}
            />,
        );
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Margherita Pizza')).toBeInTheDocument();
        expect(screen.getByText('$8.50')).toBeInTheDocument();
    });

    it('renders the empty message when there are no rows and not loading', () => {
        render(
            <ResponsiveDataTable
                caption="Menu items"
                columns={columns}
                rows={[]}
                getRowKey={(row) => row.id}
                emptyMessage="No menu items yet."
            />,
        );
        expect(screen.getByText('No menu items yet.')).toBeInTheDocument();
    });

    it('renders only tr as a direct child of thead, never th (valid HTML table structure)', () => {
        const { container } = render(
            <ResponsiveDataTable
                caption="Menu items"
                columns={columns}
                rows={rows}
                getRowKey={(row) => row.id}
            />,
        );

        const thead = container.querySelector('thead');
        expect(thead).not.toBeNull();

        const directChildTagNames = Array.from((thead as HTMLTableSectionElement).children).map(
            (child) => child.tagName.toLowerCase(),
        );

        expect(directChildTagNames.length).toBeGreaterThan(0);
        expect(directChildTagNames.every((tagName) => tagName === 'tr')).toBe(true);
        expect(directChildTagNames).not.toContain('th');
    });

    it('composes Skeleton placeholder rows while loading, instead of the empty message', () => {
        render(
            <ResponsiveDataTable
                caption="Menu items"
                columns={columns}
                rows={[]}
                getRowKey={(row) => row.id}
                loading
                emptyMessage="No menu items yet."
            />,
        );
        expect(screen.queryByText('No menu items yet.')).not.toBeInTheDocument();
        // 1 header row + 3 skeleton placeholder rows.
        expect(screen.getAllByRole('row')).toHaveLength(4);
    });
});
