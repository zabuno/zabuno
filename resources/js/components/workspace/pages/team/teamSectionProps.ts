import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';

/**
 * Bölüm bağlamından EKİP ekranının özelliklerine — TEK yerde.
 *
 * İki çağıran var: paylaşılan bölüm kaydı (`TeamPage.section.tsx`) ve
 * masaüstü paketindeki sayfa haritası. `ordersSectionProps` ile aynı
 * gerekçe.
 *
 * `viewerRole` burada geçirilir ve geçirilmesi ŞARTTIR: ekipten çıkarmak ve
 * sahipliği devretmek yalnız SAHİBİN işidir, oysa ekran `workspace.manage`
 * taşıyan herkese açıktır. Eşleme iki yerde ayrı yazılsaydı, masaüstü
 * sürümü rolü unutabilir ve yöneticiye reddedilecek bir düğme çizerdi.
 *
 * `renderMemberList` BİLEREK DIŞARIDA: cihaza özgü olan tek şey odur.
 */
export function teamPropsFromContext(ctx: WorkspaceSectionRuntimeContext): {
    workspaceId: number;
    viewerRole: string | null | undefined;
} {
    return { workspaceId: ctx.workspaceId, viewerRole: ctx.role };
}
