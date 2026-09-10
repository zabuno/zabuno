import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import type { MediaPageProps } from '../MediaPage';

/**
 * Bölüm bağlamından MEDYA ekranının özelliklerine — TEK yerde.
 *
 * `ordersSectionProps.ts` ile aynı desen ve aynı sebep: iki çağıran var
 * (paylaşılan bölüm kaydı ve masaüstü paketindeki sayfa haritası) ve bu
 * eşlemeyi ayrı ayrı yazsalardı, bağlama yarın eklenen bir alan yalnız
 * birine bağlanırdı — fark da ancak masaüstünde eksik çalışan bir ekranla
 * anlaşılırdı.
 *
 * `renderLibrary` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur ve onu
 * veren taraf çağıranın kendisidir. Buraya konsaydı paylaşılan kod cihaza
 * özgü bir modülü adıyla anardı (`docs/153` §5).
 */
export function mediaPropsFromContext(
    ctx: WorkspaceSectionRuntimeContext,
): Omit<MediaPageProps, 'renderLibrary'> {
    return {
        workspaceId: ctx.workspaceId,
    };
}
