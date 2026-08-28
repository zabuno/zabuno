import clsx from 'clsx';
import type { ReactNode } from 'react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
import { WorkspaceSwitcherTrigger } from './WorkspaceSwitcherTrigger';

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
    workspaceName?: string;
    onSwitchWorkspace?: () => void;
};

/** Kalıcı birincil kenar çubuğu — `docs/50` §4: 248–272 px. */
export function DesktopSidebar({
    navGroups,
    activeNavKey,
    navLabel,
    workspaceName,
    onSwitchWorkspace,
}: DesktopChromeProps): ReactNode {
    return (
        <aside
            className={clsx(
                'admin-shell-sidebar flex flex-[1_1_17rem] flex-col border-e',
                'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
            )}
        >
            <WorkspaceSwitcherTrigger
                workspaceName={workspaceName}
                onSwitchWorkspace={onSwitchWorkspace}
            />
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />
        </aside>
    );
}
