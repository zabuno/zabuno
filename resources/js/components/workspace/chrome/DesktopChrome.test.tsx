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

/**
 * AEP kabuk grameri — teslim paketinin `DESIGN_SPEC.md` §1 "Kenar çubuğu".
 *
 * Referans kabukta ray 264px'tir ve iç dolgusu 12px'te sabittir. Depodaki hâl
 * akışkan bir dolgu (`--space-fluid-md`, 16→24px) kullanıyordu: geniş ekranda
 * ray içeriden şişiyor, aynı gezinti maddesi 1280px'te ve 1920px'te farklı
 * yerden başlıyordu. Ray bir ÇIPADIR; ekran büyüdükçe büyümesi gereken yer
 * içeriktir, çıpanın kendisi değil.
 */
describe('masaüstü kenar çubuğu — AEP grameri (FF-131)', () => {
    const navGroups = [
        {
            key: 'primary',
            label: 'Operations',
            items: [{ key: 'home', label: 'Home', href: '/app/x/dashboard' }],
        },
    ];

    it('ray 264px genişliktedir ve iç dolgusu ekranla birlikte büyümez', () => {
        const { container } = render(
            <DesktopSidebar navGroups={navGroups} navLabel="Restaurant admin" />,
        );

        const aside = container.querySelector('aside') as HTMLElement;

        expect(aside.className).toContain('basis-[16.5rem]');
        expect(aside.className).toContain('px-[var(--space-3)]');
        expect(aside.className).not.toContain('px-[var(--space-fluid-md)]');
    });

    /**
     * Dipteki Profil/Ayarlar satırları AKTİF İŞARETİNİ gezinti maddeleriyle
     * AYNI dille taşır: marka rengi bir ray olarak satırın başına iner.
     *
     * Önceki hâlde bu satırlar yalnız zemin tonu alıyordu; hemen üstlerindeki
     * `NavLink` maddeleri ise ton + marka rayı. Aynı kenar çubuğunda "buradasın"
     * demenin iki ayrı işareti vardı ve alttaki daha zayıftı — Ayarlar'a geçen
     * kullanıcı, gezinti maddelerindeki kadar net bir onay alamıyordu.
     */
    it('dipteki hedeflerin aktif satırı marka rayı taşır', () => {
        render(
            <DesktopSidebar
                navGroups={navGroups}
                navLabel="Restaurant admin"
                railSections={[
                    { key: 'profile', label: 'Profile', href: '/app/x/profile' },
                    { key: 'settings', label: 'Settings', href: '/app/x/settings', active: true },
                ]}
            />,
        );

        const active = screen.getByRole('link', { name: 'Settings' });
        const idle = screen.getByRole('link', { name: 'Profile' });

        expect(active.className).toContain('border-s-brand');
        expect(active.className).toContain('rounded-[var(--radius-lg)]');
        /*
            Pasif satır da rayın YERİNİ ayırır (saydam kenarlık): ayırmasaydı
            aktif satır 2px kayar ve etiketler tek tek yerinden oynardı.
        */
        expect(idle.className).toContain('border-s-2');
        expect(idle.className).not.toContain('border-s-brand');
    });
});
