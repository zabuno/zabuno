import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';
import { AdminShell } from './AdminShell';
import { DesktopSidebar } from '../../../workspace/chrome/DesktopChrome';
import { MobileBottomNav } from '../../../workspace/chrome/MobileChrome';
import {
    ACCOUNT,
    BOTTOM_NAV,
    FRAME,
    LAYOUT,
    MAIN,
    RAIL,
    SCROLLER,
} from '../../../../../../scripts/shell-scroll-gate.classes.mjs';

/**
 * DÜZENEK GERÇEK BİLEŞENDEN SAPMASIN — `scripts/shell-scroll-gate`.
 *
 * O kapı gerçek Chrome'da DAVRANIŞI ölçüyor (kabuk ekran boyunda mı, belge
 * kayıyor mu, ana alan kaydırılabiliyor mu) ve bunu elle kurulmuş bir
 * düzenek üzerinde yapıyor: gerçek bileşenleri tarayıcıda render etmek bir
 * derleme adımı ve bir sunucu gerektirirdi.
 *
 * Bu kolaylığın bedeli SESSİZDİR. Bileşenin sınıfı değişir, düzenek eski
 * sınıfla ölçmeye devam eder, kapı yeşil kalır — ve ölçtüğü şey artık
 * ekranda olan şey değildir. Bu deponun tekrar eden kusuru tam olarak budur
 * (`docs/109` §8.7): çalışan ama söylediğini ölçmeyen bir kapı. Kabuk üç kez
 * üst üste sahibin ekranında kırıldı ve üçünde de testler yeşildi; dördüncüsü
 * bu yoldan gelebilirdi.
 *
 * Burada ölçülen: kapının düzeneğinde geçen HER sınıf, gerçek bileşenin
 * ürettiği DOM'da hâlâ duruyor mu. Bir sınıf düşerse ya da yeniden
 * adlandırılırsa burası kırılır ve düzeneğin eskidiği aynı gün görülür.
 *
 * Ölçülmeyen: sınıfların ANLAMI. Onu tarayıcı kapısı ölçüyor. İkisi
 * birlikte tam sözleşme; tek başına ikisi de yarım.
 *
 * Requirement IDs: SHELL-SCROLL-HARNESS-FRESH-01.
 */
describe('kabuk kaydırma kapısının düzeneği gerçek bileşenle aynı sınıfları taşır', () => {
    const navGroups = [
        {
            key: 'primary',
            label: 'Operations',
            items: [{ key: 'home', label: 'Home', href: '/app/x/dashboard' }],
        },
    ];

    function assertClassesPresent(element: Element | null, expected: string, where: string) {
        expect(element, `${where}: düzenekteki öğe gerçek DOM'da bulunamadı`).not.toBeNull();

        const actual = new Set(element!.className.split(/\s+/).filter(Boolean));

        for (const wanted of expected.split(/\s+/).filter(Boolean)) {
            expect(
                actual.has(wanted),
                `SHELL-SCROLL-HARNESS-FRESH-01: ${where} artık \`${wanted}\` taşımıyor — ` +
                    'tarayıcı kapısının düzeneği eskidi ve ölçtüğü şey ekrandakinden farklı.',
            ).toBe(true);
        }
    }

    it('kabuk, düzen ve ana alan', () => {
        const { container } = render(
            <AdminShell
                brand={{ name: 'Zabuno', href: '#' }}
                mobileMenuOpen={false}
                onToggleMobileMenu={vi.fn()}
            >
                <p>içerik</p>
            </AdminShell>,
        );

        assertClassesPresent(container.firstElementChild, FRAME, 'kabuk kökü');
        assertClassesPresent(container.querySelector('.admin-shell-layout'), LAYOUT, 'düzen');
        assertClassesPresent(container.querySelector('main'), MAIN, 'ana alan');
    });

    it('kenar çubuğu, kayan listesi ve hesap bloğu', () => {
        const { container } = render(
            <DesktopSidebar
                navGroups={navGroups}
                navLabel="Restaurant admin"
                accountMenu={<button type="button">admin@zabuno.com</button>}
            />,
        );

        assertClassesPresent(container.querySelector('aside'), RAIL, 'kenar çubuğu');
        assertClassesPresent(
            container.querySelector('[data-slot="sidebar-scroll"]'),
            SCROLLER,
            'kenar çubuğunun kayan listesi',
        );
        assertClassesPresent(
            container.querySelector('[data-slot="sidebar-account"]'),
            ACCOUNT,
            'hesap bloğu',
        );
    });

    it('alt gezinti çubuğu', () => {
        const { container } = render(
            <MobileBottomNav
                items={[{ key: 'home', label: 'Home', onSelect: vi.fn() }]}
                activeKey="home"
                moreLabel="More"
                onOpenMore={vi.fn()}
                label="Restaurant admin"
            />,
        );

        assertClassesPresent(container.querySelector('nav'), BOTTOM_NAV, 'alt gezinti çubuğu');
    });
});
