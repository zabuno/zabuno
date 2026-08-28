import clsx from 'clsx';
import type { ReactNode } from 'react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';

/**
 * MASAÜSTÜNE ÖZGÜ kabuk parçaları.
 *
 * Bu modül YALNIZ `workspace.desktop.tsx` girişinden içeri alınır. Telefonla
 * açan kullanıcı bu dosyanın hiçbir baytını indirmez — kabuğun içinde
 * `deviceClass === 'desktop'` diye bir dal bırakmak yetmezdi: o dal
 * çalışmasa bile KOD pakette bulunur, indirilir ve ayrıştırılır.
 *
 * Ayrımın tek anlamlı yeri modül sınırıdır.
 */
export type DesktopChromeProps = {
    navGroups: SidebarNavGroup[];
    activeNavKey?: string;
    navLabel?: string;
};

/** Kalıcı birincil kenar çubuğu — `docs/50` §4: 248–272 px. */
export function DesktopSidebar({
    navGroups,
    activeNavKey,
    navLabel,
}: DesktopChromeProps): ReactNode {
    return (
        <aside
            className={clsx(
                'admin-shell-sidebar flex flex-[1_1_17rem] flex-col border-e',
                'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
            )}
        >
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />
        </aside>
    );
}
