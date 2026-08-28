import { MenuInspector } from '../pages/menu/MenuInspector';
import type { WorkspaceInspectorMap } from './types';

/**
 * MASAÜSTÜNE ÖZGÜ bağlam panelleri — `docs/54`, `docs/60` §5.
 *
 * Bu harita neden bölüm kaydının (`*.section.tsx`) içinde DEĞİL:
 *
 * Bölüm kayıtları iki girişte de paylaşılır. Panel bileşeni orada `import`
 * edilirse, mobil paket onu da indirir. İlk denemede tam bu oldu: `docs/60`
 * "mobil pakette bulunmaz" diyordu, ama derlenen manifest'te mobil giriş de
 * panel yığınına ulaşıyordu — bayrak `false` olduğu için ÇİZİLMİYOR ama
 * İNDİRİLİYORDU. Bu, `docs/54`'ün reddettiği şeyin ta kendisidir: adaptive
 * bir ayrım değil, gizlenmiş ölü kod.
 *
 * Ayrım bir koşul değil, bir MODÜL SINIRI olmalı. Bu dosyayı yalnız
 * `workspace.desktop.tsx` `import` eder; mobil giriş buraya hiç ulaşmaz,
 * dolayısıyla panel kodu mobil pakete hiç girmez.
 */
export const desktopInspectors: WorkspaceInspectorMap = {
    menu: (ctx) => (
        <MenuInspector
            workspaceId={ctx.workspaceId}
            menuTree={ctx.dashboardMenuTree}
            locationName={ctx.location?.display_name ?? null}
            onNavigateToSection={ctx.onNavigateToSection}
        />
    ),
};
