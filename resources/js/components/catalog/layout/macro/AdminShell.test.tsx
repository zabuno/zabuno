import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AdminShell } from './AdminShell';
import { SidebarNav } from '../compound/SidebarNav';
import type { SidebarNavGroup } from '../compound/SidebarNav';

const groups: SidebarNavGroup[] = [
    {
        key: 'main',
        items: [
            { key: 'dashboard', label: 'Dashboard', href: '#dashboard' },
            { key: 'orders', label: 'Orders', href: '#orders' },
        ],
    },
];

/*
    Kabuk gezinti verisini BİLMEZ; kalıcı ray ona yuva olarak verilir. Tenant
    tarafında bu parça cihaza özgü ayrı bir modülde durur ve telefon onu hiç
    indirmez (docs/54); test de aynı sözleşmeyi kullanır.
*/
function sidebarSlot(navGroups = groups, activeKey = 'dashboard', label?: string) {
    return (
        <aside className="admin-shell-sidebar">
            <SidebarNav groups={navGroups} activeKey={activeKey} label={label} />
        </aside>
    );
}

function renderShell(overrides: Partial<Parameters<typeof AdminShell>[0]> = {}) {
    return render(
        <AdminShell
            brand={{ name: 'Zabuno', href: '#' }}
            persistentSidebar={sidebarSlot()}
            mobileMenuOpen={false}
            onToggleMobileMenu={vi.fn()}
            {...overrides}
        >
            <p>Page content</p>
        </AdminShell>,
    );
}

describe('AdminShell', () => {
    it('renders a SkipLink targeting the main landmark', () => {
        renderShell();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute(
            'href',
            '#main-content',
        );
        expect(screen.getByRole('main')).toHaveAttribute('id', 'main-content');
    });

    it('renders the brand and children content', () => {
        renderShell();
        expect(screen.getByText('Zabuno')).toBeInTheDocument();
        expect(screen.getByText('Page content')).toBeInTheDocument();
    });

    it('renders the sidebar nav items as links', () => {
        renderShell();
        expect(screen.getAllByRole('link', { name: 'Dashboard' }).length).toBeGreaterThan(0);
    });

    /*
        Sahibin isteği (2026-09-04): "desktop'ta bu burger icon, kaldır."
        Kalıcı ray ekrandayken hamburger hiçbir şey açmıyordu.
    */
    /*
        KABUK GÖRÜNTÜ ALANINA KİLİTLİ — sahibin bildirimi (2026-09-04):
        "yükseklik statik ve çok fazla, ekrana göre dinamik olmalı".

        Önceki hâl `min-h-screen` idi ve içerik uzayınca kabuk da uzuyordu;
        kenar çubuğunun dibindeki hesap düğmesi sayfanın dibine gidiyor, yani
        uzun bir sayfada ekrandan çıkıyordu. Bu test o kararı dondurur:
        kök tam ekran yüksekliğinde, kaydırma ANA ALANDA.

        2026-09-05: karar aynı, MEKANİZMA değişti. Yükseklik artık `h-dvh`
        sınıfında değil, `admin-shell-frame` kuralında — çünkü `dvh` desteği
        olmayan tarayıcı için gereken `100vh` yedeğinin AYNI kuralda ve dvh'den
        ÖNCE gelmesi gerekiyor, bunu iki Tailwind sınıfıyla söylemek mümkün
        değil (kazananı sınıf sırası değil stil sayfasındaki sıra belirler).
        Ölçülen şey değişmedi: kök ekran yüksekliğinde, kaydırma ana alanda.
    */
    it('kabuk ekran yüksekliğine kilitlenir ve kaydırma ana alandadır', () => {
        const { container } = renderShell();

        const root = container.firstElementChild as HTMLElement;
        expect(root.className).toContain('admin-shell-frame');
        expect(root.className).toContain('overflow-hidden');
        // Yazdırmada kilit AÇILIR: aksi hâlde çıktı tek sayfaya kırpılırdı.
        expect(root.className).toContain('print:overflow-visible');

        const main = screen.getByRole('main');
        expect(main.className).toContain('overflow-y-auto');

        /*
            `min-h-0` olmadan bir flex çocuğunun en küçük boyu İÇERİĞİ
            kadardır ve uzun bir sayfa kabuğu yine gerdirirdi.
        */
        const layout = container.querySelector('.admin-shell-layout') as HTMLElement;
        expect(layout.className).toContain('min-h-0');
    });

    it('kalıcı kenar çubuğu varken hamburger ÇİZİLMEZ', () => {
        renderShell({ onToggleMobileMenu: vi.fn() });

        expect(screen.queryByRole('button', { name: 'Open menu' })).toBeNull();
    });

    it('calls onToggleMobileMenu when the mobile menu button is clicked', async () => {
        const onToggleMobileMenu = vi.fn();
        // Hamburger yalnız kalıcı ray ve alt çubuk YOKKEN çizilir (2026-09-04).
        renderShell({ onToggleMobileMenu, persistentSidebar: undefined });
        await userEvent.setup().click(screen.getByRole('button', { name: 'Open menu' }));
        expect(onToggleMobileMenu).toHaveBeenCalledTimes(1);
    });

    it('does not render the drawer-hosted sidebar content when mobileMenuOpen is false', () => {
        renderShell({ mobileMenuOpen: false });
        expect(screen.getAllByRole('link', { name: 'Dashboard' })).toHaveLength(1);
    });

    it('uses the admin-shell-layout/sidebar/main semantic classes for shell body, persistent sidebar, and main content', () => {
        const { container } = renderShell();
        expect(container.querySelector('.admin-shell-layout')).not.toBeNull();
        expect(container.querySelector('.admin-shell-sidebar')).not.toBeNull();
        expect(container.querySelector('.admin-shell-main')).not.toBeNull();
    });

    it('does not use fixed p-4 shell spacing on the persistent sidebar or main content', () => {
        const { container } = renderShell();
        const aside = container.querySelector('aside');
        const main = container.querySelector('main');
        expect(aside?.className ?? '').not.toMatch(/(?:^|\s)p-4(?:\s|$)/);
        expect(main?.className ?? '').not.toMatch(/(?:^|\s)p-4(?:\s|$)/);
    });

    /**
     * Kabuk artık HİÇBİR ŞEYİ gizlemiyor — verilmeyeni çizmiyor.
     *
     * Eski sözleşme şuydu: kalıcı kenar çubuğu her cihazda çizilir, sonra
     * `hidden` sınıfıyla dar ekranda CSS ile gizlenir. Bu, telefonun o yapıyı
     * yine de indirmesi, ayrıştırması ve DOM'a koyması demekti — hiç
     * göstermemek için.
     *
     * Yeni sözleşmede cihaz ayrımı SUNUCUDA yapılır (docs/54) ve kabuğa
     * yalnız o cihaza ait parça verilir. Verilmediğinde ortada gizlenecek bir
     * şey de yoktur.
     */
    it('kendisine verilmeyen kabuk parçasını çizmez', () => {
        const { container } = renderShell({ persistentSidebar: undefined });

        expect(container.querySelector('.admin-shell-sidebar')).toBeNull();
        expect(container.querySelector('main')).not.toBeNull();
    });

    it('verilen kalıcı kenar çubuğunu gizlemeden çizer', () => {
        const { container } = renderShell();
        const persistentAside = container.querySelector('.admin-shell-sidebar');

        expect(persistentAside).not.toBeNull();
        expect(persistentAside?.className ?? '').not.toMatch(/(?:^|\s)hidden(?:\s|$)/);
    });

    it('SidebarNav group heading consumes a semantic foreground token and not literal gray classes', () => {
        const labeledGroups: SidebarNavGroup[] = [
            {
                key: 'main',
                label: 'Main',
                items: [{ key: 'dashboard', label: 'Dashboard', href: '#dashboard' }],
            },
        ];
        renderShell({ persistentSidebar: sidebarSlot(labeledGroups) });
        const heading = screen.getAllByText('Main')[0];
        /*
            Niyet değişmedi: başlık SEMANTIC bir ön plan jetonu tüketmeli, ham
            gri değil. Donan şey jetonun ADI değil, ham rengin kabuğa
            sızmamasıdır — ad bir kez zaten taşındı (`--color-text-secondary`
            arbitrary sözdiziminden `text-fg-subtle` utility'sine).

            FF-131'de bir kez daha taşındı: AEP teslim paketinin `DESIGN_SPEC`
            §1'i grup başlığını `fg-secondary` diye adlandırıyor ve `TOKEN_MAP`
            ikisine de AYNI değeri veriyor (`rgb(8 6 22 / 66%)`). Yani ekranda
            hiçbir şey değişmedi; kabuk artık teslim paketiyle aynı kelimeyi
            kullanıyor. Bu yüzden ölçüm ikisini de kabul eder ve asıl kuralı —
            ham gri yasağını — olduğu gibi korur.
        */
        expect(heading.className).toMatch(/text-fg-(?:subtle|secondary)/);
        expect(heading.className).not.toMatch(/(?:^|\s)text-gray-500(?:\s|$)/);
        expect(heading.className).not.toMatch(/dark:text-gray-400(?:\s|$)/);
    });

    // Bu testler eskiden footer'ın HER ZAMAN render edilmesini donduruyordu.
    //
    // Tenant panelinde her ekranda görünen bir telif satırı hiçbir görevin
    // parçası değildir ve 320×480'de dikey alan harcar. Public ve Auth
    // kabuklarında gerçekten ortak alt bilgi vardır; orada istenir
    // (`docs/50` Faz 1).
    it('renders no footer landmark by default', () => {
        renderShell();
        expect(screen.queryByRole('contentinfo')).toBeNull();
    });

    it('renders exactly one footer landmark when the shell asks for one', () => {
        renderShell({ showFooter: true });
        const footers = screen.getAllByRole('contentinfo');
        expect(footers).toHaveLength(1);
        expect(footers[0]).toHaveTextContent('Zabuno');
    });

    /*
        ALT ÇUBUK EKRANIN DIŞINA İTİLMEZ — sahibin 2026-09-05 ekran kaydı.

        Kabuk `h-dvh` ile "görünen ekran kadar uzun ol" diyordu, ama yanında
        duran `min-h-screen` (yani `100vh`) bunu geçersiz kılıyordu: telefonda
        `100vh`, tarayıcının adres çubuğu dahil YÜKSEKLİKTİR ve görünen alandan
        BÜYÜKTÜR. min-height, height'ı yener; kabuk ekrandan taşar, belgenin
        kendisi kayar ve en alttaki gezinti çubuğu katlamanın altında kalır.

        Ekranda görülen buydu: alt menü "sticky" yazılıydı ama misafir gibi
        aşağı kayıyordu. Kusur çubukta değil, onu tutan kaptaydı — çubuk zaten
        kaydırılan alanın DIŞINDA, sabit yükseklikli bir sütunun son
        çocuğudur; kap ekranı taşmadığı sürece başka hiçbir kural gerekmez.

        Bu test o kabı ölçer: viewport yüksekliğini `vh` ile sabitleyen bir
        sınıf geri gelirse burası kırılır.
    */
    it('never pins the shell to a static viewport height that outgrows the phone screen', () => {
        const { container } = renderShell();
        const frame = container.firstElementChild;

        expect(frame).not.toBeNull();

        const classes = frame!.className.split(/\s+/);

        expect(classes).not.toContain('min-h-screen');
        expect(classes).not.toContain('h-screen');
        expect(classes).toContain('admin-shell-frame');
    });

    it('places the opt-in footer after the sidebar/main layout region', () => {
        const { container } = renderShell({ showFooter: true });
        const layout = container.querySelector('.admin-shell-layout');
        const footer = screen.getByRole('contentinfo');
        expect(layout).not.toBeNull();
        expect(
            (layout!.compareDocumentPosition(footer) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0,
        ).toBe(true);
    });
});
