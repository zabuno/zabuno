import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';

/**
 * Bölüm bağlamından MEDYA ekranının özelliklerine — TEK yerde.
 *
 * İki çağıran var: paylaşılan bölüm kaydı (`MediaPage.section.tsx`) ve
 * masaüstü paketindeki sayfa haritası (`pages/desktop/`). `ordersSectionProps`
 * ile aynı gerekçe: eşleme iki yerde yazılsaydı, bağlama yarın eklenen bir
 * alan yalnız birine bağlanır ve fark ancak masaüstünde eksik çalışan bir
 * ekranla anlaşılırdı.
 *
 * `renderLibrary` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur ve onu
 * veren taraf çağıranın kendisidir. Buraya konsaydı paylaşılan kod cihaza
 * özgü bir modülü adıyla anardı (`docs/153` §5).
 */
export function mediaPropsFromContext(ctx: WorkspaceSectionRuntimeContext): {
    workspaceId: number;
} {
    return { workspaceId: ctx.workspaceId };
}
