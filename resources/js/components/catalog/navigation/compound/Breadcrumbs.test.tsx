import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Breadcrumbs } from './Breadcrumbs';

const items = [
    { key: 'home', label: 'Home', href: '#' },
    { key: 'orders', label: 'Orders', href: '#orders' },
    { key: 'order-42', label: 'Order #42' },
];

describe('Breadcrumbs', () => {
    it('exposes a Breadcrumb navigation landmark', () => {
        render(<Breadcrumbs items={items} />);
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' })).toBeInTheDocument();
    });

    it('renders every non-terminal crumb as a link', () => {
        render(<Breadcrumbs items={items} />);
        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '#');
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute('href', '#orders');
    });

    it('renders the last crumb as current-page text, not a link', () => {
        render(<Breadcrumbs items={items} />);
        expect(screen.queryByRole('link', { name: 'Order #42' })).not.toBeInTheDocument();
        expect(screen.getByText('Order #42')).toHaveAttribute('aria-current', 'page');
    });

    it('accepts a custom landmark label', () => {
        render(<Breadcrumbs items={items} label="Konum" />);
        expect(screen.getByRole('navigation', { name: 'Konum' })).toBeInTheDocument();
    });
});
