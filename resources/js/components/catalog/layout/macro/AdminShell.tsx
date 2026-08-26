import type { ReactNode } from 'react';
import clsx from 'clsx';
import { SkipLink } from '../micro/SkipLink';
import { TopBar, type TopBarProps } from '../compound/TopBar';
import { SidebarNav, type SidebarNavGroup } from '../compound/SidebarNav';
import { AdminFooter } from '../compound/AdminFooter';
import { DrawerPanel } from '../../overlays/compound/DrawerPanel';

export type AdminShellProps = {
    brand: TopBarProps['brand'];
    navGroups: SidebarNavGroup[];
    activeNavKey?: string;
    /** Accessible name for the sidebar `<nav>` landmark, e.g. "Restaurant admin" / "Superadmin". */
    navLabel?: string;
    /** Mobile drawer open state — externally controlled (docs/35 §9). */
    mobileMenuOpen: boolean;
    onToggleMobileMenu: () => void;
    onCloseMobileMenu: () => void;
    /** Optional slot for search/global actions in the top bar. */
    topBarCenter?: ReactNode;
    /** Optional slot for profile menu / notifications / workspace switcher. */
    topBarEnd?: ReactNode;
    /** id of the main landmark; also the SkipLink target. */
    mainId?: string;
    children: ReactNode;
    className?: string;
};

/**
 * Macro: composes Micro/Layout/SkipLink, Compound/Layout/TopBar,
 * Compound/Layout/SidebarNav (rendered twice — persistent alongside main via
 * intrinsic flex-wrap, and inside Compound/Overlays/DrawerPanel for the
 * drawer affordance) around a `main` content region. Route/fetch/business-rule
 * agnostic — nav data,
 * active key, and drawer open state are all props; a surface owns wiring
 * this to a real route and persona (docs/35 §2a macro boundary, §4 shared
 * shell for both Restaurant Admin and Superadmin personas).
 */
export function AdminShell({
    brand,
    navGroups,
    activeNavKey,
    navLabel,
    mobileMenuOpen,
    onToggleMobileMenu,
    onCloseMobileMenu,
    topBarCenter,
    topBarEnd,
    mainId = 'main-content',
    children,
    className,
}: AdminShellProps) {
    return (
        <div className={clsx('flex min-h-screen flex-col', className)}>
            <SkipLink targetId={mainId} />
            <TopBar
                brand={brand}
                onToggleMenu={onToggleMobileMenu}
                menuOpen={mobileMenuOpen}
                center={topBarCenter}
                end={topBarEnd}
            />
            <div className="admin-shell-layout flex min-w-0 flex-1 flex-wrap">
                <aside
                    className={clsx(
                        'admin-shell-sidebar hidden flex-[1_1_16rem] flex-col border-e',
                        'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
                    )}
                >
                    <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />
                </aside>
                <DrawerPanel
                    open={mobileMenuOpen}
                    onClose={onCloseMobileMenu}
                    title={navLabel ?? 'Menu'}
                >
                    {/*
                        Çekmece zaten `title` ile adlandırılmış bir diyalogdur;
                        içindeki gezinti ayrı bir landmark olarak render
                        edilirse kalıcı kenar çubuğuyla aynı adı taşıyan
                        ikinci bir `<nav>` oluşur ve ekran okuyucu ikisini
                        ayırt edemez.
                    */}
                    <SidebarNav
                        groups={navGroups}
                        activeKey={activeNavKey}
                        label={navLabel}
                        asLandmark={false}
                    />
                </DrawerPanel>
                <main
                    id={mainId}
                    tabIndex={-1}
                    className="admin-shell-main min-w-0 flex-[4_1_32rem] p-[var(--space-fluid-md)] outline-none"
                >
                    {children}
                </main>
            </div>
            <AdminFooter productName={brand.name} currentYear={new Date().getFullYear()} />
        </div>
    );
}
