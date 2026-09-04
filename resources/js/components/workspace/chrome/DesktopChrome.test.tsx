import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DesktopSidebar } from './DesktopChrome';

/**
 * FF-106 — sahibin bildirimi (2026-09-04): "yükseklik statik ve çok fazla,
 * ekrana göre dinamik olmalı ve sol alt dropdown buton sticky sabit kalmalı
 * ekranda."
 *
 * Kabuk artık görüntü alanına kilitli (`AdminShell`), ray da kendi içinde
 * kayar. Ama bu tek başına yetmez: gezinti uzayıp ray kaydığında dipteki
 * hesap düğmesi yukarı kayıp ekrandan çıkardı. Düğme rayın GÖRÜNEN alanının
 * dibine çivilenir.
 *
 * Gereksinim: SHELL-SIDEBAR-VIEWPORT-01, SHELL-ACCOUNT-STICKY-02.
 */
describe('masaüstü kenar çubuğu', () => {
    const navGroups = [
        {
            key: 'primary',
            label: 'Operations',
            items: [{ key: 'home', label: 'Home', href: '/app/x/dashboard' }],
        },
    ];

    // --- SHELL-SIDEBAR-VIEWPORT-01 ----------------------------------------
    it('ray kendi içinde kayar, kabuğu uzatmaz', () => {
        const { container } = render(
            <DesktopSidebar navGroups={navGroups} navLabel="Restaurant admin" />,
        );

        const aside = container.querySelector('aside') as HTMLElement;

        expect(aside.className).toContain('overflow-y-auto');
        /*
            `min-h-0` olmadan flex çocuğunun en küçük boyu içeriği kadardır:
            uzun bir gezinti rayı gerdirir ve kaydırma dışarı taşardı.
        */
        expect(aside.className).toContain('min-h-0');
    });

    // --- SHELL-ACCOUNT-STICKY-02 ------------------------------------------
    it('hesap düğmesi rayın dibine ÇİVİLİDİR ve zemini vardır', () => {
        render(
            <DesktopSidebar
                navGroups={navGroups}
                navLabel="Restaurant admin"
                accountMenu={<button type="button">admin@zabuno.com</button>}
            />,
        );

        const holder = screen.getByRole('button', { name: 'admin@zabuno.com' })
            .parentElement as HTMLElement;

        expect(holder.className).toContain('sticky');
        expect(holder.className).toContain('bottom-0');
        /*
            Zemin ŞART: yapışkan bir öğe saydam olsaydı, altından geçen
            gezinti maddeleri düğmenin içinden okunurdu.
        */
        expect(holder.className).toContain('bg-[var(--color-surface)]');
    });

    it('hesap menüsü verilmezse dipte boş bir kutu bırakılmaz', () => {
        const { container } = render(
            <DesktopSidebar navGroups={navGroups} navLabel="Restaurant admin" />,
        );

        expect(container.querySelector('.sticky')).toBeNull();
    });
});
