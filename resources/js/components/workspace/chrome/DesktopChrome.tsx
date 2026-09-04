import clsx from 'clsx';
import type { ReactNode } from 'react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
import { WorkspaceSwitcherTrigger, type WorkspaceSwitcherOption } from './WorkspaceSwitcherTrigger';

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
    /** Seçilebilir çalışma alanları; tek taneyse seçici menü açılmaz. */
    workspaces?: WorkspaceSwitcherOption[];
    currentWorkspaceId?: number;
    onSelectWorkspace?: (workspaceId: number) => void;
    accountMenu?: ReactNode;
};

/** Kalıcı birincil kenar çubuğu — `docs/50` §4: 248–272 px. */
export function DesktopSidebar({
    navGroups,
    activeNavKey,
    navLabel,
    workspaceName,
    workspaces,
    currentWorkspaceId,
    onSelectWorkspace,
    accountMenu,
}: DesktopChromeProps): ReactNode {
    return (
        <aside
            className={clsx(
                // Genişlik SABİT (`docs/102`): sayfa bağlam paneli açsa da ray daralmaz.
                'admin-shell-sidebar flex shrink-0 grow-0 basis-[17rem] flex-col border-e bg-[var(--color-surface)]',
                'border-[var(--color-border)] px-[var(--space-fluid-md)] py-[var(--space-fluid-md)]',
            )}
        >
            <WorkspaceSwitcherTrigger
                workspaceName={workspaceName}
                workspaces={workspaces}
                currentWorkspaceId={currentWorkspaceId}
                onSelectWorkspace={onSelectWorkspace}
            />
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />

            {/*
                Hesap tetikleyicisi kenar çubuğunun DİBİNDE — `docs/50` §7.

                `mt-auto` ile aşağı itilir, gezintinin arasına karışmaz.
                Buraya YALNIZ yardımcı işler konur: günlük kritik bir görev
                burada saklanmamalıdır, çünkü dar pencerelerde kenar çubuğunun
                alt kısmı ilk kaybolan yerdir.
            */}
            {accountMenu !== undefined && accountMenu !== null ? (
                <div className="mt-auto pt-[var(--space-fluid-sm)]">{accountMenu}</div>
            ) : null}
        </aside>
    );
}
