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

/**
 * AEP kabuk grameri — teslim paketinin `DESIGN_SPEC.md` §1 "Kenar çubuğu".
 *
 * Referans kabukta grup başlığı ("Yönetim") gezinti maddelerinden BOYUTLA
 * değil TONLA ayrılır: aynı 1rem gövde ölçüsünde kalır, ağırlığı 700'e çıkar
 * ve rengi ikinci plana düşer. Bu bir süsleme tercihi değil, ölçüyle ilgili
 * bir karar: paket 16px'i her yerde taban ilan etti, çünkü küçültülmüş bir
 * başlık gözlüğünü çıkarmış bir restoran sahibi için okunmaz hâle geliyordu.
 * Büyük harfe çevirmek de yasak — Türkçede "İ/ı" dönüşümü tarayıcı diline
 * göre bozulur ve "Yönetim" kelimesi ekranda "YÖNETIM" diye çıkabilir.
 */
describe('SidebarNav — AEP kabuk grameri (FF-131)', () => {
    it('grup başlığı gövde ölçüsünde kalır, 700 ağırlıkta ve ikinci planda çizilir', () => {
        render(<SidebarNav groups={groups} />);

        const heading = screen.getByText('Menu');

        expect(heading.className).toContain('text-meta');
        expect(heading.className).toContain('font-bold');
        expect(heading.className).toContain('text-fg-secondary');
        /*
            Ağırlık ölçeği yalnız 400/500/700'dür (TOKEN_MAP "Tipografi").
            600 (`font-semibold`) Roboto'da ayrı bir kesim değildir; tarayıcı
            onu sentezler ve kenarlar temaya göre farklı kalınlaşır.
        */
        expect(heading.className).not.toContain('font-semibold');
        expect(heading.className).not.toMatch(/(^|\s)uppercase(\s|$)/);
    });

    /**
     * Rozet dili — referansta "Menüler" satırı yayınlanmamış değişiklik
     * sayısını taşır.
     *
     * Sayı satırın SONUNA yaslanır, çünkü kenar çubuğunda göz önce etiket
     * sütununu tarar; sayıyı etiketin hemen yanına koymak, ada bakan gözü her
     * satırda farklı bir yerde durduruyordu. Zemin marka sarısı, yazı ölçülmüş
     * mürekkep (`bg-action`/`text-action-fg`): marka bir bildirim rengi olarak
     * kullanılabilir ama okunabilirlik ölçüme bağlıdır.
     */
    it('gezinti maddesi satırın sonuna yaslanan bir sayı rozeti taşıyabilir', () => {
        render(
            <SidebarNav
                groups={[
                    {
                        key: 'primary',
                        items: [
                            {
                                key: 'menus',
                                label: 'Menus',
                                href: '#menus',
                                badge: '3',
                                badgeLabel: '3 unpublished changes',
                            },
                        ],
                    },
                ]}
            />,
        );

        /*
            Rozet ekran okuyucuya SAYI olarak değil CÜMLE olarak ulaşır:
            "Menüler 3" duyan biri neyin üçü olduğunu bilemez.
        */
        const link = screen.getByRole('link', { name: /unpublished changes/ });
        const badge = link.querySelector('[data-slot="sidebar-nav-badge"]') as HTMLElement;

        expect(badge).not.toBeNull();
        expect(badge.className).toContain('ms-auto');
        expect(badge.className).toContain('rounded-pill');
        expect(badge.className).toContain('bg-action');
        expect(badge.className).toContain('text-action-fg');
    });

    it('rozet verilmeyen satır boş bir kutu taşımaz', () => {
        render(<SidebarNav groups={groups} />);

        const link = screen.getByRole('link', { name: 'Dashboard' });

        expect(link.querySelector('[data-slot="sidebar-nav-badge"]')).toBeNull();
    });
});
