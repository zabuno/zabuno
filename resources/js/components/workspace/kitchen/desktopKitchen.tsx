import type { KitchenSurfaceRenderer } from '../pages/orders/kitchenSurface';
import { KitchenMonitor } from './KitchenMonitor';

/**
 * MASAÜSTÜ paketinin mutfak monitörü çizicisi — `docs/54`, `docs/115` S5.
 *
 * Tek işi `KitchenMonitor`'ü giriş noktasına bağlamaktır ve tam da bu yüzden
 * var: `WorkspaceApp` bileşeni kendisi `import` etseydi, Vite onu ortak
 * parçaya koyar ve telefon duvara asılmak için yazılmış bir ekranın kodunu
 * indirirdi (`docs/54` §5 — ayrım MODÜL SINIRINDA olmalı).
 *
 * `scripts/adaptive-bundle-gate` bu klasörü masaüstü-özel ilan eder ve her
 * koşuda mobil paketten ulaşılamadığını KANITLAR; yorum bir söz, kapı ise
 * kanıttır.
 */
export const desktopKitchenSurface: KitchenSurfaceRenderer = (context) => (
    <KitchenMonitor
        workspaceId={context.workspaceId}
        locationId={context.locationId}
        canAdvance={context.canAdvance}
        canDeliver={context.canDeliver}
    />
);

export default desktopKitchenSurface;
