import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DashboardOverview } from './DashboardOverview';

type OrderRow = { id: string; customer: string };

const rows: OrderRow[] = [{ id: '#42', customer: 'Grace Hopper' }];

const table = {
    caption: 'Recent orders',
    columns: [
        { key: 'id', header: 'Order', render: (row: OrderRow) => row.id },
        { key: 'customer', header: 'Customer', render: (row: OrderRow) => row.customer },
    ],
    rows,
    getRowKey: (row: OrderRow) => row.id,
};

describe('DashboardOverview', () => {
    it('renders the PageHeader title', () => {
        render(<DashboardOverview header={{ title: 'Dashboard' }} stats={[]} table={table} />);
        expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
    });

    it('renders a StatCard per stats entry', () => {
        render(
            <DashboardOverview
                header={{ title: 'Dashboard' }}
                stats={[{ key: 'orders', label: 'Orders today', value: '1,204' }]}
                table={table}
            />,
        );
        expect(screen.getByText('Orders today')).toBeInTheDocument();
        expect(screen.getByText('1,204')).toBeInTheDocument();
    });

    it('omits the stat grid when stats is empty', () => {
        render(<DashboardOverview header={{ title: 'Dashboard' }} stats={[]} table={table} />);
        expect(screen.queryByText('Orders today')).not.toBeInTheDocument();
    });

    it('renders the ResponsiveDataTable rows', () => {
        render(<DashboardOverview header={{ title: 'Dashboard' }} stats={[]} table={table} />);
        expect(screen.getByText('Grace Hopper')).toBeInTheDocument();
    });
});
