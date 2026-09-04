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
                /*
                    RAY GÖRÜNTÜ ALANI KADAR (sahibin bildirimi, 2026-09-04).

                    Kabuk artık tam ekran yüksekliğinde ve kaydırma ana alanda
                    (`AdminShell`). Ray da kendi içinde kayar: gezinti uzarsa
                    ray kayar, ama dibindeki hesap düğmesi HER ZAMAN ekranda
                    kalır — çünkü ray sayfa kadar değil, ekran kadar uzun.
                */
                'min-h-0 overflow-y-auto',
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
                /*
                    YAPIŞKAN (sahibin isteği, 2026-09-04).

                    `mt-auto` düğmeyi rayın dibine iter, ama gezinti uzayıp ray
                    kendi içinde kaydığında düğme yukarı kayıp ekrandan
                    çıkardı. `sticky bottom-0` onu rayın görünen alanının
                    dibine çiviler; gezinti altından akar.

                    Zemin ŞART: yapışkan bir öğe saydam olsaydı, altından
                    geçen gezinti maddeleri düğmenin içinden okunurdu. Üst
                    kenarlık, kaydırılacak içerik olduğunu söyleyen ince bir
                    işarettir.
                */
                <div className="sticky bottom-0 mt-auto border-t border-[var(--color-border)] bg-[var(--color-surface)] pt-[var(--space-fluid-sm)]">
                    {accountMenu}
                </div>
            ) : null}
        </aside>
    );
}
