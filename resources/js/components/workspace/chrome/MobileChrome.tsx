import type { ReactNode } from 'react';
import { List } from '@phosphor-icons/react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
import { WorkspaceSwitcherTrigger, type WorkspaceSwitcherOption } from './WorkspaceSwitcherTrigger';
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
    /** Seçilebilir çalışma alanları; tek taneyse seçici menü açılmaz. */
    workspaces?: WorkspaceSwitcherOption[];
    currentWorkspaceId?: number;
    onSelectWorkspace?: (workspaceId: number) => void;
    open: boolean;
    onClose: () => void;
};

export function MobileNavigationDrawer({
    navGroups,
    activeNavKey,
    navLabel,
    workspaceName,
    workspaces,
    currentWorkspaceId,
    onSelectWorkspace,
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
                workspaces={workspaces}
                currentWorkspaceId={currentWorkspaceId}
                onSelectWorkspace={onSelectWorkspace}
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

    /*
        HÜCRE GEOMETRİSİ TEK YERDE.

        Beş sütun aynı yüksekliği, aynı yarıçapı ve aynı odak halkasını taşır;
        "Daha fazla" da bir sütundur. Beşincisi farklı davransaydı, o hücrenin
        başka türden bir şey olduğu izlenimi doğardı — oysa kullanıcı için
        hepsi aynı: bir yere git.

        Yükseklik `--control-height`'tan gelir, `--density-hit-area-min`'den
        değil: ikincisi yalnız 44px'lik TABANI bilir. Ferah moda geçen
        kullanıcı masaüstünde 52px satırlar görürken telefonda 44px'te
        kalıyordu — ayarı yaptığı yerde değişiyor, en çok ihtiyaç duyduğu
        yerde değişmiyordu. `--control-height` tabanı zaten içinde taşır.
    */
    const cellClassName = [
        'flex min-h-[var(--control-height)] flex-col items-center justify-center gap-[var(--space-1)]',
        'rounded-[var(--radius-lg)] px-[var(--space-1)] py-[var(--space-1)] text-meta',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
    ].join(' ');

    /*
        AKTİF İŞARET BİR HAP — AEP `DESIGN_SPEC` §1: "Aktif hedef: 48×28px hap
        `brand` zemin".

        Satırın tamamını tonlamak, beş hücreli dar bir çubukta iki komşu
        hücrenin arasındaki sınırı siliyordu. Hap ikonu kucaklar: göz çubuğu
        tararken önce ikon şeridine bakar, işaret de tam oraya iner.

        Hap pasif hâlde de YER TUTAR (yalnız zemini yoktur): aksi hâlde aktif
        hedef değiştikçe komşu etiketler birkaç piksel zıplardı.
    */
    const pillClassName = 'flex h-[1.75rem] w-[3rem] items-center justify-center rounded-pill';

    return (
        <nav
            aria-label={label}
            className="sticky bottom-0 z-10 grid grid-cols-5 items-stretch gap-[var(--space-1)] border-t border-[var(--color-border)] bg-[var(--color-surface)] px-[var(--space-2)] py-[var(--space-1)]"
        >
            {primary.map((item) => {
                const current = item.key === activeKey;

                return (
                    <button
                        key={item.key}
                        type="button"
                        onClick={item.onSelect}
                        aria-current={current ? 'page' : undefined}
                        className={[
                            cellClassName,
                            current ? 'font-bold text-fg' : 'text-fg-secondary',
                        ].join(' ')}
                    >
                        <span
                            aria-hidden="true"
                            data-slot="bottom-nav-pill"
                            className={[
                                pillClassName,
                                'transition-colors duration-[var(--duration-fast)] ease-[var(--easing-standard)]',
                                current ? 'bg-action text-action-fg' : '',
                            ].join(' ')}
                        >
                            {item.icon}
                        </span>
                        <span className="truncate">{item.label}</span>
                    </button>
                );
            })}
            <button
                type="button"
                onClick={onOpenMore}
                className={[cellClassName, 'text-fg-secondary'].join(' ')}
            >
                <span aria-hidden="true" data-slot="bottom-nav-pill" className={pillClassName}>
                    <List size={22} />
                </span>
                <span className="truncate">{moreLabel}</span>
            </button>
        </nav>
    );
}
