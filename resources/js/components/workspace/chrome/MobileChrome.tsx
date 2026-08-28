import type { ReactNode } from 'react';
import { SidebarNav, type SidebarNavGroup } from '../../catalog/layout/compound/SidebarNav';
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
    open: boolean;
    onClose: () => void;
};

export function MobileNavigationDrawer({
    navGroups,
    activeNavKey,
    navLabel,
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
            <SidebarNav groups={navGroups} activeKey={activeNavKey} label={navLabel} />
        </DrawerPanel>
    );
}
