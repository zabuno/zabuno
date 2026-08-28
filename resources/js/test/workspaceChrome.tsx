import { DesktopSidebar } from '../components/workspace/chrome/DesktopChrome';
import { MobileNavigationDrawer } from '../components/workspace/chrome/MobileChrome';
import type { WorkspaceAppProps } from '../components/workspace/WorkspaceApp';

/**
 * Testlerde kullanılan MASAÜSTÜ kabuğu.
 *
 * `WorkspaceApp` kabuk parçalarını kendisi `import` etmez; giriş noktasından
 * alır. Sebep mimari: telefon masaüstü rayının kodunu, masaüstü de mobil
 * çekmecenin kodunu hiç indirmemeli (docs/54). Uygulama kendi içinde
 * `import` etseydi Vite ikisini de ortak parçaya koyardı ve ayrım kâğıt
 * üstünde kalırdı.
 *
 * Testlerin çoğu kalıcı gezintiyi sınadığı için varsayılan olarak masaüstü
 * kabuğu verilir — tıpkı `workspace.desktop.tsx` girişinin yaptığı gibi.
 */
export const desktopChrome: Pick<WorkspaceAppProps, 'renderPersistentSidebar'> = {
    renderPersistentSidebar: (context) => <DesktopSidebar {...context} />,
};

/**
 * Testlerde kullanılan TELEFON kabuğu.
 *
 * Masaüstü kabuğuyla aynı anda verilmez ve bu kasıtlıdır: gerçek kullanımda
 * da bir cihaz yalnız birini indirir. İkisini birlikte veren bir test,
 * üretimde hiç oluşmayan bir durumu sınardı.
 */
export const mobileChrome: Pick<WorkspaceAppProps, 'renderNavigationDrawer'> = {
    renderNavigationDrawer: (context) => <MobileNavigationDrawer {...context} />,
};
