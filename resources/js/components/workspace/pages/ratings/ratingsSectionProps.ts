import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import type { RatingsPageProps } from '../RatingsPage';

/**
 * Bölüm bağlamından PUANLAR ekranının özelliklerine — TEK yerde.
 *
 * İki çağıran var: paylaşılan bölüm kaydı (`RatingsPage.section.tsx`) ve
 * masaüstü paketindeki sayfa haritası. `ordersSectionProps` ile aynı
 * gerekçe.
 *
 * `menuTree` Panom'un okuduğu AYNI ağaçtır (`docs/116` P5): puanlar bir
 * menünün satırlarına dayanır ve iki ekranın aynı menüden bahsetmesi ancak
 * aynı ağaçtan okumakla garanti edilir. Eşleme iki yerde yazılsaydı,
 * masaüstü sürümü bir gün başka bir ağacı okuyabilirdi.
 *
 * `renderRatingList` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur.
 */
export function ratingsPropsFromContext(
    ctx: WorkspaceSectionRuntimeContext,
): Omit<RatingsPageProps, 'renderRatingList'> {
    return {
        workspaceId: ctx.workspaceId,
        menuTree: ctx.dashboardMenuTree,
        can: ctx.can,
        onNavigateToSection: ctx.onNavigateToSection,
    };
}
