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

    it('calls onToggleMobileMenu when the mobile menu button is clicked', async () => {
        const onToggleMobileMenu = vi.fn();
        renderShell({ onToggleMobileMenu });
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
        // Niyet değişmedi: başlık semantic bir token tüketmeli, ham gri değil.
        // Token adı `--color-text-secondary` arbitrary sözdiziminden
        // `text-fg-subtle` utility'sine taşındı; ikisi de aynı semantic
        // katmandan gelir, ikincisi okunur ve tema/yoğunlukla birlikte akar.
        expect(heading.className).toMatch(/text-fg-subtle/);
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
