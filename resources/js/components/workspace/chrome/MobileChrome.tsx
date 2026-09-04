import type { ReactNode } from 'react';
import { List } from '@phosphor-icons/react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
import { WorkspaceSwitcherTrigger } from './WorkspaceSwitcherTrigger';
import { DrawerPanel } from '../../catalog/overlays/compound/DrawerPanel';

/**
 * TELEFONA ÖZGÜ kabuk parçaları.
 *
 * Bu modül YALNIZ `workspace.mobile.tsx` girişinden içeri alınır. Masaüstünde
 * kalıcı bir ray zaten görünürken çekmece hiç açılmaz; o hâlde çekmecenin
 * kodunun da masaüstü paketinde bulunmasının bir karşılığı yoktur.
 */
export type MobileChromeProps = {
    navGroups: SidebarNavGroup[];
    activeNavKey?: string;
    navLabel?: string;
    workspaceName?: string;
    onSwitchWorkspace?: () => void;
    open: boolean;
    onClose: () => void;
};

export function MobileNavigationDrawer({
    navGroups,
    activeNavKey,
    navLabel,
    workspaceName,
    onSwitchWorkspace,
    open,
    onClose,
}: MobileChromeProps): ReactNode {
    return (
        <DrawerPanel open={open} onClose={onClose} title={navLabel ?? 'Menu'}>
            {/*
                Çekmecedeki gezinti bir LANDMARK'tır.

                Eski kabukta `asLandmark={false}` veriliyordu, çünkü kalıcı
                kenar çubuğu da aynı anda çiziliyordu ve aynı adı taşıyan iki
                `<nav>` oluşuyordu. Telefon paketinde kalıcı ray artık HİÇ
                yok (docs/54); landmark'ı da kapatmak, ekran okuyucu kullanan
                birinin telefonda gezintiye landmark listesinden hiç
                ulaşamaması demek olurdu.
            */}
            <WorkspaceSwitcherTrigger
                workspaceName={workspaceName}
                onSwitchWorkspace={onSwitchWorkspace}
            />
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />
        </DrawerPanel>
    );
}

export type MobileBottomNavProps = {
    /** Kayıttan gelen birincil hedefler; en fazla dördü çizilir (`docs/50` §20). */
    items: { key: string; label: string; icon?: ReactNode; onSelect: () => void }[];
    activeKey?: string;
    moreLabel: string;
    onOpenMore: () => void;
    label: string;
};

/**
 * TELEFON ALT GEZİNTİSİ — ekranın altında, başparmağın altında.
 *
 * Üst çubuktaki hamburger, telefonda en uzak köşedeydi ve her gezinti iki
 * adım gerektiriyordu: menüyü aç, sonra seç. Alt çubuk günlük dört hedefi
 * TEK dokunuşa indirir; geri kalanı "More" çekmeceyi açar. Hamburger, bu
 * çubuk varken üst çubuktan kalkar (`AdminShell.bottomBar`).
 */
export function MobileBottomNav({
    items,
    activeKey,
    moreLabel,
    onOpenMore,
    label,
}: MobileBottomNavProps): ReactNode {
    const primary = items.slice(0, 4);

    return (
        <nav
            aria-label={label}
            className="sticky bottom-0 z-10 flex items-stretch justify-between gap-[var(--space-1)] border-t border-[var(--color-border)] bg-[var(--color-surface)] px-[var(--space-2)] py-[var(--space-1)]"
        >
            {primary.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    onClick={item.onSelect}
                    aria-current={item.key === activeKey ? 'page' : undefined}
                    className={[
                        'flex min-h-[var(--density-hit-area-min)] flex-1 flex-col items-center justify-center gap-[var(--space-1)]',
                        'rounded-[var(--radius-md)] px-[var(--space-1)] py-[var(--space-1)] text-caption',
                        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                        item.key === activeKey
                            ? 'bg-surface-active font-semibold text-fg'
                            : 'text-fg-secondary',
                    ].join(' ')}
                >
                    {item.icon ? (
                        <span aria-hidden="true" className="shrink-0">
                            {item.icon}
                        </span>
                    ) : null}
                    <span className="truncate">{item.label}</span>
                </button>
            ))}
            <button
                type="button"
                onClick={onOpenMore}
                className="flex min-h-[var(--density-hit-area-min)] flex-1 flex-col items-center justify-center gap-[var(--space-1)] rounded-[var(--radius-md)] px-[var(--space-1)] py-[var(--space-1)] text-caption text-fg-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
            >
                <span aria-hidden="true" className="shrink-0">
                    <List size={18} />
                </span>
                <span className="truncate">{moreLabel}</span>
            </button>
        </nav>
    );
}
