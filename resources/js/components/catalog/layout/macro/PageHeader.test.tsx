import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PageHeader } from './PageHeader';

describe('PageHeader', () => {
    it('renders the title as a heading', () => {
        render(<PageHeader title="Orders" />);
        expect(screen.getByRole('heading', { name: 'Orders' })).toBeInTheDocument();
    });

    it('omits Breadcrumbs when not given', () => {
        render(<PageHeader title="Orders" />);
        expect(screen.queryByRole('navigation', { name: 'Breadcrumb' })).not.toBeInTheDocument();
    });

    it('composes Breadcrumbs when items are given', () => {
        render(
            <PageHeader
                title="Order #42"
                breadcrumbs={[
                    { key: 'home', label: 'Home', href: '#' },
                    { key: 'order-42', label: 'Order #42' },
                ]}
            />,
        );
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Home' })).toBeInTheDocument();
    });

    it('renders the description and actions slot', () => {
        render(
            <PageHeader
                title="Orders"
                description="All orders today."
                actions={<button type="button">Export</button>}
            />,
        );
        expect(screen.getByText('All orders today.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Export' })).toBeInTheDocument();
    });
});
