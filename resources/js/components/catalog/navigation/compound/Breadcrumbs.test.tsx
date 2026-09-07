import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Breadcrumbs } from './Breadcrumbs';

const items = [
    { key: 'home', label: 'Home', href: '#' },
    { key: 'orders', label: 'Orders', href: '#orders' },
    { key: 'order-42', label: 'Order #42' },
];

describe('Breadcrumbs', () => {
    /*
        Bölge adı ARTIK VERİLİR, varsayılan değil — `docs/121` Ö1: kodda
        gömülü `'Breadcrumb'` katalogdan hiç geçmiyordu.
    */
    it('exposes the given navigation landmark name', () => {
        render(<Breadcrumbs items={items} label="Breadcrumb" emptyLabel="Empty trail" />);
        expect(screen.getByRole('navigation', { name: 'Breadcrumb' })).toBeInTheDocument();
    });

    it('renders every non-terminal crumb as a link', () => {
        render(<Breadcrumbs items={items} label="Breadcrumb" emptyLabel="Empty trail" />);
        expect(screen.getByRole('link', { name: 'Home' })).toHaveAttribute('href', '#');
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute('href', '#orders');
    });

    it('renders the last crumb as current-page text, not a link', () => {
        render(<Breadcrumbs items={items} label="Breadcrumb" emptyLabel="Empty trail" />);
        expect(screen.queryByRole('link', { name: 'Order #42' })).not.toBeInTheDocument();
        expect(screen.getByText('Order #42')).toHaveAttribute('aria-current', 'page');
    });

    it('accepts a custom landmark label', () => {
        render(<Breadcrumbs items={items} label="Konum" emptyLabel="Boş iz" />);
        expect(screen.getByRole('navigation', { name: 'Konum' })).toBeInTheDocument();
    });

    /*
        İZ BOŞKEN DE KONUŞUR ve söylediği şey katalogdan gelir (`docs/121` Ö1).
        Cümle bileşenin içinde gömülüyken hiçbir gün çevrilemezdi.
    */
    it('boş izde çağıranın cümlesini okur', () => {
        render(<Breadcrumbs items={[]} label="Konum" emptyLabel="Boş iz" />);
        expect(screen.getByText('Boş iz')).toBeInTheDocument();
    });
});
