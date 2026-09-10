import type { ReactNode } from 'react';

import { TeamPage } from '../TeamPage';
import { teamPropsFromContext } from '../team/teamSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { TeamMemberTableDesktop } from './TeamMemberTableDesktop';

/**
 * EKİP ekranının MASAÜSTÜ bileşimi — `docs/153` §6.
 *
 * `OrdersScreenDesktop` / `MediaScreenDesktop` ile aynı biçim ve aynı
 * sebep: sayfanın kendisi (üye ve davet okuması, davet formu, rol rehberi,
 * çıkarma/devretme mutasyonları, izin kapıları) PAYLAŞILANdır ve buraya
 * kopyalanmaz — iki kopya yetki mantığı, yarın bir izin değiştiğinde yalnız
 * birinde düzeltilirdi. Değişen tek şey ÜYE BÖLGESİNİN çizimidir ve o da
 * bir çizici olarak geçirilir.
 *
 * Yani bu dosya bir sayfa DEĞİL, bir BİLEŞİMdir: paylaşılan sayfa + bu
 * cihazın üye tablosu. Ortak olan paylaşılır, ayrışan enjekte edilir
 * (`docs/153` §4).
 */
export function TeamScreenDesktop({ ctx }: { ctx: WorkspaceSectionRuntimeContext }): ReactNode {
    return (
        <TeamPage
            {...teamPropsFromContext(ctx)}
            renderMembers={(surface) => <TeamMemberTableDesktop {...surface} />}
        />
    );
}

export default TeamScreenDesktop;
