import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import type { OrdersPageProps } from '../OrdersPage';

/**
 * Bölüm bağlamından SİPARİŞLER ekranının özelliklerine — TEK yerde.
 *
 * İki çağıran var: paylaşılan bölüm kaydı (`OrdersPage.section.tsx`) ve
 * masaüstü paketindeki sayfa haritası (`pages/desktop/`). İkisi bu eşlemeyi
 * ayrı ayrı yazsaydı, bağlama yarın eklenen bir alan yalnız birine
 * bağlanır ve fark ancak masaüstünde eksik çalışan bir ekranla anlaşılırdı
 * — hem de hangi pakete baktığını bilmeyen birine.
 *
 * `renderQueue` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur ve onu
 * veren taraf çağıranın kendisidir. Buraya konsaydı paylaşılan kod cihaza
 * özgü bir modülü adıyla anardı (`docs/153` §5).
 */
export function ordersPropsFromContext(
    ctx: WorkspaceSectionRuntimeContext,
): Omit<OrdersPageProps, 'renderQueue'> {
    return {
        workspaceId: ctx.workspaceId,
        locationId: ctx.catalogLocationId,
        subPath: ctx.subPath,
        onNavigate: ctx.onNavigateToSection,
        can: ctx.can,
        /*
            Mutfak monitörü çizicisi GİRİŞ NOKTASINDAN gelir; bu dosya onu
            adıyla anmaz (`docs/54` §5). Telefon paketinde `undefined` olur
            ve ekran nedenini söyler.
        */
        renderKitchenMonitor: ctx.renderKitchenMonitor,
    };
}
