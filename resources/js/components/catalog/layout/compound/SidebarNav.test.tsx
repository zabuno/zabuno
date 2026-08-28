import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { SidebarNav, type SidebarNavGroup } from './SidebarNav';

const groups: SidebarNavGroup[] = [
    {
        key: 'main',
        label: 'Menu',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
];

describe('SidebarNav', () => {
    it('exposes a Primary navigation landmark by default', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByRole('navigation', { name: 'Primary' })).toBeInTheDocument();
    });

    it('accepts a custom landmark label', () => {
        render(<SidebarNav groups={groups} label="Superadmin" />);
        expect(screen.getByRole('navigation', { name: 'Superadmin' })).toBeInTheDocument();
    });

    it('renders every item as a NavLink', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute(
            'href',
            '#dashboard',
        );
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute('href', '#orders');
    });

    it('marks the matching item as current via activeKey', () => {
        render(<SidebarNav groups={groups} activeKey="orders" />);
        expect(screen.getByRole('link', { name: 'Orders' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(screen.getByRole('link', { name: 'Dashboard' })).not.toHaveAttribute('aria-current');
    });

    it('renders an optional group heading', () => {
        render(<SidebarNav groups={groups} />);
        expect(screen.getByText('Menu')).toBeInTheDocument();
    });
    /**
     * Grup başlığı, LİSTEYİ etiketler.
     *
     * Önceden başlık yalnız başlık gibi GÖRÜNEN bir `<span>`di ve hiçbir şeyi
     * etiketlemiyordu: ekran okuyucu kullanan biri kenar çubuğunda birbirinin
     * aynı, adsız listeler duyuyordu — gruplamanın taşıdığı bütün bilgi görsel
     * katmanda kalıyor, onlara hiç ulaşmıyordu.
     */
    it('her grup başlığı kendi listesini adlandırır', () => {
        render(
            <SidebarNav
                groups={[
                    { key: 'overview', items: [{ key: 'dashboard', label: 'Dashboard' }] },
                    {
                        key: 'restaurant',
                        label: 'Your restaurant',
                        items: [{ key: 'brand', label: 'Brand' }],
                    },
                ]}
            />,
        );

        expect(screen.getByRole('list', { name: 'Your restaurant' })).toBeInTheDocument();

        // Başlıksız grup adlandırılmaz — uydurulmuş bir ad, olmayan bir
        // gruplamayı varmış gibi gösterirdi.
        const lists = screen.getAllByRole('list');
        expect(lists.some((list) => !list.hasAttribute('aria-labelledby'))).toBe(true);
    });

    /**
     * İki örnek yan yana render edildiğinde kimlikler ÇAKIŞMAZ.
     *
     * `AdminShell` bu bileşeni iki kez çizer (kalıcı ray + mobil çekmece).
     * Sabit bir `id` kullanılsaydı `aria-labelledby` her iki kopyada da İLK
     * başlığa bağlanırdı.
     */
    it('aynı anda iki kez render edildiğinde grup kimlikleri çakışmaz', () => {
        const groups = [
            { key: 'menu', label: 'Your menu', items: [{ key: 'menu', label: 'Menu' }] },
        ];

        render(
            <>
                <SidebarNav groups={groups} label="Persistent" />
                <SidebarNav groups={groups} label="Drawer" />
            </>,
        );

        const ids = screen
            .getAllByRole('list', { name: 'Your menu' })
            .map((list) => list.getAttribute('aria-labelledby'));

        expect(ids).toHaveLength(2);
        expect(new Set(ids).size).toBe(2);
    });
});
