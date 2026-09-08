import type { ReactNode } from 'react';

import { TeamPage } from '../TeamPage';
import { teamPropsFromContext } from '../team/teamSectionProps';
import type { WorkspaceSectionRuntimeContext } from '../../WorkspaceApp';
import { TeamMemberTableDesktop } from './TeamMemberTableDesktop';

/**
 * EKİP ekranının MASAÜSTÜ bileşimi — `docs/151` §B.
 *
 * Sayfanın kendisi (davet formu, bekleyen davetler, rol rehberi, izin
 * kapıları) PAYLAŞILANdIR ve buraya kopyalanmaz. Değişen tek şey ÜYE
 * LİSTESİNİN çizimidir.
 *
 * Bekleyen davetler bilerek dokunmalı sürümde bırakıldı: orası bir tarama
 * yüzeyi değil, en fazla birkaç satırlık bir bekleme listesidir ve toplu
 * işlemin kazandıracağı bir şey yoktur. Ayrım gerekmeyen yere ikinci bir
 * dosya koymak, bakılacak ikinci bir yer yaratıp hiçbir şey kazandırmaz
 * (`docs/153` §9).
 */
export function TeamScreenDesktop({ ctx }: { ctx: WorkspaceSectionRuntimeContext }): ReactNode {
    return (
        <TeamPage
            {...teamPropsFromContext(ctx)}
            renderMemberList={(surface) => <TeamMemberTableDesktop {...surface} />}
        />
    );
}

export default TeamScreenDesktop;
