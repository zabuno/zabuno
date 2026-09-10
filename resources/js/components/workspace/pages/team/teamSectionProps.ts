import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import type { TeamPageProps } from '../TeamPage';

/**
 * Bölüm bağlamından EKİP ekranının özelliklerine — TEK yerde.
 *
 * `ordersSectionProps.ts` / `mediaSectionProps.ts` ile aynı desen ve aynı
 * sebep: iki çağıran var (paylaşılan bölüm kaydı ve masaüstü paketindeki
 * sayfa haritası) ve bu eşlemeyi ayrı ayrı yazsalardı, bağlama yarın
 * eklenen bir alan yalnız birine bağlanırdı — fark da ancak masaüstünde
 * eksik çalışan bir ekranla anlaşılırdı.
 *
 * Rol BURADA geçirilir ve geçirilmesi zorunludur: `workspace.manage` iznini
 * Yönetici de taşır, ama ekipten çıkarmak ve sahipliği devretmek yalnız
 * Sahibin işidir (`docs/98` FF-74). İki çağırandan biri rolü unutsaydı o
 * yüzeyde yönetici, sunucunun 403 döneceği düğmeleri görürdü.
 *
 * `renderMembers` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur ve onu
 * veren taraf çağıranın kendisidir. Buraya konsaydı paylaşılan kod cihaza
 * özgü bir modülü adıyla anardı (`docs/153` §5).
 */
export function teamPropsFromContext(
    ctx: WorkspaceSectionRuntimeContext,
): Omit<TeamPageProps, 'renderMembers'> {
    return {
        workspaceId: ctx.workspaceId,
        viewerRole: ctx.role,
    };
}
